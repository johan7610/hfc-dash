<?php

namespace App\Console\Commands\CommandCenter;

use App\Models\CommandCenter\CalendarEvent;
use App\Models\CommandCenter\CalendarEventAuditEntry;
use App\Models\CommandCenter\CommandTask;
use App\Models\Contact;
use App\Services\CommandCenter\Calendar\CalendarNotificationDispatcher;
use App\Services\CommandCenter\Calendar\CalendarSourceRegistry;
use App\Services\CommandCenter\Calendar\CalendarThresholdResolver;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Nightly reconciliation:
 *
 * 1. Iterate registered CalendarSourceContract sources.
 * 2. Each returns "what events should exist right now".
 * 3. Upsert keyed by (source_type, source_id, category).
 * 4. Detect colour transitions on ALL pending events and fire notifications.
 *
 * Transition detection is stateless: yesterday's colour is computed by
 * simulating the resolver as of now()-1day. No DB column needed.
 *
 * Phase 0 ships with empty registry — no-ops cleanly.
 */
class ReconcileCalendarEvents extends Command
{
    protected $signature = 'corex:calendar:reconcile {--dry : Report without writing}';
    protected $description = 'Reconcile calendar events with source services + fire colour transition notifications';

    /** Synthetic events older than this past their event_date are soft-deleted. */
    private const SYNTHETIC_ORPHAN_GRACE_DAYS = 90;

    public function handle(
        CalendarSourceRegistry $registry,
        CalendarThresholdResolver $resolver,
        CalendarNotificationDispatcher $dispatcher,
    ): int {
        $dry = (bool) $this->option('dry');

        $sources = $registry->all();
        if ($sources->isEmpty()) {
            $this->info('No calendar source services registered. Nothing to reconcile.');
        } else {
            $this->info("Reconciling {$sources->count()} source(s)...");

            foreach ($sources as $source) {
                try {
                    $events = $source->syncAll();
                    $this->info("  {$source->name()}: {$events->count()} events from source");

                    if (!$dry) {
                        foreach ($events as $payload) {
                            $this->upsertEvent($payload);
                        }
                    }
                } catch (\Throwable $e) {
                    Log::error('ReconcileCalendarEvents: source failed', [
                        'source' => $source->name(),
                        'error'  => $e->getMessage(),
                    ]);
                    $this->error("  {$source->name()}: FAILED — see log");
                }
            }
        }

        // Colour transition pass — runs over ALL pending events.
        $transitions = $this->detectAndFireTransitions($resolver, $dispatcher, $dry);
        $this->info("Colour transitions fired: {$transitions}");

        // Synthetic orphan cleanup — soft-delete computed events that
        // have aged out of any plausible display window.
        $cleaned = $this->cleanupSyntheticOrphans($dry);
        $this->info("Synthetic orphans soft-deleted: {$cleaned}");

        // Auto-task creation for missed feedback (M2.5)
        $taskCount = $this->createMissedFeedbackTasks($dry);
        $this->info("Missed-feedback tasks created: {$taskCount}" . ($dry ? ' (dry)' : ''));

        return self::SUCCESS;
    }

    private function upsertEvent(array $payload): void
    {
        $required = ['event_type', 'category', 'title', 'event_date', 'source_type', 'source_id'];
        foreach ($required as $k) {
            if (!array_key_exists($k, $payload)) {
                throw new \InvalidArgumentException("Missing required key: {$k}");
            }
        }

        CalendarEvent::withoutGlobalScopes()
            ->updateOrCreate(
                [
                    'source_type' => $payload['source_type'],
                    'source_id'   => $payload['source_id'],
                    'category'    => $payload['category'],
                ],
                array_merge(['status' => 'pending'], $payload)
            );
    }

    private function detectAndFireTransitions(
        CalendarThresholdResolver $resolver,
        CalendarNotificationDispatcher $dispatcher,
        bool $dry,
    ): int {
        $fired = 0;

        CalendarEvent::withoutGlobalScopes()
            ->whereNotNull('event_date')
            ->where('status', 'pending')
            ->whereNull('deleted_at')
            ->chunkById(200, function ($events) use ($resolver, $dispatcher, $dry, &$fired) {
                foreach ($events as $event) {
                    $today = $resolver->resolveForEvent($event);
                    $yesterday = $this->resolveAsOf($resolver, $event, now()->subDay());

                    if ($today !== $yesterday && $today !== null) {
                        if (!$dry) {
                            $dispatcher->onColourTransition($event, $yesterday, $today);
                        }
                        $fired++;
                    }
                }
            });

        return $fired;
    }

