<?php

declare(strict_types=1);

namespace App\Services\MarketReports\Parsers;

use App\Domain\Presentation\TextExtractionService;
use App\Models\Prospecting\TrackedProperty;
use App\Services\MarketReports\Contracts\MarketReportParser;
use App\Services\MarketReports\DTOs\ParserConfidence;

/**
 * Shared scaffolding for the CMA Info family of parsers.
 *
 * Subclasses override canParse()/parse() and reuse:
 *   - extractText()          — pdftotext via the existing service
 *   - normaliseSuburb()      — same as TrackedProperty::normaliseSuburb
 *   - parsePrice()           — strips R + comma + spaces from "R 1,234,567"
 *   - parseDate()            — flexible Carbon parse with null-on-fail
 *   - findHeader()           — case-insensitive substring check
 *   - looksLikeCmaInfo()     — common gate ("CMA Info", "CMAInfo") + page count band
 *
 * On Windows local without pdftotext, extractText() returns ''. Each subclass'
 * canParse() must handle empty text and return ParserConfidence::none() so
 * the GenericFallbackParser ends up winning.
 *
 * Spec: .ai/specs/mic-complete-spec.md §8.
 */
abstract class AbstractCmaInfoParser implements MarketReportParser
{
    public function __construct(
        protected readonly TextExtractionService $textExtractor,
    ) {}

    /**
     * Returns raw pdftotext output (preferring -layout mode so column-based
     * CMA Info reports survive extraction) or '' when extraction is
     * unavailable / fails. NEVER throws.
     *
     * Falls back to the standard TextExtractionService (no -layout) when the
     * pdftotext binary is unavailable on PATH.
     */
    protected function extractText(string $filePath): string
    {
        if (!is_file($filePath)) return '';

        // -layout preserves table columns — essential for CMA Info reports
        // where labels and values are positioned side-by-side, not stacked.
        $layout = $this->tryPdftotextLayout($filePath);
        if ($layout !== '') {
            return $layout;
        }

        return $this->textExtractor->extractText($filePath, 'application/pdf');
    }

    private function tryPdftotextLayout(string $absolutePath): string
    {
        $whereCmd = PHP_OS_FAMILY === 'Windows' ? 'where pdftotext 2>NUL' : 'command -v pdftotext 2>/dev/null';
        $exists = @shell_exec($whereCmd);
        if (empty($exists)) return '';

        try {
            $escaped = escapeshellarg($absolutePath);
            $output  = @shell_exec("pdftotext -layout {$escaped} -");
            if (!is_string($output)) return '';

            // pdftotext emits U+FFFD (replacement char) for glyphs it cannot
            // decode (e.g. ° / ²). The bytes look like 0xEF 0xBF 0xBD which
            // IS valid UTF-8 — but pdftotext on Windows sometimes emits
            // lone 0xEF or 0xBD bytes that break /u-modifier regex matches.
            // Strip invalid UTF-8 silently so subsequent /u regex calls
            // don't throw preg_last_error 4 (PREG_BAD_UTF8_ERROR).
            $cleaned = @mb_convert_encoding($output, 'UTF-8', 'UTF-8');
            return trim(is_string($cleaned) ? $cleaned : $output);
        } catch (\Throwable) {
            return '';
        }
    }

    protected function normaliseSuburb(?string $s): ?string
    {
        return TrackedProperty::normaliseSuburb($s);
    }

    /** Strip "R", commas, whitespace from a price string. Returns float|null. */
    protected function parsePrice(?string $raw): ?float
    {
        if ($raw === null || $raw === '') return null;
        $digits = preg_replace('/[^\d.]/', '', (string) $raw);
        if ($digits === '' || $digits === '.') return null;
        return (float) $digits;
    }

    protected function parseDate(?string $raw): ?string
    {
        if (!$raw) return null;
        try {
            return \Carbon\Carbon::parse($raw)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function findHeader(string $haystack, string $needle): bool
    {
        return stripos($haystack, $needle) !== false;
    }

    /**
     * Heuristic shared by all CMA Info variants — "Powered by CMA Info" or
     * "CMA Info" appears in the file. Returns false when the file looks
     * like another vendor.
     */
    protected function looksLikeCmaInfo(string $text): bool
    {
        if ($text === '') return false;
        return stripos($text, 'CMA Info') !== false
            || stripos($text, 'CMAinfo') !== false
            || stripos($text, 'CMA INFO') !== false;
    }

    protected function pageCount(string $text): int
    {
        if ($text === '') return 0;
        // pdftotext separates pages with form-feed (\f).
        return substr_count($text, "\f") + 1;
    }

    /**
     * Used by every CMA parser to seed an extracted-address record from a
     * subject-property or comparable-sale line. The orchestrator then routes
     * these through TrackedPropertyMatchOrCreateService with
     * source_type='cmainfo'.
     */
    protected function makeAddress(array $bits): array
    {
        return array_filter([
            'street_number' => $bits['street_number'] ?? null,
            'street_name'   => $bits['street_name'] ?? null,
            'suburb'        => $bits['suburb'] ?? null,
            'town'          => $bits['town'] ?? null,
            'latitude'      => $bits['latitude'] ?? null,
            'longitude'     => $bits['longitude'] ?? null,
            'erf_number'    => $bits['erf_number'] ?? null,
            'sale_price'    => $bits['sale_price'] ?? null,
            'sale_date'     => $bits['sale_date'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    /** Subclasses override. */
    abstract public function canParse(string $filePath): ParserConfidence;
    abstract public function getReportTypeKey(): string;
}
