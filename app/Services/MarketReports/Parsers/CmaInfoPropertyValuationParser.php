<?php

declare(strict_types=1);

namespace App\Services\MarketReports\Parsers;

use App\Models\MarketReports\MarketReport;
use App\Services\MarketReports\DTOs\MarketReportParseResult;
use App\Services\MarketReports\DTOs\ParserConfidence;

/**
 * V1 parser for CMA Info "Property Valuation" — full ~11-page document with
 * subject details, sale history, comparatives, and a value range.
 *
 * Spec: .ai/specs/mic-complete-spec.md §8.3.
 */
final class CmaInfoPropertyValuationParser extends AbstractCmaInfoParser
{
    public function getReportTypeKey(): string
    {
        return 'cma_info_property_valuation';
    }

    public function canParse(string $filePath): ParserConfidence
    {
        $text = $this->extractText($filePath);
        if ($text === '') return ParserConfidence::none('empty text');
        if (!$this->looksLikeCmaInfo($text)) return ParserConfidence::none('no CMA Info signature');

        $score = 0.0;
        $reasons = ['cma info signature'];

        $pages = $this->pageCount($text);
        if ($pages >= 9 && $pages <= 14) { $score += 0.3; $reasons[] = "page count {$pages}"; }

        if ($this->findHeader($text, 'Property Valuation')) {
            $score += 0.4;
            $reasons[] = 'Property Valuation header';
        }
        if ($this->findHeader($text, 'Sale History') || $this->findHeader($text, 'Previous Sales')) {
            $score += 0.15;
            $reasons[] = 'sale history block';
        }
        if ($this->findHeader($text, 'Municipal Valuation')) {
            $score += 0.1;
            $reasons[] = 'municipal valuation block';
        }
        if ($this->findHeader($text, 'Value Range') || $this->findHeader($text, 'Recommended Value')) {
            $score += 0.1;
            $reasons[] = 'value range block';
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
        $addresses = [];
        $today = now()->toDateString();
        $suburb = $this->normaliseSuburb($report->source_suburb);

        // Subject property address (first line after the header).
        if (preg_match('/Property Valuation[^\n]*\n(?<line>[^\n]{6,180})/i', $text, $m)) {
            $line = trim($m['line']);
            if (preg_match('/^(\d{1,4})\s+([A-Za-z\' ]+),\s*([A-Za-z\' ]+)/', $line, $am)) {
                $addresses[] = $this->makeAddress([
                    'street_number' => $am[1],
                    'street_name'   => trim($am[2]),
                    'suburb'        => trim($am[3]),
                ]);
            }
        }

        // Municipal valuation + year.
        if (preg_match('/Municipal Valuation[^\n]*?(R\s*[\d ,]+)[^\n]*?(20\d{2})/i', $text, $m)) {
            $val = $this->parsePrice($m[1]);
            $year = (int) $m[2];
            if ($val !== null) {
                $points[] = [
                    'metric_key'           => 'municipal_valuation',
                    'metric_value_numeric' => $val,
                    'metric_value_date'    => $year . '-07-01',
                    'metric_date'          => $today,
                    'confidence'           => 'high',
                    'suburb_normalised'    => $suburb,
                ];
            }
        }

        // Sale history rows — "R 1,234,567  on  YYYY-MM-DD".
        if (preg_match_all('/R\s*([\d ,]+)\s+on\s+(\d{4}[-\/]\d{2}[-\/]\d{2})/i', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $row) {
                $points[] = [
                    'metric_key'           => 'subject_sale_history',
                    'metric_value_numeric' => $this->parsePrice($row[1]),
                    'metric_value_date'    => $this->parseDate($row[2]),
                    'metric_date'          => $today,
                    'confidence'           => 'high',
                    'suburb_normalised'    => $suburb,
                ];
            }
        }

        // Value range — "R 1,000,000 to R 1,400,000" or with – / —.
        if (preg_match('/(?:Value Range|Recommended)[^\n]*?(R\s*[\d ,]+)[\s\-\x{2013}\x{2014}to]+(R\s*[\d ,]+)/iu', $text, $m)) {
            $lower = $this->parsePrice($m[1]);
            $upper = $this->parsePrice($m[2]);
            if ($lower !== null) $points[] = [
                'metric_key' => 'cma_value_lower', 'metric_value_numeric' => $lower,
                'metric_date' => $today, 'confidence' => 'medium', 'suburb_normalised' => $suburb,
            ];
            if ($upper !== null) $points[] = [
                'metric_key' => 'cma_value_upper', 'metric_value_numeric' => $upper,
                'metric_date' => $today, 'confidence' => 'medium', 'suburb_normalised' => $suburb,
            ];
        }

        return new MarketReportParseResult(
            dataPoints: $points,
            extractedAddresses: $addresses,
            rawJson: ['pages' => $this->pageCount($text)],
        );
    }
}
