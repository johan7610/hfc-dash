<?php

namespace App\Support\Presentation;

use App\Models\PresentationUpload;
use Illuminate\Support\Facades\Storage;

/**
 * Deterministic document field extractor (doc_extract_v1).
 *
 * Extracts structured key/value fields from PDF text using regex patterns
 * anchored on known labels. Supports CMA, suburb sales, and vicinity sales.
 *
 * Never throws. Never calls AI. Deterministic only.
 */
class DocumentExtractor
{
    public const EXTRACTED_VERSION = 'doc_extract_v1';

    /**
     * Run extraction on an upload. Returns field_key => field_value pairs.
     * Uses already-extracted text if available; falls back to PHP-based extraction.
     */
    public function extract(PresentationUpload $upload): array
    {
        $text = $upload->text_extracted ?? '';

        // If text_extracted is empty (e.g. pdftotext not available), try PHP-based extraction
        if ($text === '' && $upload->storage_path) {
            $absolutePath = Storage::disk('local')->path($upload->storage_path);
            if (file_exists($absolutePath)) {
                $text = $this->extractTextFromPdf($absolutePath);
                if ($text !== '') {
                    $upload->update([
                        'text_extracted'    => $text,
                        'extraction_status' => 'ok',
                    ]);
                }
            }
        }

        if ($text === '') {
            return [];
        }

        return match ($upload->type) {
            'cma'            => $this->parseCma($text),
            'suburb_stats'   => $this->parseSuburbSales($text),
            'vicinity_sales' => $this->parseVicinitySales($text),
            default          => [],
        };
    }

