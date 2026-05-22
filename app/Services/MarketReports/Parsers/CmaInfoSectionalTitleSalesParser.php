<?php

declare(strict_types=1);

namespace App\Services\MarketReports\Parsers;

use App\Models\MarketReports\MarketReport;
use App\Models\MarketReports\MarketReportCompRow;
use App\Services\MarketReports\DTOs\MarketReportParseResult;
use App\Services\MarketReports\DTOs\ParserConfidence;

/**
 * V2 parser for CMA Info "Sectional Title Sales" — covers BOTH the in-scheme
 * variant ("Sectional Title sales in. MADEIRA GARDENS") and the radius variant
 * ("Sectional Title sales within. 500m"). Differs by header text only; the
 * data-row table layout is identical.
 *
 * Phase 3a additions:
 *   - per-row comp persistence to market_report_comp_rows
 *   - radius detection ("within. 500m" / "within 300 m") → subject_meta.radius_metres
 *   - subject scheme name from page-1 header
 *
 * Spec: .ai/specs/mic-complete-spec.md §8.3 + Phase 3a build prompt.
 */
final class CmaInfoSectionalTitleSalesParser extends AbstractCmaInfoParser
{
    public const PARSER_VERSION = 'cma_info_sectional_title_sales_v2';

    public function getReportTypeKey(): string
    {
        return 'cma_info_sectional_title_sales';
    }

    public function getVersion(): string
    {
        return '2.0.0';
    }

    public function canParse(string $filePath): ParserConfidence
    {
        $text = $this->extractText($filePath);
        if ($text === '') return ParserConfidence::none('empty text');
        if (!$this->looksLikeCmaInfo($text)) return ParserConfidence::none('no CMA Info signature');

        $score = 0.0;
        $reasons = ['cma info signature'];

        $pages = $this->pageCount($text);
        if ($pages >= 2 && $pages <= 5) { $score += 0.3; $reasons[] = "page count {$pages}"; }

        if ($this->findHeader($text, 'Sectional Title sales')) {
            $score += 0.5;
            $reasons[] = 'Sectional Title sales header';
        }
        if (preg_match('/within\.?\s*\d{2,4}\s*m/i', $text)) {
            $score += 0.1;
            $reasons[] = 'radius signal';
        }

        return ParserConfidence::high($score, $reasons);
    }

    public function parse(string $filePath, MarketReport $report): MarketReportParseResult
    {
        $text = $this->extractText($filePath);
        if ($text === '') {
            return new MarketReportParseResult(rawJson: ['note' => 'No text extracted.']);
        }

        $points    = [];
        $addresses = [];
        $compRows  = [];
        $today     = now()->toDateString();
        $suburb    = $this->normaliseSuburb($report->source_suburb);

        // ── Subject + radius detection ─────────────────────────────────────
        $subjectMeta = [];

        // Page-1 first line — "MADEIRA GARDENS, 4 TUCKER AVENUE, UVONGO"
        if (preg_match('/^([A-Z][A-Z \']{2,40}),\s+(\d{1,4}\s+[A-Z][A-Z \']{2,40}),\s+([A-Z][A-Z \']{2,40})$/m', $text, $m)) {
            $subjectMeta['subject_scheme_name'] = trim($m[1]);
            $subjectMeta['subject_address']     = trim($m[2]) . ', ' . trim($m[3]);
        }

        // Radius: "Sectional Title sales within. 500m" or "300m radius" or "Within 500 m of"
        $radius = null;
        if (preg_match('/Sectional\s+Title\s+sales\s+within\.?\s*(\d{2,4})\s*m/i', $text, $m)) {
            $radius = (int) $m[1];
        } elseif (preg_match('/Within\s*(\d{2,4})\s*m\s*of/i', $text, $m)) {
            $radius = (int) $m[1];
        }
        if ($radius !== null) {
            $subjectMeta['radius_metres'] = $radius;
            $points[] = [
                'metric_key'           => 'sectional_radius_metres',
                'metric_value_numeric' => (float) $radius,
                'metric_date'          => $today,
                'confidence'           => 'high',
                'suburb_normalised'    => $suburb,
            ];
        }

        // ── Comp rows from the sales table ──────────────────────────────────
        // For the IN-SCHEME variant the leading "Scheme Name" column is the
        // header — every row implicitly belongs to subject_scheme_name.
        // For the RADIUS variant each row has its own "SCHEME, ADDRESS" prefix.
        $isInScheme = (bool) preg_match('/Sectional\s+Title\s+sales\s+in\.?\s+[A-Z]/i', $text);

        $rowIndex = 0;
        foreach ($this->extractCompRows($text, $isInScheme, $subjectMeta['subject_scheme_name'] ?? null) as $row) {
            $compRows[] = [
                'row_index'             => $rowIndex++,
                'row_type'              => MarketReportCompRow::ROW_COMP,
                'scheme_name'           => $row['scheme_name']    ?? null,
                'section_number'        => $row['section_number'] ?? null,
                'ss_number'             => $row['ss_number']      ?? null,
                'ss_year'               => $row['ss_year']        ?? null,
                'address'               => $row['address']        ?? null,
                'suburb_normalised'     => $suburb,
                'property_type'         => $row['property_type']  ?? 'Residence',
                'extent_m2'             => $row['extent_m2']      ?? null,
                'sale_date'             => $row['sale_date']      ?? null,
                'sale_price'            => $row['sale_price']     ?? null,
                'r_per_m2'              => $row['r_per_m2']       ?? null,
                'distance_to_subject_m' => null,  // not present in source table
                'raw_row_json'          => $row,
            ];

            if (!empty($row['sale_price'])) {
                $points[] = [
                    'metric_key'           => 'sectional_radius_sale_price',
                    'metric_value_numeric' => (float) $row['sale_price'],
                    'metric_date'          => $row['sale_date'] ?? $today,
                    'confidence'           => 'medium',
                    'suburb_normalised'    => $suburb,
                    'town'                 => $report->source_town,
                ];
            }
            if (!empty($row['address']) || !empty($row['scheme_name'])) {
                $addresses[] = $this->makeAddress([
                    'street_name' => $row['scheme_name'] ?? $row['address'] ?? null,
                    'suburb'      => $report->source_suburb,
                    'sale_price'  => $row['sale_price'] ?? null,
                    'sale_date'   => $row['sale_date'] ?? null,
                ]);
            }
        }

        // Lower/middle/upper ranges (footer)
        if (preg_match('/Lower Range:\s*R\s*([\d ,]+)\s+Middle Range:\s*R\s*([\d ,]+)\s+Upper Range:\s*R\s*([\d ,]+)/u', $text, $m)) {
            foreach (['cma_value_lower' => $m[1], 'cma_value_middle' => $m[2], 'cma_value_upper' => $m[3]] as $key => $val) {
                $price = $this->parsePrice($val);
                if ($price !== null) {
                    $points[] = ['metric_key' => $key, 'metric_value_numeric' => $price, 'metric_date' => $today, 'confidence' => 'medium', 'suburb_normalised' => $suburb];
                }
            }
        }

        return new MarketReportParseResult(
            dataPoints:        $points,
            extractedAddresses: $addresses,
            rawJson:           [
                'parser_version'  => self::PARSER_VERSION,
                'pages'           => $this->pageCount($text),
                'comp_rows'       => count($compRows),
                'radius_metres'   => $radius,
                'is_in_scheme'    => $isInScheme,
            ],
            subjectMeta:       $subjectMeta,
            compRows:          $compRows,
        );
    }

