<?php

declare(strict_types=1);

namespace App\Services\MarketReports\Parsers;

use App\Models\MarketReports\MarketReport;
use App\Services\MarketReports\DTOs\MarketReportParseResult;
use App\Services\MarketReports\DTOs\ParserConfidence;

/**
 * V1 parser for CMA Info "Median Sales Analysis" — 4-page suburb-history
 * PDF with per-year median, sales count, and annual change.
 *
 * Spec: .ai/specs/mic-complete-spec.md §8.3.
 */
final class CmaInfoMedianSalesAnalysisParser extends AbstractCmaInfoParser
{
    public function getReportTypeKey(): string
    {
        return 'cma_info_median_sales_analysis';
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

        if ($this->findHeader($text, 'Median Sales Analysis')) {
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
        $today = now()->toDateString();
        $suburb = $this->normaliseSuburb($report->source_suburb);

        // "YYYY  <count>  R 1,234,567  <±N.NN%>"
        if (preg_match_all('/(?<year>20\d{2})\s+(?<count>\d{1,5})\s+R\s*(?<med>[\d ,]+)(?:\s+(?<chg>-?\d{1,3}\.\d{1,2})\s*%)?/m', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $row) {
                $year = (int) $row['year'];
                $medDate = $year . '-12-31';
                $points[] = [
                    'metric_key'           => 'suburb_median_price_year',
                    'metric_value_numeric' => $this->parsePrice($row['med']),
                    'metric_value_date'    => $medDate,
                    'metric_date'          => $today,
                    'confidence'           => 'high',
                    'suburb_normalised'    => $suburb,
                    'town'                 => $report->source_town,
                ];
                $points[] = [
                    'metric_key'           => 'suburb_sales_count_year',
                    'metric_value_numeric' => (float) $row['count'],
                    'metric_value_date'    => $medDate,
                    'metric_date'          => $today,
                    'confidence'           => 'high',
                    'suburb_normalised'    => $suburb,
                    'town'                 => $report->source_town,
                ];
                if (!empty($row['chg'])) {
                    $points[] = [
                        'metric_key'           => 'suburb_annual_change_pct',
                        'metric_value_numeric' => (float) $row['chg'],
                        'metric_value_date'    => $medDate,
                        'metric_date'          => $today,
                        'confidence'           => 'medium',
                        'suburb_normalised'    => $suburb,
                        'town'                 => $report->source_town,
                    ];
                }
            }
        }

        return new MarketReportParseResult(
            dataPoints: $points,
            rawJson: ['pages' => $this->pageCount($text), 'years_found' => count($points) / 2],
        );
    }
}