    /**
     * Extract raw text from a PDF file.
     * Tries pdftotext CLI first (faster, better quality), falls back to smalot/pdfparser.
     * Never throws.
     */
    public function extractTextFromPdf(string $absolutePath): string
    {
        if (!file_exists($absolutePath)) {
            return '';
        }

        // Try pdftotext CLI first
        $cliText = $this->tryPdftotext($absolutePath);
        if ($cliText !== '') {
            return $cliText;
        }

        // Fallback: smalot/pdfparser (pure PHP)
        if (!class_exists(\Smalot\PdfParser\Parser::class)) {
            return '';
        }

        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf    = $parser->parseFile($absolutePath);
            $text   = $pdf->getText();
            return is_string($text) ? trim($text) : '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function tryPdftotext(string $absolutePath): string
    {
        $check = PHP_OS_FAMILY === 'Windows'
            ? @shell_exec('where pdftotext 2>NUL')
            : @shell_exec('command -v pdftotext 2>/dev/null');

        if (empty($check)) {
            return '';
        }

        try {
            $escaped = escapeshellarg($absolutePath);
            $output  = shell_exec("pdftotext {$escaped} -");
            return is_string($output) ? trim($output) : '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    // ── CMA Parser ──────────────────────────────────────────────────────────

    public function parseCma(string $text): array
    {
        $fields = [];

        // Price ranges: "Lower Range: R 2,250,000"
        $this->matchPrice($text, 'Lower\s*Range', $fields, 'cma.lower_range');
        $this->matchPrice($text, 'Middle\s*Range', $fields, 'cma.middle_range');
        $this->matchPrice($text, 'Upper\s*Range', $fields, 'cma.upper_range');

        // Municipal valuation: "Total Value: R 1,800,000" or "Municipal Valuation: R..."
        $this->matchPrice($text, '(?:Total|Municipal)\s*(?:Municipal\s*)?(?:Value|Valuation)', $fields, 'municipal.total_value');

        // Valuation year
        if (preg_match('/(?:Valuation|Municipal)\s*(?:Year|Date)\s*[:\-]?\s*(\d{4})/i', $text, $m)) {
            $year = (int) $m[1];
            if ($year >= 1990 && $year <= 2030) {
                $fields['municipal.valuation_year'] = (string) $year;
            }
        }

        // Subject property: Address
        if (preg_match('/(?:Property\s*)?Address\s*[:\-]\s*(.+?)(?:\r?\n|$)/i', $text, $m)) {
            $addr = trim($m[1]);
            if (strlen($addr) >= 5 && strlen($addr) <= 200) {
                $fields['subject.address'] = $addr;
            }
        }

        // Subject property: Suburb
        if (preg_match('/Suburb\s*[:\-]\s*(.+?)(?:\r?\n|$)/i', $text, $m)) {
            $suburb = trim($m[1]);
            if (strlen($suburb) >= 2 && strlen($suburb) <= 100) {
                $fields['subject.suburb'] = $suburb;
            }
        }

        // Subject property: Erf / Stand
        if (preg_match('/(?:Erf|Stand)\s*(?:No\.?|Number)?\s*[:\-]?\s*(\S+)/i', $text, $m)) {
            $erf = trim($m[1]);
            if (strlen($erf) >= 1 && strlen($erf) <= 50) {
                $fields['subject.erf'] = $erf;
            }
        }

        // Extent in m²
        if (preg_match('/(?:Extent|Size|Floor\s*Area|Property\s*Size)\s*[:\-]?\s*(\d[\d\s,]*\d|\d+)\s*(?:m2|m²|sqm)/i', $text, $m)) {
            $size = (int) preg_replace('/[\s,]/', '', $m[1]);
            if ($size >= 10 && $size <= 99999) {
                $fields['subject.extent_m2'] = (string) $size;
            }
        }

        // Purchase date
        if (preg_match('/(?:Purchase|Transfer|Acquisition)\s*Date\s*[:\-]?\s*(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{4}|\d{4}[\/\-\.]\d{2}[\/\-\.]\d{2})/i', $text, $m)) {
            $fields['subject.purchase_date'] = trim($m[1]);
        }

        // Purchase price
        $this->matchPrice($text, '(?:Purchase|Transfer|Acquisition)\s*Price', $fields, 'subject.purchase_price');

        // Indexed value
        $this->matchPrice($text, '(?:Indexed|CPI\s*Indexed|Inflation\s*Adjusted)\s*Value', $fields, 'subject.indexed_value');

        // CAGR
        if (preg_match('/(?:CAGR|Compound\s*Annual\s*Growth\s*Rate)\s*[:\-]?\s*([\d.]+)\s*%?/i', $text, $m)) {
            $cagr = (float) $m[1];
            if ($cagr > 0 && $cagr < 100) {
                $fields['subject.cagr'] = (string) $cagr;
            }
        }

        return $fields;
    }

    // ── Suburb Sales Parser ─────────────────────────────────────────────────

    public function parseSuburbSales(string $text): array
    {
        $fields = [];

        // Strategy: parse tabular rows from "Residential Price Ranges" section.
        // Each row: Year  NoOfSales  R Low  R Median  R High  R Maximum
        // Price format: R X XXX XXX (1-3 leading digits then groups of 3 separated by space/comma).
        // Using specific pattern to avoid lazy quantifier issues.
        $priceRe = 'R\s*(\d{1,3}(?:[\s,]\d{3})+)';
        $rowPattern = '/\b(20\d{2})\s+(\d{1,4})\s+' . $priceRe . '\s+' . $priceRe . '\s+' . $priceRe . '\s+' . $priceRe . '/i';

        $rows = [];
        if (preg_match_all($rowPattern, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $year  = (int) $m[1];
                $count = (int) $m[2];
                $low   = (int) preg_replace('/[\s,]/', '', $m[3]);
                $med   = (int) preg_replace('/[\s,]/', '', $m[4]);
                $high  = (int) preg_replace('/[\s,]/', '', $m[5]);
                $max   = (int) preg_replace('/[\s,]/', '', $m[6]);

                if ($year >= 2000 && $year <= 2030 && $count > 0 && $med >= 10000) {
                    $rows[] = compact('year', 'count', 'low', 'med', 'high', 'max');
                }
            }
        }

        if (!empty($rows)) {
            // Pick the latest year with a meaningful sample (>= 10 sales).
            // Fall back to the absolute latest year if none qualifies.
            usort($rows, fn ($a, $b) => $b['year'] <=> $a['year']);
            $best = null;
            foreach ($rows as $row) {
                if ($row['count'] >= 10) {
                    $best = $row;
                    break;
                }
            }
            if ($best === null) {
                $best = $rows[0]; // latest year regardless
            }

            $fields['suburb.latest_year']         = (string) $best['year'];
            $fields['suburb.latest_sales_count']   = (string) $best['count'];
            $fields['suburb.latest_median_price']  = (string) $best['med'];
            $fields['suburb.latest_low']           = (string) $best['low'];
            $fields['suburb.latest_high']          = (string) $best['high'];
            $fields['suburb.latest_max']           = (string) $best['max'];

            return $fields;
        }

        // Fallback: parse "Residential Sales Analysis" table rows.
        // Pattern: Year  NoOfSales  R MedianPrice  Percentage  Index
        $salesPattern = '/\b(20\d{2})\s+(\d{1,4})\s+R\s*(\d{1,3}(?:[\s,]\d{3})+)\s+[\-\d]/i';
        $salesRows = [];
        if (preg_match_all($salesPattern, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $year  = (int) $m[1];
                $count = (int) $m[2];
                $med   = (int) preg_replace('/[\s,]/', '', $m[3]);
                if ($year >= 2000 && $year <= 2030 && $count > 0 && $med >= 10000) {
                    $salesRows[] = ['year' => $year, 'count' => $count, 'med' => $med];
                }
            }
        }

        if (!empty($salesRows)) {
            usort($salesRows, fn ($a, $b) => $b['year'] <=> $a['year']);
            $best = null;
            foreach ($salesRows as $row) {
                if ($row['count'] >= 10) {
                    $best = $row;
                    break;
                }
            }
            if ($best === null) {
                $best = $salesRows[0];
            }

            $fields['suburb.latest_year']         = (string) $best['year'];
            $fields['suburb.latest_sales_count']   = (string) $best['count'];
            $fields['suburb.latest_median_price']  = (string) $best['med'];
        }

        return $fields;
    }

    // ── Vicinity Sales Parser ───────────────────────────────────────────────

    public function parseVicinitySales(string $text): array
    {
        $fields = [];

        // Price ranges — "Lower Range: R 1 206 000"
        $this->matchPrice($text, 'Lower\s*Range', $fields, 'vicinity.lower_range');
        $this->matchPrice($text, 'Middle\s*Range', $fields, 'vicinity.middle_range');
        $this->matchPrice($text, 'Upper\s*Range', $fields, 'vicinity.upper_range');

        // Average price — "Average: R 1 687 000" (may not have the word "Price")
        $this->matchPrice($text, '(?:Average|Avg|Mean)\s*(?:Sale\s*)?(?:Price)?', $fields, 'vicinity.average_price');

        // Average price per m² — "Average R/m²: R 1 232" or "Average R/m²:\tR 1 232"
        // Use [^\s:\-]* after 'm' to safely skip ²/2/sqm without UTF-8 byte issues.
        if (preg_match('/(?:Average|Avg|Mean)\s*R\s*\/\s*m[^\s:\-]*\s*[:\-]?\s*R?\s*(\d{1,3}(?:[, ]\d{3})*|\d{3,10})/i', $text, $m)) {
            if (isset($m[1])) {
                $cleaned = (int) preg_replace('/[\s,]/', '', $m[1]);
                if ($cleaned >= 100) {
                    $fields['vicinity.avg_price_per_m2'] = (string) $cleaned;
                }
            }
        }
        // Fallback: "Average price per m²: R..." or "R12,345/m²"
        if (!isset($fields['vicinity.avg_price_per_m2'])) {
            if (preg_match('/(?:Average|Avg|Mean)\s*(?:Price\s*)?(?:per|\/)\s*(?:m2|m²|sqm)\s*[:\-]?\s*R?\s*(\d{1,3}(?:[, ]\d{3})+|\d{3,10})/i', $text, $m)) {
                if (isset($m[1])) {
                    $cleaned = (int) preg_replace('/[\s,]/', '', $m[1]);
                    if ($cleaned >= 100) {
                        $fields['vicinity.avg_price_per_m2'] = (string) $cleaned;
                    }
                }
            }
        }
        if (!isset($fields['vicinity.avg_price_per_m2'])) {
            if (preg_match('/R\s*(\d{1,3}(?:[, ]\d{3})+|\d{3,10})\s*(?:per|\/)\s*(?:m2|m²|sqm)/i', $text, $m)) {
                if (isset($m[1])) {
                    $cleaned = (int) preg_replace('/[\s,]/', '', $m[1]);
                    if ($cleaned >= 100) {
                        $fields['vicinity.avg_price_per_m2'] = (string) $cleaned;
                    }
                }
            }
        }

        // Comps count — count numbered property rows: "1  292 m  792  8 SMUTS..."
        // Each row starts with a row number, then distance, then erf number
        $compsCount = preg_match_all('/^\s*(\d{1,3})\s+(?:\d+\s*m|-)\s+\d+\s+\d+\s+\w/m', $text);
        if ($compsCount && $compsCount > 0 && $compsCount < 1000) {
            $fields['vicinity.comps_count'] = (string) $compsCount;
        }

        // Fallback comps count: look for explicit labels
        if (!isset($fields['vicinity.comps_count'])) {
            if (preg_match('/(?:Comparables?|Comps?|Properties\s*(?:Sold|Found|Used|Within))\s*[:\-]?\s*(\d+)/i', $text, $m)) {
                $count = (int) $m[1];
                if ($count > 0 && $count < 1000) {
                    $fields['vicinity.comps_count'] = (string) $count;
                }
            }
        }
        if (!isset($fields['vicinity.comps_count'])) {
            if (preg_match('/(\d+)\s*(?:comparables?|comps?|properties\s*(?:sold|within|found))/i', $text, $m)) {
                $count = (int) $m[1];
                if ($count > 0 && $count < 1000) {
                    $fields['vicinity.comps_count'] = (string) $count;
                }
            }
        }

        return $fields;
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Match a label pattern followed by a price value.
     * Normalizes "R 2,250,000" → "2250000" for storage.
     */
    private function matchPrice(string $text, string $labelPattern, array &$fields, string $key): void
    {
        $pattern = '/' . $labelPattern . '\s*[:\-]?\s*R?\s*(\d{1,3}(?:[, ]\d{3})+|\d{4,10})/i';
        if (preg_match($pattern, $text, $m)) {
            $cleaned = (int) preg_replace('/[\s,]/', '', $m[1]);
            if ($cleaned >= 1000) {
                $fields[$key] = (string) $cleaned;
            }
        }
    }
}
