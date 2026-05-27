<?php

declare(strict_types=1);

namespace App\Services\MarketReports\Parsers;

use App\Models\MarketReports\MarketReport;
use App\Services\MarketReports\DTOs\MarketReportParseResult;
use App\Services\MarketReports\DTOs\ParserConfidence;

/**
 * Always-wins-the-tiebreaker fallback parser. Files the report, extracts
 * raw text for auditing, persists ZERO data points. The "we don't recognise
 * this format but we'll keep your upload" path.
 *
 * Always reports confidence 0.1 so it never beats a real parser but always
 * has something to return when the upload pipeline iterates parsers and
 * none match.
 *
 * Spec: .ai/specs/mic-complete-spec.md §8.3.
 */
final class GenericFallbackParser extends AbstractCmaInfoParser
{
    public function getReportTypeKey(): string
    {
        return 'other';
    }

    public function canParse(string $filePath): ParserConfidence
    {
        if (!is_file($filePath)) return ParserConfidence::none('file missing');
        return new ParserConfidence(0.1, ['generic fallback — always available']);
    }

    public function parse(string $filePath, MarketReport $report): MarketReportParseResult
    {
        $text = $this->extractText($filePath);
        return new MarketReportParseResult(
            dataPoints: [],
            extractedAddresses: [],
            rawJson: [
                'parser_note' => 'Generic fallback — no structured extraction performed.',
                'pages'       => $this->pageCount($text),
                'first_500'   => mb_substr($text, 0, 500),
                'bytes'       => is_file($filePath) ? filesize($filePath) : 0,
            ],
        );
    }
}
