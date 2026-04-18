<?php

namespace App\Http\Middleware;

use App\Models\P24OnboardingPortal;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves a public onboarding portal by token, no auth required.
 * Binds the portal onto the request so controllers can access it,
 * and sets session('active_agency_id') so AgencyScope scopes to the
 * portal's agency for the request lifetime.
 */
class ResolveOnboardingPortal
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->route('token');
        $portal = P24OnboardingPortal::where('slug', $key)
            ->orWhere('token', $key)
            ->first();

        if (!$portal) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'message' => 'Portal not found for URL key.',
                    'url_key' => $key,
                ], 404);
            }
            abort(404);
        }

        if ($portal->revoked_at || ($portal->expires_at && $portal->expires_at->isPast())) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'message'     => 'This onboarding link has been revoked or has expired.',
                    'portal_id'   => $portal->id,
                    'revoked_at'  => $portal->revoked_at,
                    'expires_at'  => $portal->expires_at,
                ], 410);
            }
            return response()->view('onboarding.portal.expired', [
                'portal' => $portal,
            ], 410);
        }

        // Bind onto request only. We deliberately do NOT write to
        // session('active_agency_id') because that would leak into any
        // subsequent authenticated CoreX session in the same browser.
        // Public portal controllers scope queries explicitly via
        // $portal->rowsQuery() + agency_id, not via AgencyScope.
        $request->attributes->set('onboarding_portal', $portal);

        return $next($request);
    }
}