    /**
     * Resolve the colour the event WOULD have had as of a given moment.
     * Shift event_date by the delta so the resolver's now()-relative math
     * reflects the past moment.
     */
    private function resolveAsOf(
        CalendarThresholdResolver $resolver,
        CalendarEvent $event,
        Carbon $asOf,
    ): ?string {
        if (!$event->event_date || !$event->category) {
            return null;
        }
        $deltaDays = (int) now()->startOfDay()->diffInDays($asOf->copy()->startOfDay(), false);
        $shifted = Carbon::parse($event->event_date)->subDays($deltaDays);
        return $resolver->resolve($event->agency_id, $event->category, $shifted);
    }

    /**
     * Soft-delete synthetic events whose event_date is more than
     * SYNTHETIC_ORPHAN_GRACE_DAYS in the past. Only synthetic:* source
     * types are touched — stored events with real source rows are never
     * cleaned by this method.
     */
    private function cleanupSyntheticOrphans(bool $dry): int
    {
        $cutoff = now()->subDays(self::SYNTHETIC_ORPHAN_GRACE_DAYS);

        $query = CalendarEvent::withoutGlobalScopes()
            ->where('source_type', 'like', 'synthetic:%')
            ->where('event_date', '<', $cutoff)
            ->whereNull('deleted_at');

        if ($dry) {
            return $query->count();
        }

        return $query->delete();
    }

    /** Grace period before creating a missed-feedback task. */
    private const FEEDBACK_GRACE_HOURS = 24;

    /**
     * Create CommandTask for manual events that are past the grace period,
     * have linked contacts, no feedback captured, and no existing open task.
     */
    private function createMissedFeedbackTasks(bool $dry): int
    {
        $cutoff = now()->subHours(self::FEEDBACK_GRACE_HOURS);

        $eligibleEventIds = DB::table('calendar_events as ce')
            ->whereIn('ce.source_type', ['manual', 'manual:demo'])
            ->where('ce.event_date', '<', $cutoff)
            ->where('ce.status', '!=', 'completed')
            ->whereNull('ce.deleted_at')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('calendar_event_links')
                  ->whereColumn('calendar_event_links.calendar_event_id', 'ce.id')
                  ->where('linkable_type', Contact::class)
                  ->whereNull('deleted_at');
            })
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('calendar_event_feedback')
                  ->whereColumn('calendar_event_feedback.calendar_event_id', 'ce.id')
                  ->whereNull('deleted_at');
            })
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('command_tasks')
                  ->whereColumn('command_tasks.calendar_event_id', 'ce.id')
                  ->where('source_type', 'calendar:missed_feedback')
                  ->whereIn('status', [CommandTask::STATUS_TODO, CommandTask::STATUS_IN_PROGRESS, CommandTask::STATUS_AWAITING]);
            })
            ->pluck('ce.id');

        if ($eligibleEventIds->isEmpty() || $dry) {
            return $eligibleEventIds->count();
        }

        $created = 0;
        foreach ($eligibleEventIds as $eventId) {
            $evt = CalendarEvent::withoutGlobalScopes()->find($eventId);
            if (!$evt) {
                continue;
            }

            $contactCount = $evt->linkedContacts->count();
            $contactLabel = $contactCount === 1 ? 'contact' : 'contacts';

            CommandTask::create([
                'title'            => 'Capture feedback — ' . $evt->title,
                'description'      => "Feedback not yet captured for {$contactCount} {$contactLabel} from this event on " . $evt->event_date->format('j M, H:i') . '.',
                'task_type'        => 'feedback',
                'status'           => CommandTask::STATUS_TODO,
                'priority'         => 'normal',
                'due_date'         => now()->addDays(2),
                'assigned_to'      => $evt->user_id,
                'source_type'      => 'calendar:missed_feedback',
                'calendar_event_id' => $evt->id,
                'agency_id'        => $evt->agency_id,
                'branch_id'        => $evt->branch_id,
                'metadata'         => [
                    'calendar_event_id' => $evt->id,
                    'auto_created'      => true,
                    'created_via'       => 'reconcile_command',
                ],
            ]);

            CalendarEventAuditEntry::create([
                'calendar_event_id'    => $evt->id,
                'action'               => 'feedback_task_created',
                'new_values'           => ['reason' => 'feedback_missed_24h_grace'],
                'performed_by_user_id' => null,
                'performed_at'         => now(),
                'notes'                => 'Auto-task created by reconciliation command',
            ]);

            $created++;
        }

        return $created;
    }
}
