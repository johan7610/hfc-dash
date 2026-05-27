<?php

declare(strict_types=1);

namespace App\Jobs\Prospecting;

use App\Events\Prospecting\ClaimFlaggedAsStale;
use App\Models\ProspectingClaim;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * MIC Phase G1 — hourly sweep that flags claims as stale when the agent
 * has not logged feedback within the configured window (default 48h).
 *
 * Sets flagged_at = now() so the row surfaces on the BM Team Dashboard
 * (Phase G2) and fires ClaimFlaggedAsStale so LogAgentActivity records the
 * detection — useful for performance reviews and the "stale flagged" tile
 * on the team page.
 *
 * Idempotent — claims that already carry a flagged_at value are skipped
 * by the WHERE clause, so re-runs are no-ops.
 *
 * Spec: .ai/specs/mic-complete-spec.md §10.1.
 */
class FlagStaleClaimsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $threshold = (int) config('mic.claim_stale_hours', 48);
        $cutoff = now()->subHours($threshold);
        $count = 0;
        $errors = 0;

        ProspectingClaim::query()
            ->where('is_active', true)
            ->whereNull('feedback_at')
            ->whereNull('flagged_at')
            ->whereNotIn('status', ['lost', 'not_interested', 'listing'])
            ->where('claimed_at', '<', $cutoff)
            ->chunkById(100, function ($claims) use (&$count, &$errors, $threshold) {
                foreach ($claims as $claim) {
                    try {
                        $claim->update(['flagged_at' => now()]);
                        event(new ClaimFlaggedAsStale(
                            claim: $claim,
                            staleHours: $threshold,
                            detectionReason: 'hourly_sweep:no_feedback_within_window',
                        ));
                        $count++;
                    } catch (Throwable $e) {
                        $errors++;
                        Log::warning('FlagStaleClaimsJob: claim flag failed', [
                            'claim_id' => $claim->id,
                            'error'    => $e->getMessage(),
                        ]);
                    }
                }
            });

        Log::info('FlagStaleClaimsJob complete', [
            'flagged'         => $count,
            'errors'          => $errors,
            'threshold_hours' => $threshold,
        ]);
    }
}
