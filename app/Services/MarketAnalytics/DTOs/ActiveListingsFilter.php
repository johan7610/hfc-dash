<?php

namespace App\Services\MarketAnalytics\DTOs;

class ActiveListingsFilter
{
    public const SCOPE_RADIUS_ALL  = 'radius_all';
    public const SCOPE_SUBURB_ONLY = 'suburb_only';

    public function __construct(
        public readonly string  $suburbSlug,
        public readonly string  $propertyType,
        public readonly string  $asAtDate,    // YYYY-MM-DD snapshot date
        public readonly ?int    $bedrooms    = null,
        public readonly ?int    $branchId    = null,
        // Phase 3b additions — backward-compatible.
        public readonly string  $compScope        = self::SCOPE_SUBURB_ONLY,
        public readonly int     $compRadiusM      = 1000,
        public readonly ?float  $subjectLatitude  = null,
        public readonly ?float  $subjectLongitude = null,
    ) {}
}
