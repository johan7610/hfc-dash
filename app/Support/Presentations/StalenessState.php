<?php

declare(strict_types=1);

namespace App\Support\Presentations;

/**
 * Phase 7 — coarse classification of a snapshot link's freshness, used to
 * pick which banner/page variant the public viewer renders.
 *
 *   fresh     — within staleness window, no banner
 *   aging     — ≥ 50% of staleness window elapsed, soft "data may be dated" hint
 *   stale     — at/past staleness window but not yet expired, prominent banner
 *   expired   — past expires_at; show full-page "request refresh" gate
 *   revoked   — revoked_at set OR superseded_at set; viewer redirects/blocks
 */
enum StalenessState: string
{
    case Fresh   = 'fresh';
    case Aging   = 'aging';
    case Stale   = 'stale';
    case Expired = 'expired';
    case Revoked = 'revoked';

    public function isBlocking(): bool
    {
        return $this === self::Expired || $this === self::Revoked;
    }

    public function showsBanner(): bool
    {
        return $this === self::Aging || $this === self::Stale;
    }

    public function label(): string
    {
        return match ($this) {
            self::Fresh   => 'Fresh',
            self::Aging   => 'Slightly dated',
            self::Stale   => 'Data may be dated',
            self::Expired => 'Expired',
            self::Revoked => 'Revoked',
        };
    }
}
