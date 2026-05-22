<?php

declare(strict_types=1);

namespace App\Services\MarketReports\Parsers;

use App\Models\MarketReports\MarketReport;
use App\Services\MarketReports\DTOs\MarketReportParseResult;
use App\Services\MarketReports\DTOs\ParserConfidence;

/**
 * V2 parser for CMA Info "Median Sales Analysis" — 4-page suburb-history PDF.
 * Layout per row: "<year> <count> R<median> <change%> <index>" with optional
 * second area (e.g. the parent municipality RAY NKONYENI alongside UVONGO).
 *
 * Phase 3a additions:
 *   - extracts BOTH areas when the table has parallel suburb/municipality cols
 *   - no comp rows (the report is purely aggregate metrics — no per-row data)
 *   - parser version bumped to v2 for audit
 *
 * Spec: .ai/specs/mic-complete-spec.md §8.3 + Phase 3a build prompt.
 */
final class CmaInfoMedianSalesAnalysisParser extends AbstractCmaInfoParser
{
    public const PARSER_VERSION = 'cma_info_median_sales_analysis_v2';

    public function getReportTypeKey(): string
    {
        return 'cma_info_median_sales_analysis';
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
        if ($pages >= 2 && $pages <= 6) { $score += 0.3; $reasons[] = "page count {$pages}"; }

        if ($this->findHeader($text, 'Median Sales Analysis') || $this->findHeader($text, 'ST Residential Sales Analysis')) {
            $score += 0.5;
            $reasons[] = 'Median Sales Analysis header';
        }
        if ($this->findHeader($text, 'Annual Change') || $this->findHeader($text, 'YoY')) {
            $score += 0.1;
            $reasons[] = 'annual change column';
        }
        if (preg_match('/\b20\d{2}\s+\d+\s+R[\s\d,]+/', $text)) {
            $score += 0.1;
            $reasons[] = 'year×sales×median row';
        }

        return ParserConfidence::high($score, $reasons);
    }

    public function parse(string $filePath, MarketReport $report): MarketReportParseResult
    {
        $text = $this->extractText($filePath);
        if ($text === '') {
            return new MarketReportParseResult(rawJson: ['note' => 'No text extracted.']);
        }

        $points = [];
        $today  = now()->toDateString();
        $suburbNorm = $this->normaliseSuburb($report->source_suburb);
        $town   = $report->source_town;

        // Detect the second area header (municipality) — e.g. "RAY NKONYENI".
        // It appears in the "Year   UVONGO   ...   RAY NKONYENI" column header.
        $secondAreaName = null;
        if (preg_match('/Year\s+[A-Z][A-Z \']{2,30}\s+([A-Z][A-Z \']{3,30})/u', $text, $hm)) {
            $secondAreaName = trim($hm[1]);
        }

        // Split text into per-year blocks: each block begins at `^20YY` and
        // extends to (but not including) the next `^20YY`. Within each block
        // we look for one or two (count, R<median>, change%) triplets — first
        // is the subject suburb column, second (when present) is the
        // municipality column. Indices are optional. This is far more tolerant
        // than the previous "all on one line" pattern.
        if (preg_match_all('/(?:^|\n)(?<year>20\d{2})(?<body>.*?)(?=(?:\n20\d{2})|\nPlease|\Z)/su', $text, $blocks, PREG_SET_ORDER)) {
            foreach ($blocks as $block) {
                $year = (int) $block['year'];
                if ($year < 2000 || $year > 2099) continue;
                $body = (string) $block['body'];

                if (!preg_match_all('/(?<c>\d{1,5})\s+R\s*(?<m>[\d ,]+)\s+(?<chg>-?\d{1,3}\.\d{1,2})\s*%/u', $body, $triplets, PREG_SET_ORDER)) {
                    continue;
                }

                // MarketDataPoint validation requires exactly ONE of
                // metric_value_(numeric|date|string). Encode the year via
                // metric_date (Y-12-31) and leave metric_value_date null.
                $metricDate = $year . '-12-31';

                // First triplet = subject column
                $t1 = $triplets[0];
                $points[] = ['metric_key' => 'suburb_median_price_year', 'metric_value_numeric' => $this->parsePrice($t1['m']), 'metric_date' => $metricDate, 'confidence' => 'high', 'suburb_normalised' => $suburbNorm, 'town' => $town];
                $points[] = ['metric_key' => 'suburb_sales_count_year', 'metric_value_numeric' => (float) $t1['c'], 'metric_date' => $metricDate, 'confidence' => 'high', 'suburb_normalised' => $suburbNorm, 'town' => $town];
                $points[] = ['metric_key' => 'suburb_annual_change_pct', 'metric_value_numeric' => (float) $t1['chg'], 'metric_date' => $metricDate, 'confidence' => 'medium', 'suburb_normalised' => $suburbNorm, 'town' => $town];

                // Second triplet (when present) = municipality column
                if (isset($triplets[1])) {
                    $t2 = $triplets[1];
                    $secondNorm = $secondAreaName !== null ? $this->normaliseSuburb($secondAreaName) : null;
                    $points[] = ['metric_key' => 'suburb_median_price_year', 'metric_value_numeric' => $this->parsePrice($t2['m']), 'metric_date' => $metricDate, 'confidence' => 'high', 'suburb_normalised' => $secondNorm, 'town' => $secondAreaName];
                    $points[] = ['metric_key' => 'suburb_sales_count_year', 'metric_value_numeric' => (float) $t2['c'], 'metric_date' => $metricDate, 'confidence' => 'high', 'suburb_normalised' => $secondNorm, 'town' => $secondAreaName];
                    $points[] = ['metric_key' => 'suburb_annual_change_pct', 'metric_value_numeric' => (float) $t2['chg'], 'metric_date' => $metricDate, 'confidence' => 'medium', 'suburb_normalised' => $secondNorm, 'town' => $secondAreaName];
                }
            }
        }

        return new MarketReportParseResult(
            dataPoints: $points,
            rawJson: [
                'parser_version'   => self::PARSER_VERSION,
                'pages'            => $this->pageCount($text),
                'second_area_name' => $secondAreaName,
            ],
        );
    }
}
