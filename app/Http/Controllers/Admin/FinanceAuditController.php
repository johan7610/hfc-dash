<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Deal;
use App\Models\FinanceAuditRun;
use App\Models\FinanceAuditItem;
use App\Models\FinanceDefinition;
use App\Models\FinanceComputedValue;
use App\Models\User;
use App\Services\Finance\AuditService;
use Illuminate\Http\Request;

class FinanceAuditController extends Controller
{
    /**
     * Get distinct periods from deals table, ordered newest first.
     */
    private function availablePeriods()
    {
        return Deal::select('period')
            ->whereNotNull('period')
            ->where('period', '!=', '')
            ->distinct()
            ->orderByDesc('period')
            ->pluck('period');
    }

    public function index(Request $request)
    {
        $runs = FinanceAuditRun::query()
            ->when($request->period, fn ($q, $p) => $q->where('period', $p))
            ->withCount([
                'items',
                'items as errors_count'   => fn ($q) => $q->where('severity', 'error'),
                'items as warnings_count' => fn ($q) => $q->where('severity', 'warn'),
            ])
            ->orderByDesc('id')
            ->paginate(20);

        $availablePeriods = $this->availablePeriods();

        return view('admin.finance.audit.index', compact('runs', 'availablePeriods'));
    }

    public function run(Request $request, FinanceAuditRun $run)
    {
        $query = $run->items();

        if ($request->filled('severity') && $request->severity !== 'all') {
            $query->where('severity', $request->severity);
        }
        if ($request->filled('definition_key')) {
            $query->where('definition_key', 'like', '%' . $request->definition_key . '%');
        }
        if ($request->filled('entity_type')) {
            $query->where('entity_type', $request->entity_type);
        }
        if ($request->filled('entity_id')) {
            $query->where('entity_id', $request->entity_id);
        }
        if ($request->boolean('diff_only')) {
            $query->whereRaw('ABS(COALESCE(diff_numeric, 0)) > 0.01');
        }

        $items = $query
            ->orderByRaw("CASE severity WHEN 'error' THEN 0 WHEN 'warn' THEN 1 ELSE 2 END")
            ->orderBy('entity_type')
            ->orderBy('entity_id')
            ->orderBy('id')
            ->paginate(50)
            ->withQueryString();

        $counts = [
            'total'    => $run->items()->count(),
            'errors'   => $run->items()->where('severity', 'error')->count(),
            'warnings' => $run->items()->where('severity', 'warn')->count(),
            'matches'  => $run->items()->where('severity', 'info')->count(),
        ];

        // Build entity lookup maps (lightweight query for IDs only)
        $allRefs = $run->items()->get(['entity_type', 'entity_id']);
        $dealIds   = $allRefs->where('entity_type', 'deal')->pluck('entity_id')->unique();
        $agentIds  = $allRefs->where('entity_type', 'agent_period')->pluck('entity_id')->unique();
        $branchIds = $allRefs->where('entity_type', 'branch_period')->pluck('entity_id')->unique();

        $dealMap = $dealIds->isNotEmpty()
            ? Deal::with('agents')->whereIn('id', $dealIds)->get()->keyBy('id')
            : collect();

        $userMap = $agentIds->isNotEmpty()
            ? User::whereIn('id', $agentIds)->get(['id', 'name', 'branch_id'])->keyBy('id')
            : collect();

        $allBranchIds = $branchIds->merge($userMap->pluck('branch_id')->filter())->unique();
        $branchMap = $allBranchIds->isNotEmpty()
            ? Branch::whereIn('id', $allBranchIds)->get(['id', 'name'])->keyBy('id')
            : collect();

        // Group all items by entity for collapsible view
        $groupedItems = $run->items()
            ->orderByRaw("CASE severity WHEN 'error' THEN 0 WHEN 'warn' THEN 1 ELSE 2 END")
            ->orderBy('entity_type')
            ->orderBy('entity_id')
            ->orderBy('id')
            ->get()
            ->groupBy(fn ($item) => $item->entity_type . ':' . $item->entity_id);

        $groupSummaries = [];
        foreach ($groupedItems as $groupKey => $groupItemCollection) {
            $groupSummaries[$groupKey] = [
                'errors'   => $groupItemCollection->where('severity', 'error')->count(),
                'warnings' => $groupItemCollection->where('severity', 'warn')->count(),
                'matches'  => $groupItemCollection->where('severity', 'info')->count(),
                'total'    => $groupItemCollection->count(),
            ];
        }

        return view('admin.finance.audit.run', compact(
            'run', 'items', 'counts',
            'dealMap', 'userMap', 'branchMap',
            'groupedItems', 'groupSummaries'
        ));
    }

