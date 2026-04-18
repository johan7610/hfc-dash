<?php

namespace App\Observers;

use App\Models\Agency;
use App\Models\CommandCenter\AgencyDashboardSetting;
use App\Models\CommandCenter\CommandTask;
use App\Models\CommandCenter\UserDashboardSetting;
use App\Models\User;

class CommandTaskObserver
{
    /**
     * When a task transitions into DONE, honour the owner's
     * auto_archive_done_days === 0 setting by soft-deleting it immediately.
     * The daily command handles N > 0.
     */
    public function updated(CommandTask $task): void
    {
        // Only act on a fresh transition into DONE
        if (!$task->wasChanged('status')) {
            return;
        }
        if ($task->status !== CommandTask::STATUS_DONE) {
            return;
        }
        if ($task->getOriginal('status') === CommandTask::STATUS_DONE) {
            return;
        }

        $user = User::find($task->assigned_to);
        if (!$user) {
            return;
        }

        if ($this->resolveAutoArchiveDays($user) === 0) {
            $task->delete();
        }
    }

    /**
     * Resolve auto_archive_done_days for a user.
     * Agency override wins when dashboard_settings_mode = 'agency'.
     * Returns null (never) or an integer.
     */
    protected function resolveAutoArchiveDays(User $user): ?int
    {
        $agencyId = $user->effectiveAgencyId();
        $agency   = $agencyId ? Agency::find($agencyId) : null;

        if ($agency && ($agency->dashboard_settings_mode ?? 'user') === 'agency') {
            $a = AgencyDashboardSetting::where('agency_id', $agency->id)->first();
            return $a?->auto_archive_done_days === null ? null : (int) $a->auto_archive_done_days;
        }

        $u = UserDashboardSetting::where('user_id', $user->id)->first();
        return $u?->auto_archive_done_days === null ? null : (int) $u->auto_archive_done_days;
    }
}
