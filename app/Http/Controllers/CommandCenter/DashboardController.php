<?php

namespace App\Http\Controllers\CommandCenter;

use App\Http\Controllers\Controller;
use App\Models\CommandCenter\CalendarEvent;
use App\Models\CommandCenter\CommandTask;
use App\Models\CommandCenter\PropertyHealthScore;
use App\Models\CommandCenter\AgentScorecard;
use App\Models\Docuperfect\SignatureTemplate;
use App\Services\CandidatePractitionerService;
use App\Services\CommandCenter\CalendarEventService;
use App\Services\CommandCenter\PropertyHealthCalculator;
use App\Services\CommandCenter\TaskService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user   = $request->user();
        $period = now()->format('Y-m');

        // ── Activity Points (preserved from original dashboard) ──
        $defIds = DB::table('activity_definitions')
            ->where('is_enabled', 1)
            ->where('scope', 'global')
            ->pluck('id');

        $mtdPoints = (int) DB::table('daily_activity_entries as e')
            ->join('activity_definitions as d', 'd.id', '=', 'e.activity_definition_id')
            ->where('e.user_id', $user->id)
            ->where('e.period', $period)
            ->whereIn('e.activity_definition_id', $defIds)
            ->sum(DB::raw('e.value * d.weight'));

        $monthlyTarget = (int) (DB::table('targets')
            ->where('user_id', $user->id)
            ->where('period', $period)
            ->value('points_target') ?? 0);

        // ── Candidate Documents (preserved) ──
        $candidateService = new CandidatePractitionerService();
        $candidateDocs    = collect();
        if ($candidateService->canAuthorise($user)) {
            $candidateDocs = SignatureTemplate::with(['document', 'creator'])
                ->where('is_candidate_flow', true)
                ->whereIn('status', [
                    SignatureTemplate::STATUS_AWAITING_SUPERVISOR,
                    SignatureTemplate::STATUS_AWAITING_SUPERVISOR_FINAL,
                ])
                ->orderBy('created_at', 'desc')
                ->get();
        }

        // ── Calendar Events ──
        $calendarService = new CalendarEventService();
        $todayEvents     = $calendarService->getTodayEvents($user);
        $overdueEvents   = $calendarService->getOverdueEvents($user);
        $weekSummary     = $calendarService->getWeekSummary($user);

        // ── Tasks ──
        $taskService   = new TaskService();
        $myTasks       = $taskService->getOpenTasks($user, 8);
        $overdueTasks  = $taskService->getOverdueTasks($user, 5);
        $taskSummary   = $taskService->getSummary($user);

        // ── Property Health ──
        $healthCalc   = new PropertyHealthCalculator();
        $propsNeedingAttention = $healthCalc->getNeedingAttention($user->id, null, 5);
        $propHealthSummary = [
            'critical'  => PropertyHealthScore::critical()->whereHas('property', fn ($q) => $q->where('agent_id', $user->id))->count(),
            'attention' => PropertyHealthScore::where('grade', 'attention')->whereHas('property', fn ($q) => $q->where('agent_id', $user->id))->count(),
            'good'      => PropertyHealthScore::whereIn('grade', ['good', 'excellent'])->whereHas('property', fn ($q) => $q->where('agent_id', $user->id))->count(),
        ];

        // ── Agent Scorecard ──
        $scorecard = AgentScorecard::forUser($user->id)->currentWeek()->first();

        // ── Mini Calendar (current month events by date) ──
        $monthStart  = now()->startOfMonth();
        $monthEnd    = now()->endOfMonth();
        $monthEvents = CalendarEvent::forUser($user->id)
            ->inDateRange($monthStart, $monthEnd)
            ->where('status', 'pending')
            ->get()
            ->groupBy(fn ($e) => $e->event_date->toDateString());

        // ── Overall overdue count (events + tasks) ──
        $totalOverdue = $overdueEvents->count() + $overdueTasks->count();

        // ── Overdue popup items (unresolved past-due tasks + events) ──
        $overduePopupTasks = CommandTask::forUser($user->id)
            ->overdue()
            ->whereNull('resolution')
            ->with(['property', 'contact'])
            ->orderBy('due_date')
            ->limit(20)
            ->get();

        $overduePopupEvents = CalendarEvent::forUser($user->id)
            ->where('status', 'overdue')
            ->whereNull('resolution')
            ->with(['property', 'contact'])
            ->orderBy('event_date')
            ->limit(20)
            ->get();

        return view('command-center.dashboard', [
            'user'                  => $user,
            'period'                => $period,
            'mtdPoints'             => $mtdPoints,
            'monthlyTarget'         => $monthlyTarget,
            'candidateDocs'         => $candidateDocs,
            'todayEvents'           => $todayEvents,
            'overdueEvents'         => $overdueEvents,
            'weekSummary'           => $weekSummary,
            'myTasks'               => $myTasks,
            'overdueTasks'          => $overdueTasks,
            'taskSummary'           => $taskSummary,
            'propsNeedingAttention' => $propsNeedingAttention,
            'propHealthSummary'     => $propHealthSummary,
            'scorecard'             => $scorecard,
            'monthEvents'           => $monthEvents,
            'totalOverdue'          => $totalOverdue,
            'overduePopupTasks'     => $overduePopupTasks,
            'overduePopupEvents'    => $overduePopupEvents,
        ]);
    }

    /**
     * Resolve an overdue task: completed, extend, or did_not_happen.
     */
    public function resolveTask(Request $request, CommandTask $task)
    {
        $request->validate([
            'resolution'      => 'required|in:completed,extended,did_not_happen',
            'extend_days'     => 'nullable|integer|min:1|max:90',
            'resolution_note' => 'nullable|string|max:500',
        ]);

        $resolution = $request->resolution;

        if ($resolution === 'completed') {
            $task->update([
                'status'          => CommandTask::STATUS_DONE,
                'completed_at'    => now(),
                'resolution'      => 'completed',
                'resolution_note' => $request->resolution_note,
            ]);
        } elseif ($resolution === 'extended') {
            $days = $request->extend_days ?? 7;
            $task->update([
                'due_date'        => now()->addDays($days),
                'resolution'      => 'extended',
                'resolution_note' => $request->resolution_note ?? "Extended by {$days} day(s)",
                'metadata'        => array_merge($task->metadata ?? [], ['reminder_sent' => null]),
            ]);
            // Clear resolution so it can be resolved again if it goes overdue
            $task->update(['resolution' => null]);
        } elseif ($resolution === 'did_not_happen') {
            $task->update([
                'status'          => CommandTask::STATUS_DISMISSED,
                'resolution'      => 'did_not_happen',
                'resolution_note' => $request->resolution_note ?? 'Did not take place',
            ]);
        }

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Task resolved.');
    }

    /**
     * Resolve an overdue event: completed, extend, or did_not_happen.
     */
    public function resolveEvent(Request $request, CalendarEvent $calendarEvent)
    {
        $request->validate([
            'resolution'      => 'required|in:completed,extended,did_not_happen',
            'extend_days'     => 'nullable|integer|min:1|max:90',
            'resolution_note' => 'nullable|string|max:500',
        ]);

        $resolution = $request->resolution;

        if ($resolution === 'completed') {
            $calendarEvent->update([
                'status'          => 'completed',
                'resolution'      => 'completed',
                'resolution_note' => $request->resolution_note,
            ]);
        } elseif ($resolution === 'extended') {
            $days = $request->extend_days ?? 7;
            $calendarEvent->update([
                'event_date'      => now()->addDays($days),
                'status'          => 'pending',
                'resolution'      => null,
                'resolution_note' => $request->resolution_note ?? "Rescheduled by {$days} day(s)",
                'metadata'        => array_merge($calendarEvent->metadata ?? [], ['reminder_sent' => null]),
            ]);
        } elseif ($resolution === 'did_not_happen') {
            $calendarEvent->update([
                'status'          => 'dismissed',
                'resolution'      => 'did_not_happen',
                'resolution_note' => $request->resolution_note ?? 'Did not take place',
            ]);
        }

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Event resolved.');
    }
}
