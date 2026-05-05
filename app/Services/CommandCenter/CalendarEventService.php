<?php

namespace App\Services\CommandCenter;

use App\Models\CommandCenter\CalendarEvent;
use App\Models\User;
use Illuminate\Support\Collection;

class CalendarEventService
{
    /**
     * Create a manual calendar event.
     */
    public function createManual(array $data, User $user): CalendarEvent
    {
        return CalendarEvent::create(array_merge($data, [
            'user_id'       => $data['user_id'] ?? $user->id,
            'created_by_id' => $user->id,
            'event_type'    => $data['event_type'] ?? 'manual',
            'status'        => 'pending',
            'colour'        => $data['colour'] ?? null,
        ]));
    }

    /**
     * Create an auto-generated event from a source model.
     */
    public function createFromSource(
        string $eventType,
        string $category,
        string $title,
        \DateTime $eventDate,
        $source,
        array $extra = []
    ): CalendarEvent {
        return CalendarEvent::create(array_merge([
            'event_type'  => $eventType,
            'category'    => $category,
            'title'       => $title,
            'event_date'  => $eventDate,
            'source_type' => get_class($source),
            'source_id'   => $source->getKey(),
            'status'      => 'pending',
        ], $extra));
    }

    /**
     * Get events for a user in a date range.
     *
     * Scope controls the user filter:
     *   'own'    — only events assigned to this user (user_id = $user->id)
     *   'branch' — events in the user's branch (downstream VisibilityResolver handles per-event checks)
     *   'all'    — no user filter (downstream VisibilityResolver handles per-event checks)
     */
    public function getEventsForRange(User $user, string $start, string $end, array $filters = [], string $scope = 'all'): Collection
    {
        $query = CalendarEvent::query()->inDateRange($start, $end);

        if ($scope === 'own') {
            $query->forUser($user->id);
        } elseif ($scope === 'branch' && $user->branch_id) {
            $query->where('branch_id', $user->branch_id);
        }
        // scope 'all' — no user/branch filter; VisibilityResolver handles access

        if (!empty($filters['event_type'])) {
            $query->ofType($filters['event_type']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['property_id'])) {
            $query->where('property_id', $filters['property_id']);
        }

        return $query->orderBy('event_date')->get();
    }

    /**
     * Get today's events for a user.
     */
    public function getTodayEvents(User $user, int $limit = 10): Collection
    {
        return CalendarEvent::forUser($user->id)
            ->today()
            ->orderBy('all_day', 'desc')
            ->orderBy('event_date')
            ->limit($limit)
            ->get();
    }

    /**
     * Get overdue events for a user.
     */
    public function getOverdueEvents(User $user, int $limit = 10): Collection
    {
        return CalendarEvent::forUser($user->id)
            ->overdue()
            ->orderBy('event_date')
            ->limit($limit)
            ->get();
    }

    /**
     * Count events by category for this week.
     */
    public function getWeekSummary(User $user): array
    {
        $events = CalendarEvent::forUser($user->id)
            ->thisWeek()
            ->where('status', 'pending')
            ->get();

        return [
            'total'  => $events->count(),
            'byType' => $events->groupBy('event_type')->map->count()->toArray(),
        ];
    }

    /**
     * Update an event.
     */
    public function update(CalendarEvent $event, array $data): CalendarEvent
    {
        $event->update($data);
        return $event->fresh();
    }

    /**
     * Soft-delete an event.
     */
    public function delete(CalendarEvent $event): void
    {
        $event->delete();
    }

    /**
     * Remove all auto-generated events for a source model.
     */
    public function deleteForSource($source): void
    {
        CalendarEvent::where('source_type', get_class($source))
            ->where('source_id', $source->getKey())
            ->delete();
    }

    /**
     * Get events for a month calendar grid (includes surrounding weeks).
     */
    public function getMonthGrid(User $user, int $year, int $month, array $filters = [], string $scope = 'all'): array
    {
        $start = \Carbon\Carbon::create($year, $month, 1)->startOfWeek();
        $end   = \Carbon\Carbon::create($year, $month, 1)->endOfMonth()->endOfWeek();

        $events = $this->getEventsForRange($user, $start, $end, $filters, $scope);

        // Group by date
        $grouped = [];
        foreach ($events as $event) {
            $dateKey = $event->event_date->toDateString();
            $grouped[$dateKey][] = $event;
        }

        return [
            'start'    => $start,
            'end'      => $end,
            'month'    => $month,
            'year'     => $year,
            'events'   => $events,
            'byDate'   => $grouped,
        ];
    }
}
