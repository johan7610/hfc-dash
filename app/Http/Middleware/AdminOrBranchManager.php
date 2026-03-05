<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminOrBranchManager
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();

        if (!$user) {
            abort(403);
        }

        if ($user->hasPermission('manage_system') || $user->hasPermission('manage_branch')) {
            return $next($request);
        }

        abort(403);
    }
}
