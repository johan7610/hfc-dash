<?php

declare(strict_types=1);

namespace App\Services\Map;

/**
 * Phase 3g B1 — bounding-box + filter spec for MapPinService.
 *
 * Constructed from an HTTP request by MapController. Validation lives in
 * the controller's form-request layer; this DTO assumes valid input.
 */
final class MapBoundsRequest
{
    /** Hard cap so a malformed request can't ask for 1m pins. */
    public const MAX_LIMIT = 5000;

    /** @var string[] */
    public const VALID_LAYERS = [
        'hfc_listings', 'sold_comps', 'active_listings', 'mic_subjects', 'scheme_owners',
    ];

    public function __construct(
        public readonly float  $north,
        public readonly float  $south,
        public readonly float  $east,
        public readonly float  $west,
        /** @var string[] */
        public readonly array  $layers,
        public readonly string $viewMode,
        public readonly int    $agencyId,
        public readonly ?string $dateFrom = null,
        public readonly ?string $dateTo = null,
        /** @var string[] */
        public readonly array  $propertyTypes = [],
        public readonly ?int   $priceMin = null,
        public readonly ?int   $priceMax = null,
        public readonly int    $limit = 2000,
        // Phase 3h Step 9.5 — when false, hide is_demo=true rows from
        // the map. Defaults to true so demo pins are visible until
        // someone explicitly toggles them off (the left-rail switch
        // wires this up from localStorage).
        public readonly bool   $includeDemo = true,
        // Phase 3g V2 — extended filter fields. All optional, defaults
        // preserve V1 behaviour (no filtering when null).
        public readonly ?int   $dateFromYear = null,
        public readonly ?int   $dateToYear   = null,
        /** @var int[] */
        public readonly array  $bedrooms     = [],
        // Phase 3g V2 Part E — radius post-filter for the embedded views.
        // When all 3 set, pins outside Haversine(center, pin) <= radiusM
        // are dropped after the bounding-box pre-filter. The standalone
        // map doesn't pass these, so V1 behaviour is unchanged.
        public readonly ?float $radiusCenterLat = null,
        public readonly ?float $radiusCenterLng = null,
        public readonly ?int   $radiusM         = null,
    ) {}

    public function hasRadiusFilter(): bool
    {
        return $this->radiusCenterLat !== null
            && $this->radiusCenterLng !== null
            && $this->radiusM !== null;
    }

    public function isSellerView(): bool
    {
        return $this->viewMode === 'seller';
    }

    public function wantsLayer(string $key): bool
    {
        return in_array($key, $this->layers, true);
    }

    public function effectiveLimit(): int
    {
        return min(max(1, $this->limit), self::MAX_LIMIT);
    }
}
