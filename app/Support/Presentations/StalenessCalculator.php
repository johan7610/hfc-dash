<?php

declare(strict_types=1);

namespace App\Support\Presentations;

use App\Models\Agency;
use App\Models\PresentationSnapshotLink;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/**
 * Phase 7 — derive a StalenessState for a snapshot link.
 *
 * Inputs:
 *   - link.created_at         → anchor for staleness window
 *   - link.expires_at         → hard cutoff (Expired)
 *   - link.revoked_at         → manual revoke (Revoked)
 *   - link.superseded_at      → auto-revoke via refresh (Revoked)
 *   - agency.presentation_staleness_days → window length (default 21)
 *
 * Order of checks (first match wins):
 *   1. revoked_at OR superseded_at → Revoked
 *   2. expires_at past             → Expired
 *   3. age >= staleness window     → Stale
 *   4. age >= half of window       → Aging
 *   5. otherwise                   → Fresh
 *
 * "Half of window" is a deliberate soft warning: by the time half the window
 * has elapsed the data is no longer brand-new but isn't yet at the point
 * where you'd actively ask the seller to request fresh comps. A subtle hint
 * here gives sellers psychological time to absorb that the snapshot ages.
 */
final class StalenessCalculator
{
    public const DEFAULT_STALENESS_DAYS = 21;

    public function classify(
        PresentationSnapshotLink $link,
        ?Agency $agency = null,
        ?CarbonImmutable $now = null,
    ): StalenessState {
        $now ??= CarbonImmutable::now();

        if ($link->revoked_at !== null || $link->superseded_at !== null) {
            return StalenessState::Revoked;
        }

        if ($link->expires_at !== null && Carbon::parse($link->expires_at)->lte($now)) {
            return StalenessState::Expired;
        }

        $windowDays = $this->resolveWindowDays($agency ?? $link->presentation?->agency);
        $created    = Carbon::parse($link->created_at);
        // Carbon's diffInDays sign semantics shifted between versions; use an
        // explicit timestamp delta so we don't depend on that quirk.
        $ageDays    = max(0.0, ($now->getTimestamp() - $created->getTimestamp()) / 86400.0);

        if ($ageDays >= $windowDays) {
            return StalenessState::Stale;
        }

        if ($ageDays >= ($windowDays / 2)) {
            return StalenessState::Aging;
        }

        return StalenessState::Fresh;
    }

    public function bannerMessage(StalenessState $state, int $windowDays): ?string
    {
        return match ($state) {
            StalenessState::Aging => sprintf(
                'This presentation was prepared more than %d days ago. Market data may have shifted since.',
                (int) floor($windowDays / 2),
            ),
            StalenessState::Stale => sprintf(
                'This presentation is over %d days old. Market conditions may have changed — request an updated version below.',
                $windowDays,
            ),
            StalenessState::Expired => 'This share link has expired. You can request an updated presentation from the agent.',
            StalenessState::Revoked => 'This share link is no longer active.',
            default                 => null,
        };
    }

    public function resolveWindowDays(?Agency $agency): int
    {
        $raw = $agency?->presentation_staleness_days;

        if (!is_numeric($raw)) {
            return self::DEFAULT_STALENESS_DAYS;
        }

        $days = (int) $raw;

        return max(7, min(90, $days));
    }
}
