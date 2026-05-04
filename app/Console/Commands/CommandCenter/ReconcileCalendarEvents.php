<?php

namespace App\Console\Commands\CommandCenter;

use App\Models\CommandCenter\CalendarEvent;
use App\Services\CommandCenter\Calendar\CalendarNotificationDispatcher;
use App\Services\CommandCenter\Calendar\CalendarSourceRegistry;
use App\Services\CommandCenter\Calendar\CalendarThresholdResolver;
use Carbon\Carbon;
use Illuminate\Console\Command;
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
        if (!$event->event_date) {
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
}
