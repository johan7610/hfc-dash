<?php

declare(strict_types=1);

namespace App\Support\Geocoding;

/**
 * Phase 3f A2 — pure address normalisation.
 *
 * Produces a stable canonical string from a free-form South African address.
 * Used as the cache key for the geocoder waterfall — every input that should
 * resolve to the same physical location must hash to the same normalised
 * string.
 *
 * Rules (applied in order):
 *   1. Lowercase + trim
 *   2. Strip sectional-title unit prefixes that aren't part of the address:
 *      "Ss Madeira Gardens", "Section 4", "Section No 4", "Unit 12",
 *      "Flat 7", "Apt 3" — these are unit identifiers, not street parts.
 *   3. Strip punctuation (commas, full stops, semicolons) — replace with single space.
 *   4. Collapse repeated whitespace.
 *   5. Append suburb when not already present at any position.
 *   6. Append town when not already present.
 *   7. Drop the trailing ", South Africa" / "ZA" tokens — those are added
 *      by the geocoder branch, not the cache key.
 *
 * Idempotent: normalise(normalise(x)) === normalise(x).
 *
 * Examples:
 *   "4 Ss Madeira Gardens, 4 Tucker Avenue", "Uvongo"     → "4 tucker avenue uvongo"
 *   "  4 TUCKER AVE,  Uvongo   ", null                    → "4 tucker ave uvongo"
 *   "Erf 1234, Margate", null                             → "erf 1234 margate"
 *   "Unit 12, 8 Beach Rd", "Shelly Beach"                 → "8 beach rd shelly beach"
 *   "4 Tucker Avenue, Uvongo", "Uvongo", "Ray Nkonyeni"   → "4 tucker avenue uvongo ray nkonyeni"
 *
 * NOT a replacement for full SA address standardisation — there's no street-type
 * canonicalisation ("Ave" vs "Avenue"), no suffix normalisation. Those would
 * cause false collisions where two genuinely different addresses share a
 * normalised key. We accept some duplicates in cache in exchange for safety.
 */
final class AddressNormaliser
{
    /** Strip these unit-prefix tokens before joining. */
    private const UNIT_PREFIX_PATTERNS = [
        // "4 Ss Madeira Gardens," — anchored at string start so we don't
        // accidentally eat addresses like "Tucker Avenue, Ss Madeira Gardens"
        // where Ss is mid-sentence. The leading digit (unit number) is also
        // consumed because it identifies the unit, not the street.
        '/^\s*\d{1,4}\s+ss\s+[a-z][a-z \-\']+,\s*/iu',
        // Same again without leading number, mid-string.
        '/\bss\s+[a-z][a-z \-\']+,\s*/iu',
        '/\bsection(?:\s+no)?\s*\d+\s*,?\s*/iu',     // "Section 4" / "Section No 4"
        '/\bunit\s+\w{1,8}\s*,?\s*/iu',              // "Unit 12"
        '/\bflat\s+\w{1,8}\s*,?\s*/iu',              // "Flat 7"
        '/\bapt\s+\w{1,8}\s*,?\s*/iu',               // "Apt 3"
    ];

    /** Trailing tokens we drop because they're added downstream. */
    private const TRAILING_DROP = [
        ', south africa', ' south africa', ', za', ' za',
    ];

    public static function normalise(string $raw, ?string $suburb = null, ?string $town = null): string
    {
        $work = mb_strtolower(trim($raw));
        if ($work === '') {
            $work = '';
        }

        // Step 2 — strip unit prefixes.
        foreach (self::UNIT_PREFIX_PATTERNS as $p) {
            $work = preg_replace($p, ' ', $work) ?? $work;
        }

        // Step 7 (early) — drop trailing country tokens so they don't get re-added.
        foreach (self::TRAILING_DROP as $tail) {
            if (str_ends_with($work, $tail)) {
                $work = substr($work, 0, -strlen($tail));
            }
        }

        // Step 3 — replace punctuation with single space.
        $work = preg_replace('/[,;]+/u', ' ', $work) ?? $work;
        $work = preg_replace('/\.+/u', ' ', $work) ?? $work;

        // Step 4 — collapse whitespace.
        $work = preg_replace('/\s+/u', ' ', $work) ?? $work;
        $work = trim($work);

        // Step 5 — append suburb if not already present.
        $suburbLower = $suburb !== null ? mb_strtolower(trim($suburb)) : null;
        if ($suburbLower !== null && $suburbLower !== '' && !self::tokenAppearsIn($work, $suburbLower)) {
            $work = trim($work . ' ' . $suburbLower);
        }

        // Step 6 — append town if not already present.
        $townLower = $town !== null ? mb_strtolower(trim($town)) : null;
        if ($townLower !== null && $townLower !== '' && !self::tokenAppearsIn($work, $townLower)) {
            $work = trim($work . ' ' . $townLower);
        }

        // Final whitespace pass — appends may have introduced doubles.
        $work = preg_replace('/\s+/u', ' ', $work) ?? $work;

        return trim($work);
    }

    /**
     * True when $needle appears in $haystack as a whitespace-bounded token,
     * not as a substring of another word. Multi-word needles match if their
     * full sequence is present.
     */
    private static function tokenAppearsIn(string $haystack, string $needle): bool
    {
        $needle = trim($needle);
        if ($needle === '') return true;
        $hay = ' ' . $haystack . ' ';
        $ndl = ' ' . $needle . ' ';
        return mb_strpos($hay, $ndl) !== false;
    }
}
