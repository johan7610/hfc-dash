<?php

namespace App\Http\Controllers\CoreX;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Permission-based redirect to the appropriate dashboard
        if ($user->hasPermission('manage_system')) {
            return redirect()->route('admin.performance');
        }

        if ($user->hasPermission('manage_branch')) {
            return redirect()->route('bm.performance');
        }

        // Agent (default)
        return redirect()->route('agent.dashboard');
    }
}
