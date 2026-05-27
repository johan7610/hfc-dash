<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\AI\AINarrativeCache;
use Illuminate\Support\Facades\Log;

/**
 * Targeted invalidation of `ai_narrative_cache` rows. Soft-deletes affected
 * rows so the next consumer regenerates from fresh inputs; the daily
 * SweepExpiredNarrativeCacheJob + weekly PurgeOldSoftDeletedCacheJob handle
 * lifecycle from there.
 *
 * Cache keys follow the deterministic convention documented in
 * .ai/specs/mic-complete-spec.md §4.8:
 *   weekly_brief:agency:{id}:week:{YYYY-WW}
 *   tile_copy:agent:{id}:tile:{slug}
 *   listing_tooltip:listing:{id}:variant:{variant}
 *   suburb_pocket:suburb:{id}:...
 *
 * This service is invoked from event listeners on domain events that materially
 * change the underlying inputs (tracked-property address verification, market
 * report parsing, claim lifecycle changes). Failures in invalidation MUST NOT
 * break the upstream event — listeners are expected to wrap calls in try/catch.
 *
 * Spec: .ai/specs/mic-complete-spec.md §4.8 (cache invalidation), Phase B2 brief.
 */
final class CacheInvalidator
{
    /**
     * Invalidate the current-week weekly brief for one agency. Optionally pass
     * a specific week (YYYY-WW) — defaults to the calling moment's ISO week.
     */
    public function invalidateWeeklyBriefForAgency(int $agencyId, ?string $weekYYYYWW = null): int
    {
        $prefix = $weekYYYYWW !== null
            ? "weekly_brief:agency:{$agencyId}:week:{$weekYYYYWW}"
            : "weekly_brief:agency:{$agencyId}:";
        return $this->invalidateForCacheKeyPrefix($prefix);
    }

    /**
     * Invalidate every tile-copy narrative scoped to one agent.
     */
    public function invalidateTileCopyForAgent(int $agentId, ?int $agencyId = null): int
    {
        $prefix = "tile_copy:agent:{$agentId}:";
        $count = $this->invalidateForCacheKeyPrefix($prefix);

        if ($agencyId !== null) {
            // Belt-and-braces: some tile_copy keys may be scoped agency-first.
            $count += $this->invalidateForCacheKeyPrefix("tile_copy:agency:{$agencyId}:agent:{$agentId}:");
        }
        return $count;
    }

    /**
     * Invalidate every listing-tooltip narrative for one listing.
     */
    public function invalidateListingTooltipsForListing(int $listingId): int
    {
        return $this->invalidateForCacheKeyPrefix("listing_tooltip:listing:{$listingId}:");
    }

    /**
     * Generic prefix-match invalidator. Returns the number of rows soft-deleted.
     */
    public function invalidateForCacheKeyPrefix(string $prefix): int
    {
        // Escape MySQL LIKE wildcards in user-supplied prefix segments so the
        // match stays anchored. The keys we build internally don't contain
        // % / _ but defensive escaping costs nothing.
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $prefix);

        $rows = AINarrativeCache::query()
            ->where('cache_key', 'like', $escaped . '%')
            ->whereNull('deleted_at')
            ->get(['id', 'cache_key']);

        if ($rows->isEmpty()) return 0;

        $count = AINarrativeCache::query()
            ->whereIn('id', $rows->pluck('id'))
            ->update(['deleted_at' => now()]);

        Log::info('AI cache invalidation', [
            'prefix'        => $prefix,
            'soft_deleted'  => $count,
        ]);
        return (int) $count;
    }

    /**
     * Nuclear option — soft-delete every active cache row. Reserved for
     * pricing changes / prompt-version bumps where every narrative needs
     * regeneration. Use sparingly.
     */
    public function invalidateAll(): int
    {
        $now = now();
        $count = AINarrativeCache::query()
            ->whereNull('deleted_at')
            ->update(['deleted_at' => $now]);

        Log::warning('AI cache fully invalidated', [
            'soft_deleted' => $count,
            'at'           => $now->toIso8601String(),
        ]);
        return (int) $count;
    }
}
