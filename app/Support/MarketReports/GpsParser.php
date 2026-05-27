<?php

declare(strict_types=1);

namespace App\Support\MarketReports;

/**
 * Parses GPS coordinates from CMA Info report text.
 *
 * CMA Info renders coordinates as `30.380705°E 30.844135°S` (the order between
 * E/W and N/S is consistent in the source PDF, but the helper is tolerant of
 * either ordering and of garbled degree symbols from pdftotext on Windows
 * (`�` instead of `°`).
 *
 * `°E` / `°W` annotates longitude; `°S` / `°N` annotates latitude. Southern-
 * hemisphere coordinates are returned as negative latitude; western hemisphere
 * as negative longitude.
 */
final class GpsParser
{
    /**
     * Parse a GPS string.
     *
     * @return array{lat: float, lng: float}|null  null when no valid pair found.
     */
    public static function fromString(?string $raw): ?array
    {
        if ($raw === null) return null;
        $raw = trim($raw);
        if ($raw === '') return null;

        // Find every "<decimal><deg-symbol><E|W|N|S>" token. Degree symbol may
        // be `°`, `\xC2\xB0`, `�`, or even missing entirely.
        $pattern = '/(?<num>-?\d{1,3}(?:\.\d{1,8})?)\s*(?:°|\x{00B0}|\xEF\xBF\xBD|\?|�)?\s*(?<dir>[EWNS])\b/iu';

        if (!preg_match_all($pattern, $raw, $matches, PREG_SET_ORDER)) {
            return null;
        }

        $lat = null;
        $lng = null;

        foreach ($matches as $m) {
            $val = (float) $m['num'];
            $dir = strtoupper($m['dir']);

            if ($dir === 'E') {
                $lng = abs($val);
            } elseif ($dir === 'W') {
                $lng = -abs($val);
            } elseif ($dir === 'S') {
                $lat = -abs($val);
            } elseif ($dir === 'N') {
                $lat = abs($val);
            }
        }

        if ($lat === null || $lng === null) return null;
        if (abs($lat) > 90 || abs($lng) > 180) return null;

        return ['lat' => $lat, 'lng' => $lng];
    }
}
