<?php

namespace App\Http\Controllers;

use App\Models\DocumentFiling;
use App\Models\Branch;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DocumentFilingController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $scope = PermissionService::getDataScope($user, 'filing');
        $isAdmin = $scope === 'all';
        $isBM = $scope === 'branch';
        $branchId = $user->effectiveBranchId();

        // Build query with permission-based scoping
        $query = DocumentFiling::with(['agent', 'branch', 'capturedBy'])
            ->visibleTo($user);

        // Additional branch filter for users with 'all' scope
        if ($isAdmin && $request->filled('branch_id')) {
            $query->forBranch($request->branch_id);
            $branchId = $request->branch_id;
        }

        // Agent filter
        if ($request->filled('agent_id')) {
            $query->forAgent($request->agent_id);
        }

        // Search
        if ($request->filled('search')) {
            $query->search($request->search);
        }

        // Document type filter
        if ($request->filled('document_type') && $request->document_type !== 'All') {
            $query->where('document_type', $request->document_type);
        }

        // Status filter
        $showArchived = false;
        if ($request->filled('status') && $request->status !== 'All') {
            if ($request->status === 'Archived') {
                // Switch to soft-deleted records only
                $showArchived = true;
                $query = DocumentFiling::onlyTrashed()
                    ->with(['agent', 'branch', 'capturedBy'])
                    ->visibleTo($user);
                if ($isAdmin && $request->filled('branch_id')) {
                    $query->forBranch($request->branch_id);
                }
                if ($request->filled('agent_id')) {
                    $query->forAgent($request->agent_id);
                }
                if ($request->filled('search')) {
                    $query->search($request->search);
                }
                if ($request->filled('document_type') && $request->document_type !== 'All') {
                    $query->where('document_type', $request->document_type);
                }
            } elseif ($request->status === 'Active') {
                $query->where(function ($q) {
                    $q->whereNull('expiry_date')
                      ->orWhere('expiry_date', '>', Carbon::today()->addDays(30));
                });
            } elseif ($request->status === 'Expiring') {
                $query->expiringSoon();
            } elseif ($request->status === 'Expired') {
                $query->expired();
            }
        }

        $filings = $query->orderBy('created_at', 'desc')->get();

        // Summary counts (scoped to same visibility)
        $countQuery = DocumentFiling::visibleTo($user);
        if ($isAdmin && $request->filled('branch_id')) {
            $countQuery->forBranch($request->branch_id);
        }

        $allFilings = $countQuery->get();
        $totalCount = $allFilings->count();
        $activeCount = $allFilings->filter(fn($f) => $f->status === 'active')->count();
        $expiringCount = $allFilings->filter(fn($f) => $f->status === 'expiring')->count();
        $expiredCount = $allFilings->filter(fn($f) => $f->status === 'expired')->count();

        // Data for dropdowns
        $branches = $isAdmin ? Branch::orderBy('name')->get() : Branch::where('id', $user->effectiveBranchId())->get();
        $agentsQuery = User::where('is_active', 1)->orderBy('name');
        if (!$isAdmin) {
            $agentsQuery->where('branch_id', $user->effectiveBranchId());
        } elseif ($request->filled('branch_id')) {
            $agentsQuery->where('branch_id', $request->branch_id);
        }
        $agents = $agentsQuery->get();

        // Branch name for header
        $branchName = $isAdmin && !$request->filled('branch_id')
            ? 'All Branches'
            : ($branchId ? (Branch::find($branchId)->name ?? 'Unknown') : 'Unknown');

        return view('filing-register.index', compact(
            'filings', 'branches', 'agents', 'branchName',
            'isAdmin', 'isBM', 'showArchived',
            'totalCount', 'activeCount', 'expiringCount', 'expiredCount'
        ));
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'agent_id' => 'required|exists:users,id',
            'document_type' => 'required|in:OA,EA,Other',
            'file_reference' => 'required|string|max:255',
            'sequence_number' => 'required|string|max:255',
            'property_address' => 'required|string|max:255',
            'seller_name' => 'nullable|string|max:255',
            'expiry_date' => 'nullable|date',
            'notes' => 'nullable|string|max:2000',
        ]);

        // Non-'all' scope users can only add to their own branch
        $scope = PermissionService::getDataScope($user, 'filing');
        if ($scope !== 'all') {
            $validated['branch_id'] = $user->effectiveBranchId();
        }

        $validated['captured_by'] = $user->id;

        DocumentFiling::create($validated);

        return redirect()->route('filing-register.index')
            ->with('success', 'Filing entry added.');
    }

    public function update(Request $request, $id)
    {
        $user = auth()->user();

        $filing = DocumentFiling::findOrFail($id);

        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'agent_id' => 'required|exists:users,id',
            'document_type' => 'required|in:OA,EA,Other',
            'file_reference' => 'required|string|max:255',
            'sequence_number' => 'required|string|max:255',
            'property_address' => 'required|string|max:255',
            'seller_name' => 'nullable|string|max:255',
            'expiry_date' => 'nullable|date',
            'notes' => 'nullable|string|max:2000',
        ]);

        $filing->update($validated);

        return redirect()->route('filing-register.index')
            ->with('success', 'Filing entry updated.');
    }

    public function destroy($id)
    {
        $filing = DocumentFiling::findOrFail($id);
        $filing->delete();

        return redirect()->route('filing-register.index')
            ->with('success', 'Filing entry archived.');
    }

    // ── Restore soft-deleted ──

    public function restore($id)
    {
        abort_unless(auth()->user()->hasPermission('filing.edit'), 403);
        $record = DocumentFiling::onlyTrashed()->findOrFail($id);
        $record->restore();
        return redirect()->back()->with('success', 'Record restored.');
    }
}
