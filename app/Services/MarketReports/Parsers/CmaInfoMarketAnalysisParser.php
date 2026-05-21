<?php

declare(strict_types=1);

namespace App\Services\MarketReports\Parsers;

use App\Models\MarketReports\MarketReport;
use App\Services\MarketReports\DTOs\MarketReportParseResult;
use App\Services\MarketReports\DTOs\ParserConfidence;

/**
 * V1 parser for the CMA Info "Market Analysis" report — a 10-page PDF with
 * a subject property header on page 1, a vicinity-sales table around page
 * 4-5, comparative listings, and a 12-month suburb stats panel.
 *
 * V1 extraction is regex-driven against pdftotext output. Anywhere extraction
 * fails we degrade gracefully (return whatever points we found, leave the
 * rest off) — the spot-check audit (Phase F §8.5) catches divergence.
 *
 * Spec: .ai/specs/mic-complete-spec.md §8.3.
 */
final class CmaInfoMarketAnalysisParser extends AbstractCmaInfoParser
{
    public function getReportTypeKey(): string
    {
        return 'cma_info_market_analysis';
    }

    public function canParse(string $filePath): ParserConfidence
    {
        $text = $this->extractText($filePath);
        if ($text === '') return ParserConfidence::none('extractor returned empty text');
        if (!$this->looksLikeCmaInfo($text)) return ParserConfidence::none('no CMA Info signature');

        $score = 0.0;
        $reasons = ['cma info signature'];

        $pages = $this->pageCount($text);
        if ($pages >= 8 && $pages <= 14) { $score += 0.4; $reasons[] = "page count {$pages}"; }

        if ($this->findHeader($text, 'Market Analysis')) {
            $score += 0.3;
            $reasons[] = 'Market Analysis header';
        }
        if ($this->findHeader($text, 'Subject Property')) {
            $score += 0.1;
            $reasons[] = 'Subject Property block';
        }
        if ($this->findHeader($text, 'Vicinity Sales') || $this->findHeader($text, 'Comparable Sales')) {
            $score += 0.1;
            $reasons[] = 'comparable sales table';
        }
        if ($this->findHeader($text, '12 Month')) {
            $score += 0.1;
            $reasons[] = '12-month suburb stats panel';
        }

        return ParserConfidence::high($score, $reasons);
    }

    public function parse(string $filePath, MarketReport $report): MarketReportParseResult
    {
        $text = $this->extractText($filePath);
        if ($text === '') {
            return new MarketReportParseResult(
                dataPoints: [],
                extractedAddresses: [],
                rawJson: ['note' => 'No text extracted — pdftotext unavailable or PDF is image-only.'],
            );
        }

        $points = [];
        $addresses = [];
        $today = now()->toDateString();

        // Subject property — first address-shaped line after the header.
        if (preg_match('/Subject Property[^\n]*\n(?<line>[^\n]{6,180})/i', $text, $m)) {
            $sub = $this->parseAddressLine($m['line']);
            if ($sub) {
                $addresses[] = $sub;
            }
        }

        // Suburb 12-month median + total sales — flexible patterns.
        if (preg_match('/Median (?:Price|Sale Price)[^\n]*?(R\s*[\d ,]+)/i', $text, $m)) {
            $price = $this->parsePrice($m[1]);
            if ($price !== null) {
                $points[] = [
                    'metric_key'           => 'suburb_median_price_12m',
                    'metric_value_numeric' => $price,
                    'metric_date'          => $today,
                    'confidence'           => 'high',
                    'suburb_normalised'    => $this->normaliseSuburb($report->source_suburb),
                    'town'                 => $report->source_town,
                ];
            }
        }
        if (preg_match('/(?:Total Sales|No\.?\s*of Sales)[^\n]*?(\d{1,5})/i', $text, $m)) {
            $points[] = [
                'metric_key'           => 'suburb_total_sales_12m',
                'metric_value_numeric' => (float) $m[1],
                'metric_date'          => $today,
                'confidence'           => 'high',
                'suburb_normalised'    => $this->normaliseSuburb($report->source_suburb),
                'town'                 => $report->source_town,
            ];
        }

        // Recommended CMA range — e.g. "R 2,500,000 – R 2,800,000".
        if (preg_match('/(?:Recommended|Suggested) (?:Range|Price)[^\n]*?(R\s*[\d ,]+)[^\n]*?(R\s*[\d ,]+)/i', $text, $m)) {
            $lower = $this->parsePrice($m[1]);
            $upper = $this->parsePrice($m[2]);
            if ($lower !== null) {
                $points[] = [
                    'metric_key'           => 'cma_value_lower',
                    'metric_value_numeric' => $lower,
                    'metric_date'          => $today,
                    'confidence'           => 'medium',
                    'suburb_normalised'    => $this->normaliseSuburb($report->source_suburb),
                ];
            }
            if ($upper !== null) {
                $points[] = [
                    'metric_key'           => 'cma_value_upper',
                    'metric_value_numeric' => $upper,
                    'metric_date'          => $today,
                    'confidence'           => 'medium',
                    'suburb_normalised'    => $this->normaliseSuburb($report->source_suburb),
                ];
            }
        }

        // Comparable sales table — best-effort line scan.
        // Each line: "street number street name, suburb  R 1,234,567  yyyy-mm-dd"
        if (preg_match_all('/(\d{1,4})\s+([A-Z][A-Za-z\' ]{2,40})[,\s]+([A-Z][A-Za-z\' ]{2,30}).{0,40}R\s*([\d ,]+)\s+(\d{4}[-\/]\d{2}[-\/]\d{2})/m', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $row) {
                $address = $this->makeAddress([
                    'street_number' => $row[1],
                    'street_name'   => trim($row[2]),
                    'suburb'        => trim($row[3]),
                    'sale_price'    => $this->parsePrice($row[4]),
                    'sale_date'     => $this->parseDate($row[5]),
                ]);
                $addresses[] = $address;
                $points[] = [
                    'metric_key'           => 'comparable_sale_price',
                    'metric_value_numeric' => $this->parsePrice($row[4]),
                    'metric_value_date'    => $this->parseDate($row[5]),
                    'metric_value_string'  => trim($row[2]) . ', ' . trim($row[3]),
                    'metric_date'          => $today,
                    'confidence'           => 'medium',
                    'suburb_normalised'    => $this->normaliseSuburb($row[3]),
                ];
            }
        }

        return new MarketReportParseResult(
            dataPoints: $points,
            extractedAddresses: $addresses,
            rawJson: ['pages' => $this->pageCount($text), 'first_500' => mb_substr($text, 0, 500)],
        );
    }

    private function parseAddressLine(string $line): ?array
    {
        $line = trim($line);
        if ($line === '') return null;
        // "12 Main Road, Margate" → street_number=12, street_name=Main Road, suburb=Margate
        if (preg_match('/^(\d{1,4})\s+([A-Za-z\' ]+),\s*([A-Za-z\' ]+)/', $line, $m)) {
            return $this->makeAddress([
                'street_number' => $m[1],
                'street_name'   => trim($m[2]),
                'suburb'        => trim($m[3]),
            ]);
        }
        if (preg_match('/^([A-Za-z\' ]+),\s*([A-Za-z\' ]+)/', $line, $m)) {
            return $this->makeAddress([
                'street_name' => trim($m[1]),
                'suburb'      => trim($m[2]),
            ]);
        }
        return null;
    }
}
