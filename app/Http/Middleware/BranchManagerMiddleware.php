<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class BranchManagerMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            abort(403);
        }

        $user = Auth::user();

        if ($user->hasPermission('manage_system') || $user->hasPermission('manage_branch')) {
            return $next($request);
        }

        abort(403);
    }
}
