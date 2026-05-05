<?php

namespace App\Services\CommandCenter\Calendar;

use App\Models\CommandCenter\CalendarEvent;
use App\Models\CommandCenter\CalendarEventClassSetting;
use App\Models\User;

class CalendarVisibilityResolver
{
    public function __construct(
        private CalendarThresholdResolver $thresholdResolver,
    ) {}

    /**
     * Determine if a user can see an event on their calendar.
     *
     * Rules:
     *  1. Cross-agency events are never visible (agency isolation).
     *  2. Super_admin bypasses all further checks.
     *  3. Admin/owner within the event's agency bypasses class visibility.
     *  4. Event creator always sees their own events.
     *  5. Event must currently resolve to a colour (within show window + active).
     *  6. User's role must appear in that colour's visibility list.
     */
    public function canSee(CalendarEvent $event, User $user): bool
    {
        // Agency isolation guard.
        $userAgencyId = $user->effectiveAgencyId();
        if ($userAgencyId !== null && $event->agency_id !== null && (int) $event->agency_id !== (int) $userAgencyId) {
            return false;
        }

        // Super_admin sees everything (cross-agency allowed when effectiveAgencyId is null).
        if ($user->role === 'super_admin') {
            return true;
        }

        // Admin/owner within their own agency sees all events in that agency.
        if (in_array($user->role, ['admin', 'owner'], true) && (int) ($user->agency_id ?? 0) === (int) ($event->agency_id ?? 0)) {
            return true;
        }

        // Event creator always sees their own events.
        if ($event->user_id !== null && (int) $event->user_id === (int) $user->id) {
            return true;
        }

        $colour = $this->thresholdResolver->resolveForEvent($event);
        if ($colour === null) {
            return false;
        }

        $config = CalendarEventClassSetting::forAgencyAndClass($event->agency_id, $event->category ?? '');
        if (!$config) {
            return false;
        }

        $visibleRoles = $config->visibilityFor($colour);
        return $this->userMatchesAnyRole($user, $visibleRoles);
    }

    /**
     * Filter a collection of events down to only those the user may see.
     */
    public function filterVisible(iterable $events, User $user): array
    {
        $visible = [];
        foreach ($events as $event) {
            if ($this->canSee($event, $user)) {
                $visible[] = $event;
            }
        }
        return $visible;
    }

    /**
     * Check if a user's role matches any in the role list.
     *
     * Role widening:
     *  - 'bm' in config matches 'branch_manager' in DB
     *  - 'admin' in config matches 'admin', 'super_admin', 'owner' in DB
     *  - 'all' matches everyone
     */
    private function userMatchesAnyRole(User $user, array $roles): bool
    {
        if (empty($roles)) {
            return false;
        }

        if (in_array('all', $roles, true)) {
            return true;
        }

        $userRole = $user->role ?? null;
        if (!$userRole) {
            return false;
        }

        // Direct match.
        if (in_array($userRole, $roles, true)) {
            return true;
        }

        // Widen: 'bm' in config matches 'branch_manager' in DB.
        if (in_array('bm', $roles, true) && $userRole === 'branch_manager') {
            return true;
        }

        // Widen: owner / super_admin sees admin-level events.
        if (in_array('admin', $roles, true) && in_array($userRole, ['owner', 'super_admin'], true)) {
            return true;
        }

        return false;
    }
}
