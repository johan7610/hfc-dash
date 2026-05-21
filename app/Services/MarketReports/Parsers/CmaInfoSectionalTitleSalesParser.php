<?php

declare(strict_types=1);

namespace App\Services\MarketReports\Parsers;

use App\Models\MarketReports\MarketReport;
use App\Services\MarketReports\DTOs\MarketReportParseResult;
use App\Services\MarketReports\DTOs\ParserConfidence;

/**
 * V1 parser for CMA Info "Sectional Title Sales" — 3-page 300m-radius
 * sales analysis for sectional-title properties.
 *
 * Spec: .ai/specs/mic-complete-spec.md §8.3.
 */
final class CmaInfoSectionalTitleSalesParser extends AbstractCmaInfoParser
{
    public function getReportTypeKey(): string
    {
        return 'cma_info_sectional_title_sales';
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

        if ($this->findHeader($text, 'Sectional Title')) {
            $score += 0.5;
            $reasons[] = 'Sectional Title header';
        }
        if ($this->findHeader($text, '300m radius') || $this->findHeader($text, '300m Radius')) {
            $score += 0.1;
            $reasons[] = '300m radius signal';
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

        // "<scheme name>  Unit <N>  R 1,234,567  yyyy-mm-dd"
        if (preg_match_all('/(?<scheme>[A-Z][A-Za-z0-9\' ]{3,40})\s+Unit\s+(?<unit>\d{1,4})[^\n]*?R\s*(?<price>[\d ,]+)\s+(?<date>\d{4}[-\/]\d{2}[-\/]\d{2})/i', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $row) {
                $addresses[] = $this->makeAddress([
                    'unit_number' => $row['unit'],
                    'street_name' => trim($row['scheme']),
                    'suburb'      => $report->source_suburb,
                    'sale_price'  => $this->parsePrice($row['price']),
                    'sale_date'   => $this->parseDate($row['date']),
                ]);
                $points[] = [
                    'metric_key'           => 'sectional_radius_sale_price',
                    'metric_value_numeric' => $this->parsePrice($row['price']),
                    'metric_value_date'    => $this->parseDate($row['date']),
                    'metric_value_string'  => trim($row['scheme']) . ' Unit ' . $row['unit'],
                    'metric_date'          => $today,
                    'confidence'           => 'medium',
                    'suburb_normalised'    => $suburb,
                    'town'                 => $report->source_town,
                ];
            }
        }

        // Radius constant (always 300m for this report).
        $points[] = [
            'metric_key'           => 'sectional_radius_metres',
            'metric_value_numeric' => 300.0,
            'metric_date'          => $today,
            'confidence'           => 'high',
            'suburb_normalised'    => $suburb,
        ];

        return new MarketReportParseResult(
            dataPoints: $points,
            extractedAddresses: $addresses,
            rawJson: ['pages' => $this->pageCount($text), 'sectional_rows' => count($addresses)],
        );
    }
}
