<?php

declare(strict_types=1);

namespace App\Support\Presentations;

/**
 * Comparable-sale display-label builder.
 *
 * Pre-fix: review screen + PDF + map tooltips read $raw['address']
 * directly. When sectional CMA imports landed with scheme+section but
 * no street address (a common pattern — the PDF table lists scheme as
 * a column header rather than per-row), every consumer rendered "—",
 * leaving agents and sellers unable to identify which property was
 * being compared.
 *
 * Single source of truth for the identifier label. Falls through a
 * 5-step chain so the label is NEVER blank:
 *
 *   1. raw.address (non-empty)                — street address
 *   2. raw.scheme_name + Section <section>    — sectional ("Seeskulp, Section 8")
 *   3. raw.section_number + suburb            — bare section ("Section 8, Uvongo")
 *   4. suburb                                 — fallback to locality
 *   5. "Comp #<id>"                           — absolute floor
 *
 * Section number may appear under either of two keys in raw_row_json
 * depending on the parser:
 *   - section_number  (MicSnapshotHydrator / CmaInfoVicinitySaleParser)
 *   - section_no      (doc_extract_v1 family)
 * Both are checked.
 *
 * Used by AnalysisDataService (review screen + PDF tables — single
 * call inside compileData()) and by PresentationPdfService's map-
 * tooltip loop (one call per row at the SVG marker title site).
 */
final class CompLabel
{
    /**
     * Build the display label for a comparable sale.
     *
     * @param  array<string, mixed>|null  $raw     decoded raw_row_json
     * @param  ?string                    $suburb  presentation_sold_comps.suburb (last-resort locality)
     * @param  int|string|null            $id      comp row id for the floor case
     */
    public static function build(?array $raw, ?string $suburb = null, int|string|null $id = null): string
    {
        $raw = $raw ?? [];

        $address = isset($raw['address']) ? trim((string) $raw['address']) : '';
        if ($address !== '') {
            return $address;
        }

        $section = self::sectionToken($raw);
        $scheme  = isset($raw['scheme_name']) ? trim((string) $raw['scheme_name']) : '';
        if ($scheme !== '') {
            return $section !== '' ? "{$scheme}, Section {$section}" : $scheme;
        }

        $suburbStr = is_string($suburb) ? trim($suburb) : '';
        if ($section !== '') {
            return $suburbStr !== '' ? "Section {$section}, {$suburbStr}" : "Section {$section}";
        }

        if ($suburbStr !== '') {
            return $suburbStr;
        }

        return $id !== null ? "Comp #{$id}" : 'Unidentified comp';
    }

    /**
     * Extract section identifier from either of the known raw_row_json
     * key spellings. Empty string when neither carries a value.
     */
    private static function sectionToken(array $raw): string
    {
        foreach (['section_number', 'section_no'] as $k) {
            if (isset($raw[$k]) && trim((string) $raw[$k]) !== '') {
                return trim((string) $raw[$k]);
            }
        }
        return '';
    }
}
