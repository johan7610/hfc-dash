<?php

declare(strict_types=1);

namespace App\Jobs\AI;

use App\Models\AI\AINarrativeCache;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * MIC Phase B2 — weekly hard-delete of cache rows that have been soft-deleted
 * for more than 90 days.
 *
 * DOCUMENTED EXCEPTION to CLAUDE.md non-negotiable #1 ("No hard deletes. Ever.")
 * Rationale: ai_narrative_cache rows are derived intermediate output, not
 * audit/business data. SweepExpiredNarrativeCacheJob already soft-deletes them
 * at expiry; this job removes the long-since-stale residue so the table does
 * not grow without bound. There is no user-facing "restore" path for cache
 * rows older than 90 days — by then the underlying inputs (mandate counts,
 * weekly metrics, comp sets) have shifted enough that a regeneration would
 * produce different output anyway. Source-of-truth audit lives in
 * `agent_activity_events` (AINarrativeGenerated rows), which is untouched.
 *
 * Scheduled weekly via routes/console.php.
 *
 * Spec: .ai/specs/mic-complete-spec.md §4.8 (cache hygiene), Phase B2 brief.
 */
class PurgeOldSoftDeletedCacheJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const RETENTION_DAYS = 90;

    public function handle(): void
    {
        $cutoff = now()->subDays(self::RETENTION_DAYS);

        $count = AINarrativeCache::query()
            ->withTrashed()
            ->whereNotNull('deleted_at')
            ->where('deleted_at', '<=', $cutoff)
            ->forceDelete();

        Log::info('PurgeOldSoftDeletedCacheJob complete', [
            'hard_deleted'   => $count,
            'cutoff'         => $cutoff->toIso8601String(),
            'retention_days' => self::RETENTION_DAYS,
        ]);
    }
}
