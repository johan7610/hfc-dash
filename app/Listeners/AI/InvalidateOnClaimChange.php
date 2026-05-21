<?php

declare(strict_types=1);

namespace App\Listeners\AI;

use App\Events\Prospecting\ClaimAutoReleased;
use App\Events\Prospecting\ClaimCreated;
use App\Events\Prospecting\ClaimReleased;
use App\Services\AI\CacheInvalidator;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * MIC Phase B2 — claim lifecycle changes (create / release / auto-release)
 * invalidate the agency's weekly brief and the affected agent's tile-copy
 * narratives, since both surfaces summarise current claim state.
 *
 * One listener handles all three event types — the constructor signature is
 * a union but they all expose `$claim->agency_id` and `$claim->user_id`.
 *
 * Failure-isolated.
 *
 * Spec: .ai/specs/mic-complete-spec.md §4.8.
 */
final class InvalidateOnClaimChange
{
    public function __construct(private readonly CacheInvalidator $invalidator) {}

    public function handle(ClaimCreated|ClaimReleased|ClaimAutoReleased $event): void
    {
        try {
            $claim    = $event->claim;
            $agencyId = (int) ($claim->agency_id ?? 0);
            $userId   = (int) ($claim->user_id ?? 0);

            if ($agencyId > 0) {
                $this->invalidator->invalidateWeeklyBriefForAgency($agencyId);
            }
            if ($userId > 0) {
                $this->invalidator->invalidateTileCopyForAgent($userId, $agencyId > 0 ? $agencyId : null);
            }
        } catch (Throwable $e) {
            Log::warning('InvalidateOnClaimChange failed', [
                'event'    => $event::class,
                'claim_id' => $event->claim->id ?? null,
                'error'    => $e->getMessage(),
            ]);
        }
    }
}