    /**
     * Extract rows from either variant. Data rows are anywhere the layout
     * yields a Section + Residence + Extent + Sale date + Sale price tuple.
     *
     * @return array<int, array<string, mixed>>
     */
    private function extractCompRows(string $text, bool $isInScheme, ?string $impliedScheme): array
    {
        $rows = [];
        $lines = preg_split('/\r?\n/', $text);
        $currentScheme  = $impliedScheme;
        $currentAddress = null;

        foreach ($lines as $line) {
            $trim = trim($line);
            if ($trim === '') continue;

            // Scheme/address header for radius variant — "SCHEME NAME, ADDR, SUBURB"
            if (!$isInScheme && preg_match('/^([A-Z][A-Z \']{2,40}),\s+([0-9]{1,4}\s+[A-Z][A-Z \']{2,40})(?:,\s+([A-Z][A-Z \']{2,40}))?$/', $trim, $am)) {
                $currentScheme  = trim($am[1]);
                $currentAddress = trim($am[2]);
                continue;
            }

            // Data row: "<sec>  <ss>  <yr>  Residence  <ext> m²  <date>  R<price>  R<ppm>"
            // Also allow scheme name prefix sometimes ("MADEIRA GARDENS ..."):
            if (preg_match(
                '/^(?<lead>[A-Z][A-Z0-9 ,\'\\.]{0,80}?)?\s*(?<sec>\d{1,3})\s+(?<ss>\d{2,5})\s+(?<yr>\d{4})\s+Residence\s+(?<ext>\d{1,5})\s*m\S?\s+(?<date>\d{4}[\/\-]\d{2}[\/\-]\d{2})\s+R\s*(?<sp>[\d ,]+)(?:\s+R\s*(?<ppm>[\d ,]+))?/u',
                $trim,
                $rm
            )) {
                $sName = $currentScheme;
                if (!empty($rm['lead']) && preg_match('/[A-Z]/', $rm['lead'])) {
                    $candidate = trim($rm['lead']);
                    // strip trailing commas / "address" parts; keep scheme name
                    $candidate = preg_replace('/,.*$/', '', $candidate);
                    if (mb_strlen($candidate) > 2 && mb_strlen($candidate) < 50) {
                        $sName = $candidate ?: $sName;
                    }
                }
                $rows[] = [
                    'scheme_name'    => $sName,
                    'address'        => $currentAddress,
                    'section_number' => $rm['sec'] ?? null,
                    'ss_number'      => $rm['ss']  ?? null,
                    'ss_year'        => isset($rm['yr']) ? (int) $rm['yr'] : null,
                    'property_type'  => 'Residence',
                    'extent_m2'      => (int) $rm['ext'],
                    'sale_date'      => $this->parseDate($rm['date']),
                    'sale_price'     => (int) $this->parsePrice($rm['sp']),
                    'r_per_m2'       => !empty($rm['ppm']) ? (int) $this->parsePrice($rm['ppm']) : null,
                ];
            }
        }

        return $rows;
    }
}
