<?php

namespace App\Http\Controllers\BM;

use App\Http\Controllers\Controller;
use App\Models\TvAccessCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TvCodeController extends Controller
{
    /**
     * Generate a new TV access code for the BM's branch.
     * Deactivates any previous active code for this branch.
     */
    public function generate(Request $request)
    {
        $user = Auth::user();
        $branchId = (int) ($user->effectiveBranchId() ?? ($user->branch_id ?? 0));

        if ($branchId <= 0) {
            return back()->with('status', 'No branch assigned.');
        }

        // Deactivate all existing active codes for this branch
        TvAccessCode::where('branch_id', $branchId)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        // Generate new code
        TvAccessCode::create([
            'branch_id'  => $branchId,
            'code'       => TvAccessCode::generateUniqueCode(),
            'created_by' => $user->id,
            'is_active'  => true,
        ]);

        return back()->with('status', 'New TV code generated.');
    }

    /**
     * Revoke (deactivate) the current TV access code without generating a new one.
     */
    public function revoke(Request $request)
    {
        $user = Auth::user();
        $branchId = (int) ($user->effectiveBranchId() ?? ($user->branch_id ?? 0));

        if ($branchId <= 0) {
            return back()->with('status', 'No branch assigned.');
        }

        $deactivated = TvAccessCode::where('branch_id', $branchId)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        if ($deactivated > 0) {
            return back()->with('status', 'TV code revoked. TVs using this code will be disconnected.');
        }

        return back()->with('status', 'No active TV code to revoke.');
    }
}
