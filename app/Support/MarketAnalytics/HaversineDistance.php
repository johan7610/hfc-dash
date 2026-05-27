<?php

declare(strict_types=1);

namespace App\Support\MarketAnalytics;

/**
 * Phase 3b — Haversine great-circle distance helper.
 *
 * Pure function. Returns integer metres between two WGS-84 coordinate pairs.
 * Earth radius constant 6 371 000 m (mean radius). Sufficient for the
 * sub-5km comparable-property matching this codebase needs; not suitable
 * for navigation or geodesic measurement at >100km scale.
 */
final class HaversineDistance
{
    /**
     * Mean Earth radius in metres (WGS-84 mean = 6 371 008 m; rounded to
     * 6 371 000 for code-readability; difference is sub-pixel for our use).
     */
    public const EARTH_RADIUS_M = 6371000;

    /**
     * Compute the great-circle distance between two coordinates, in metres,
     * rounded to the nearest integer.
     */
    public static function distanceMetres(float $lat1, float $lng1, float $lat2, float $lng2): int
    {
        $rLat1 = deg2rad($lat1);
        $rLat2 = deg2rad($lat2);
        $dLat  = deg2rad($lat2 - $lat1);
        $dLng  = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2)
           + cos($rLat1) * cos($rLat2) * sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return (int) round(self::EARTH_RADIUS_M * $c);
    }
}
