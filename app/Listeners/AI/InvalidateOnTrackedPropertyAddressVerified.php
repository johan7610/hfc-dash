<?php

declare(strict_types=1);

namespace App\Listeners\AI;

use App\Events\Prospecting\TrackedPropertyAddressVerified;
use App\Services\AI\CacheInvalidator;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * MIC Phase B2 — when a tracked-property address is verified, the agency's
 * weekly brief is now stale (verified address counts + GPS quality feed it).
 *
 * Failure-isolated: any exception is logged and swallowed so the upstream
 * domain event (RecordDomainEvent, LogAgentActivity) is unaffected.
 *
 * Spec: .ai/specs/mic-complete-spec.md §4.8.
 */
final class InvalidateOnTrackedPropertyAddressVerified
{
    public function __construct(private readonly CacheInvalidator $invalidator) {}

    public function handle(TrackedPropertyAddressVerified $event): void
    {
        try {
            $agencyId = (int) ($event->address->agency_id ?? 0);
            if ($agencyId <= 0) return;
            $this->invalidator->invalidateWeeklyBriefForAgency($agencyId);
        } catch (Throwable $e) {
            Log::warning('InvalidateOnTrackedPropertyAddressVerified failed', [
                'address_id' => $event->address->id ?? null,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
