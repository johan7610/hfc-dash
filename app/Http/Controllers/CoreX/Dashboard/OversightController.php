<?php

namespace App\Http\Controllers\CoreX\Dashboard;

use App\Http\Controllers\Controller;
use App\Mail\OversightNudgeMail;
use App\Models\OversightNudge;
use App\Models\User;
use App\Models\UserOversightPreference;
use App\Services\Oversight\OversightService;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Response;

class OversightController extends Controller
{
    public function index(Request $request, OversightService $service)
    {
        $user = $request->user();
        abort_unless($user->hasPermission('dashboard.oversight.view'), Response::HTTP_FORBIDDEN);

        $rows = $service->feed(
            $user,
            $request->string('category')->toString() ?: null,
            $request->integer('agent_id') ?: null,
        );

        $agents = $service->agentsInScope($user);

        return view('corex.dashboard.oversight.index', [
            'rows'       => $rows,
            'agents'     => $agents,
            'categories' => UserOversightPreference::CATEGORIES,
            'canManage'  => $user->hasPermission('dashboard.oversight.manage'),
            'filters'    => [
                'category' => $request->string('category')->toString() ?: null,
                'agent_id' => $request->integer('agent_id') ?: null,
            ],
        ]);
    }

    public function nudge(Request $request, OversightService $service)
    {
        $user = $request->user();
        abort_unless($user->hasPermission('dashboard.oversight.manage'), Response::HTTP_FORBIDDEN);

        $data = $request->validate([
            'to_user_id'   => 'required|integer|exists:users,id',
            'category'     => 'required|string|in:' . implode(',', UserOversightPreference::CATEGORIES),
            'subject_type' => 'nullable|string',
            'subject_id'   => 'nullable|integer',
            'message'      => 'required|string|max:2000',
        ]);

        $allowedIds = $service->agentsInScope($user)->pluck('id')->all();
        abort_unless(in_array((int) $data['to_user_id'], $allowedIds, true), Response::HTTP_FORBIDDEN);

        $target = User::query()->findOrFail($data['to_user_id']);

        $nudge = OversightNudge::create([
            'agency_id'    => $user->agency_id,
            'from_user_id' => $user->id,
            'to_user_id'   => $target->id,
            'subject_type' => $data['subject_type'] ?? null,
            'subject_id'   => $data['subject_id'] ?? null,
            'category'     => $data['category'],
            'message'      => $data['message'],
            'sent_at'      => now(),
        ]);

        if ($target->email) {
            Mail::to($target->email)->queue(new OversightNudgeMail($nudge, $user));
        }

        DatabaseNotification::create([
            'id'              => (string) \Illuminate\Support\Str::uuid(),
            'type'            => 'oversight.nudge',
            'notifiable_type' => User::class,
            'notifiable_id'   => $target->id,
            'data'            => [
                'message'    => 'You were nudged by ' . $user->name,
                'category'   => $data['category'],
                'manager_id' => $user->id,
                'nudge_id'   => $nudge->id,
            ],
        ]);

        return back()->with('status', 'Nudge sent to ' . $target->name);
    }

    public function settings(Request $request)
    {
        $user = $request->user();
        abort_unless($user->hasPermission('dashboard.oversight.view'), Response::HTTP_FORBIDDEN);

        $existing = UserOversightPreference::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('category');

        $prefs = collect(UserOversightPreference::CATEGORIES)->map(function ($cat) use ($existing) {
            $defaults = UserOversightPreference::DEFAULTS[$cat] ?? ['threshold_hours' => 24, 'notify_channel' => 'in_app'];
            $row = $existing[$cat] ?? null;
            return [
                'category'        => $cat,
                'enabled'         => $row?->enabled ?? true,
                'threshold_hours' => $row?->threshold_hours ?? $defaults['threshold_hours'],
                'notify_channel'  => $row?->notify_channel ?? $defaults['notify_channel'],
            ];
        });

        return view('corex.settings.user.oversight', ['prefs' => $prefs]);
    }

    public function saveSettings(Request $request)
    {
        $user = $request->user();
        abort_unless($user->hasPermission('dashboard.oversight.view'), Response::HTTP_FORBIDDEN);

        $data = $request->validate([
            'preferences' => 'required|array',
            'preferences.*.category'        => 'required|string|in:' . implode(',', UserOversightPreference::CATEGORIES),
            'preferences.*.enabled'         => 'sometimes|boolean',
            'preferences.*.threshold_hours' => 'nullable|integer|min:0|max:8760',
            'preferences.*.notify_channel'  => 'required|in:email,in_app,both',
        ]);

        foreach ($data['preferences'] as $pref) {
            UserOversightPreference::updateOrCreate(
                ['user_id' => $user->id, 'category' => $pref['category']],
                [
                    'agency_id'       => $user->agency_id,
                    'enabled'         => (bool) ($pref['enabled'] ?? false),
                    'threshold_hours' => $pref['threshold_hours'] ?? null,
                    'notify_channel'  => $pref['notify_channel'],
                ],
            );
        }

        return back()->with('status', 'Oversight preferences saved.');
    }
}
