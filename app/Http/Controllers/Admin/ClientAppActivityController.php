<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientAccessLog;
use App\Models\ClientSigninAttempt;
use App\Models\Scopes\AgencyScope;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Admin → Client App Activity. System-wide log of client mobile-app use.
 * Spec: .ai/specs/client-auth.md
 */
class ClientAppActivityController extends Controller
{
    public function index(Request $request): View
    {
        $tab = $request->input('tab', 'activity');

        if ($tab === 'attempts') {
            $attempts = ClientSigninAttempt::query()
                ->when($request->filled('q'), fn ($q) => $q->where('identifier', 'like', '%' . $request->q . '%'))
                ->latest()
                ->paginate(50)->withQueryString();

            return view('admin.client-app-activity.index', [
                'tab'      => 'attempts',
                'attempts' => $attempts,
                'logs'     => null,
            ]);
        }

        $logs = ClientAccessLog::query()
            ->withoutGlobalScope(AgencyScope::class)
            ->with(['clientUser:id,email', 'agency:id,name', 'contact:id,first_name,last_name'])
            ->when($request->filled('event'), fn ($q) => $q->where('event', $request->event))
            ->when($request->filled('agency_id'), fn ($q) => $q->where('agency_id', $request->agency_id))
            ->when($request->filled('q'), function ($q) use ($request) {
                $q->whereHas('clientUser', fn ($cq) => $cq->where('email', 'like', '%' . $request->q . '%'));
            })
            ->latest()
            ->paginate(50)->withQueryString();

        $events = ClientAccessLog::query()
            ->withoutGlobalScope(AgencyScope::class)
            ->select('event')->distinct()->orderBy('event')->pluck('event');

        return view('admin.client-app-activity.index', [
            'tab'      => 'activity',
            'logs'     => $logs,
            'events'   => $events,
            'attempts' => null,
        ]);
    }
}
