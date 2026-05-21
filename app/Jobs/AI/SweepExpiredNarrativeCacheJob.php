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
 * MIC Phase B2 — daily sweep of expired narrative cache rows.
 *
 * Soft-deletes every `ai_narrative_cache` row whose `expires_at` is in the
 * past. The unique index on (cache_key, deleted_at) ensures soft-deleted
 * rows do not block fresh cache writes for the same key.
 *
 * Scheduled daily at 03:00 Africa/Johannesburg via routes/console.php.
 * Hard removal is handled separately by PurgeOldSoftDeletedCacheJob after
 * the 90-day retention window.
 *
 * Spec: .ai/specs/mic-complete-spec.md §4.8 (cache hygiene), Phase B2 brief.
 */
class SweepExpiredNarrativeCacheJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $now = now();

        $count = AINarrativeCache::query()
            ->where('expires_at', '<=', $now)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => $now]);

        Log::info('SweepExpiredNarrativeCacheJob complete', [
            'soft_deleted' => $count,
            'cutoff'       => $now->toIso8601String(),
        ]);
    }
}
