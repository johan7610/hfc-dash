<?php

declare(strict_types=1);

namespace App\Services\Geocoding;

/**
 * Phase 3f A3 — value object returned by AddressResolverService::resolve.
 *
 * Confidence levels (highest first):
 *   exact     — building/rooftop precision (MIC subject_address match, Google ROOFTOP)
 *   street    — street-level (Google GEOMETRIC_CENTER, RANGE_INTERPOLATED)
 *   suburb    — suburb centroid (Google APPROXIMATE, suburb-only fallback)
 *   town      — town centroid (very coarse, last-resort)
 *   failed    — no GPS could be derived
 */
final class GeocodingResult
{
    public function __construct(
        public readonly ?float  $latitude,
        public readonly ?float  $longitude,
        public readonly string  $confidence,
        public readonly string  $source,
        public readonly ?string $municipality = null,
        public readonly bool    $cached = false,
        public readonly ?string $failureReason = null,
        public readonly ?string $resolvedAddress = null,
    ) {}

    public function hasGps(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function failed(): bool
    {
        return $this->confidence === 'failed' || !$this->hasGps();
    }

    public function toArray(): array
    {
        return [
            'latitude'         => $this->latitude,
            'longitude'        => $this->longitude,
            'confidence'       => $this->confidence,
            'source'           => $this->source,
            'municipality'     => $this->municipality,
            'cached'           => $this->cached,
            'failure_reason'   => $this->failureReason,
            'resolved_address' => $this->resolvedAddress,
        ];
    }
}