    public function deal(Request $request, Deal $deal)
    {
        if ($request->filled('run_id')) {
            $run = FinanceAuditRun::findOrFail($request->run_id);
        } elseif ($request->filled('period')) {
            $run = FinanceAuditRun::where('period', $request->period)
                ->orderByDesc('id')
                ->firstOrFail();
        } else {
            $run = FinanceAuditRun::orderByDesc('id')->firstOrFail();
        }

        $items = FinanceAuditItem::where('audit_run_id', $run->id)
            ->where('entity_type', 'deal')
            ->where('entity_id', $deal->id)
            ->orderByRaw("CASE severity WHEN 'error' THEN 0 WHEN 'warn' THEN 1 ELSE 2 END")
            ->orderBy('definition_key')
            ->get();

        // Load deal agents for name resolution
        $deal->load('agents');
        $agentNameMap = [];
        foreach ($deal->agents as $agent) {
            $agentNameMap[$agent->id] = $agent->name;
        }

        return view('admin.finance.audit.deal', compact('run', 'deal', 'items', 'agentNameMap'));
    }

    public function definitions()
    {
        $definitions = FinanceDefinition::orderBy('key')->get();
        $computedCount = FinanceComputedValue::count();
        $availablePeriods = $this->availablePeriods();

        return view('admin.finance.definitions', compact('definitions', 'computedCount', 'availablePeriods'));
    }

    public function recalculate(Request $request, AuditService $auditService)
    {
        $mode = $request->input('mode', 'single');
        $period = $request->input('period', now()->format('Y-m'));

        if ($mode === 'all') {
            $periods = $this->availablePeriods();

            if ($periods->isEmpty()) {
                return redirect()->back()->with('error', 'No periods with deals found.');
            }

            $totalItems = 0;
            $totalErrors = 0;

            foreach ($periods as $p) {
                try {
                    $run = $auditService->run($p, 200, ['audit_scope' => 'all'], [
                        'audit_scope'   => 'all',
                        'rollup_roles'  => ['agent', 'bm', 'admin'],
                        'rollup_stages' => ['pending', 'granted', 'registered', 'declined'],
                    ]);
                    $totalItems += $run->items()->count();
                    $totalErrors += $run->items()->where('severity', 'error')->count();
                } catch (\Throwable $e) {
                    return redirect()->back()->with('error', "Recalculation failed on period {$p}: " . $e->getMessage());
                }
            }

            return redirect()->route('admin.finance.audit.index')
                ->with('status', "Recalculation complete for ALL {$periods->count()} periods. Total: {$totalItems} items, {$totalErrors} errors.");
        }

        // Single period mode
        if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
            return redirect()->back()->with('error', "Invalid period format: {$period}");
        }

        try {
            $run = $auditService->run($period, 200, ['audit_scope' => 'all'], [
                'audit_scope'   => 'all',
                'rollup_roles'  => ['agent', 'bm', 'admin'],
                'rollup_stages' => ['pending', 'granted', 'registered', 'declined'],
            ]);

            $itemCount  = $run->items()->count();
            $errorCount = $run->items()->where('severity', 'error')->count();
            $computedCount = FinanceComputedValue::where('audit_run_id', $run->id)->count();

            return redirect()->route('admin.finance.audit.run', $run)
                ->with('status', "Recalculation complete for {$period}. {$itemCount} items, {$errorCount} errors, {$computedCount} computed values.");
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', "Recalculation failed: " . $e->getMessage());
        }
    }
}
