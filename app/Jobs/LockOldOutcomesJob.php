<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\Presentation\PresentationOutcomeLocked;
use App\Models\PresentationOutcome;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Phase 8 — daily auto-lock.
 *
 * Sets locked=true on outcomes recorded LOCK_AFTER_DAYS days ago that
 * aren't already locked. Silent to the agent (no notification) but the
 * PresentationOutcomeLocked domain event lands in the activity feed so
 * the audit trail is complete.
 *
 * Reason: once a stat is published (win rate by quarter, by agent, etc),
 * the underlying outcomes must stop shifting. 90 days is the cap.
 */
final class LockOldOutcomesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $cutoff = now()->subDays(PresentationOutcome::LOCK_AFTER_DAYS);

        PresentationOutcome::query()
            ->where('locked', false)
            ->where('recorded_at', '<=', $cutoff)
            ->chunkById(100, function ($outcomes) {
                foreach ($outcomes as $outcome) {
                    $outcome->forceFill([
                        'locked'    => true,
                        'locked_at' => now(),
                    ])->save();

                    try {
                        event(new PresentationOutcomeLocked(
                            presentationOutcomeId: (int) $outcome->id,
                            presentationId:        (int) $outcome->presentation_id,
                            agencyIdValue:         (int) $outcome->agency_id,
                        ));
                    } catch (\Throwable $e) {
                        Log::warning('outcome.lock.event_failed', [
                            'outcome_id' => $outcome->id,
                            'error'      => $e->getMessage(),
                        ]);
                    }
                }
            });
    }
}
