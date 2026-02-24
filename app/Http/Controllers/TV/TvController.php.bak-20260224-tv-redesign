<?php

namespace App\Http\Controllers\TV;

use App\Http\Controllers\Controller;
use App\Models\TvAccessCode;
use App\Models\Deal;
use App\Services\Admin\CompanyPerformanceService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TvController extends Controller
{
    /**
     * Show the TV code entry screen (public, no auth).
     */
    public function index()
    {
        return view('tv.index');
    }

    /**
     * Validate code and redirect to TV display.
     */
    public function verify(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string', 'size:6', 'regex:/^\d{6}$/'],
        ]);

        $code = $request->input('code');

        $tvCode = TvAccessCode::where('code', $code)
            ->active()
            ->first();

        if (!$tvCode) {
            return back()->withErrors(['code' => 'Invalid or expired code. Please check with your branch manager.'])->withInput();
        }

        // Update last_used_at
        $tvCode->update(['last_used_at' => now()]);

        return redirect()->route('tv.display', ['code' => $code]);
    }

    /**
     * Show the TV display for a given code (public, code IS the auth).
     */
    public function display(Request $request, CompanyPerformanceService $service, string $code)
    {
        $tvCode = TvAccessCode::where('code', $code)
            ->active()
            ->first();

        if (!$tvCode) {
            return view('tv.deactivated');
        }

        // Update last_used_at
        $tvCode->update(['last_used_at' => now()]);

        $branchId = $tvCode->branch_id;
        $period = Carbon::now()->format('Y-m');

        // Branch existence check
        $branchName = DB::table('branches')->where('id', $branchId)->value('name');
        if (!$branchName) {
            return view('tv.deactivated');
        }

        // Reuse exact same data pipeline as BranchTvController
        $rollup = $service->getBranchRollup($branchId, $period);
        $statusSummary = Deal::statusSummaryForBranch((int) $branchId, (string) $period);

        // Listing Stock Stats
        $tvListings = \App\Models\ListingStock::query()
            ->where('source', 'propcon')
            ->where(function ($x) use ($branchId) {
                $x->where('branch_id', $branchId)
                  ->orWhereIn('user_id', function ($sq) use ($branchId) {
                      $sq->select('id')
                         ->from('users')
                         ->where('branch_id', $branchId)
                         ->where('is_active', 1);
                  });
            })
            ->where(function ($q) {
                $q->whereRaw("lower(coalesce(status,'')) like '%active%'")
                  ->orWhereRaw("lower(coalesce(status,'')) like '%for sale%'");
            })
            ->get();

        $tvTotal = $tvListings->count();
        $tvAvgDom = $tvTotal > 0
            ? (int) round($tvListings->filter(fn($l) => $l->days_on_market !== null)->avg('days_on_market') ?? 0)
            : 0;
        $tvStale = $tvListings->filter(fn($l) => $l->is_stale)->count();
        $tvExpiring = $tvListings->filter(fn($l) => $l->is_expiring_soon)->count();
        $tvExpired = $tvListings->filter(fn($l) => $l->is_expired)->count();

        $listingStats = [
            'total' => $tvTotal,
            'avg_days_on_market' => $tvAvgDom,
            'stale' => $tvStale,
            'expiring_soon' => $tvExpiring,
            'expired' => $tvExpired,
        ];

        // TV Messages
        $tvMessagesRaw = \App\Models\TvMessage::query()
            ->activeForBranch((int) $branchId)
            ->with(['creator:id,name,email'])
            ->get();

        $ph = [
            '{{branch_name}}'      => (string) $branchName,
            '{{period}}'           => (string) $period,
            '{{deals_target}}'     => (string) (int) ($rollup['totals']['targets']['deals'] ?? 0),
            '{{deals_actual}}'     => (string) (int) ($rollup['totals']['actuals']['deals'] ?? 0),
            '{{deals_remaining}}'  => (string) (int) max(((int)($rollup['totals']['targets']['deals'] ?? 0)) - ((int)($rollup['totals']['actuals']['deals'] ?? 0)), 0),
            '{{value_target}}'     => (string) number_format((float) ($rollup['totals']['targets']['value'] ?? 0), 0, '.', ''),
            '{{value_actual}}'     => (string) number_format((float) ($rollup['totals']['actuals']['value'] ?? 0), 0, '.', ''),
            '{{value_remaining}}'  => (string) number_format(max(((float)($rollup['totals']['targets']['value'] ?? 0)) - ((float)($rollup['totals']['actuals']['value'] ?? 0)), 0), 0, '.', ''),
            '{{points_target}}'    => (string) number_format((float) ($rollup['points']['target'] ?? 0), 0, '.', ''),
            '{{points_actual}}'    => (string) number_format((float) ($rollup['points']['actual'] ?? 0), 0, '.', ''),
            '{{points_status}}'    => (string) ($rollup['points']['status'] ?? '—'),
            '{{listings_active}}'  => (string) (int) ($listingStats['total'] ?? 0),
            '{{listings_avg_dom}}' => (string) (int) ($listingStats['avg_days_on_market'] ?? 0),
            '{{listings_stale}}'   => (string) (int) ($listingStats['stale'] ?? 0),
            '{{listings_expiring}}'=> (string) (int) ($listingStats['expiring_soon'] ?? 0),
            '{{listings_expired}}' => (string) (int) ($listingStats['expired'] ?? 0),
        ];

        $tvMessages = $tvMessagesRaw->map(function ($m) use ($ph) {
            $msg = (string) ($m->message ?? '');
            if ($msg !== '') {
                $msg = strtr($msg, $ph);
            }
            return [
                'id' => $m->id,
                'branch_id' => $m->branch_id,
                'title' => $m->title,
                'message' => $msg,
                'display_area' => (string) ($m->display_area ?? 'both'),
                'is_enabled' => (bool) $m->is_enabled,
                'creator_name' => $m->creator->name ?? null,
            ];
        })->filter(fn($x) => trim((string)$x['message']) !== '')->values()->all();

        return view('tv.branch', [
            'tvMessages' => $tvMessages,
            'tvMessagesRawCount' => is_countable($tvMessagesRaw) ? count($tvMessagesRaw) : 0,
            'listingStats' => $listingStats,
            'statusSummary' => $statusSummary,
            'rollup' => $rollup,
            'branchName' => $branchName,
            'tvCode' => $code,
            'autoRefresh' => true,
        ]);
    }
}
