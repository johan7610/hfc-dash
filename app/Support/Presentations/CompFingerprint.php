<?php

declare(strict_types=1);

namespace App\Support\Presentations;

/**
 * Build 8d — shared comp fingerprint for cross-source dedup.
 *
 * Three previously-private fingerprint helpers lived inside
 * CmaCoverageService (deal / MIC / legacy presentation_sold_comps).
 * Each used a source-prefixed key ('D|', 'M|', 'P|') so the badge could
 * distinguish what counted as what — with the unintended consequence
 * that the same sale appearing in BOTH a CMA source AND HFC's deal
 * register would be counted TWICE in the badge.
 *
 * Two flavours of key live here:
 *
 *   - sourceAgnosticKey(): same sale ⇒ same key regardless of source.
 *     Used by:
 *       * CmaCoverageService::countComps — distinct counting across sources
 *       * MicSnapshotHydrator::collectDealComps — CMA-wins precedence when
 *         injecting deals into the engine pool
 *
 *   - sourceTaggedKey(): the source-agnostic key prefixed with a 1-char
 *     source marker (D / M / P), for telemetry / debug where you want to
 *     know which source produced what. Dedup decisions use the agnostic
 *     form.
 *
 * Keying rule:
 *   sectional (has scheme + section) → strtoupper(scheme)|S{section}|date|price
 *   everything else                  → normaliseAddress(address)|date|price
 *
 * Address normalisation strips the trailing comma-suburb clause that
 * deal_register entries carry ("42 Smith St, Testville") but CMA Info
 * imports do not ("42 Smith St"). Without this, address-based dedup
 * never matches across the two sources in practice. Plus trim +
 * mb_strtolower. Price is coerced to int Rands. Date is the date
 * string verbatim.
 */
final class CompFingerprint
{
    public const SOURCE_DEAL    = 'D'; // deals table — HFC's own registered transactions
    public const SOURCE_MIC     = 'M'; // market_report_comp_rows — CMA Info imports
    public const SOURCE_PRES_SC = 'P'; // legacy presentation_sold_comps fallback

    /**
     * Source-agnostic key — same sale ⇒ same key regardless of source.
     */
    public static function sourceAgnosticKey(
        ?string $address,
        ?string $schemeName,
        ?string $sectionNumber,
        ?string $saleDate,
        int|float|string|null $salePrice,
    ): string {
        $price  = (int) ($salePrice ?? 0);
        $date   = (string) ($saleDate ?? '');
        $scheme = trim((string) $schemeName);
        $section = trim((string) $sectionNumber);

        if ($scheme !== '' && $section !== '') {
            return strtoupper($scheme) . '|S' . $section . '|' . $date . '|' . $price;
        }
        return self::normaliseAddress((string) $address) . '|' . $date . '|' . $price;
    }

    /**
     * Normalise an address for fingerprinting:
     *   - drop everything after the first comma (suburb suffix)
     *   - trim + mb_strtolower
     * Internal helper. Exposed for tests and for callers that need to
     * mirror this normalisation on lookup keys.
     */
    public static function normaliseAddress(string $address): string
    {
        $primary = explode(',', $address, 2)[0];
        return mb_strtolower(trim($primary));
    }

    /**
     * Source-tagged key — agnostic key with a 1-char source prefix.
     */
    public static function sourceTaggedKey(
        string $source,
        ?string $address,
        ?string $schemeName,
        ?string $sectionNumber,
        ?string $saleDate,
        int|float|string|null $salePrice,
    ): string {
        return $source . '|' . self::sourceAgnosticKey($address, $schemeName, $sectionNumber, $saleDate, $salePrice);
    }
}
