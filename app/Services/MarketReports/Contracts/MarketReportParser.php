<?php

declare(strict_types=1);

namespace App\Services\MarketReports\Contracts;

use App\Models\MarketReports\MarketReport;
use App\Services\MarketReports\DTOs\MarketReportParseResult;
use App\Services\MarketReports\DTOs\ParserConfidence;

/**
 * The contract every CMA / market-report parser implements.
 *
 * Auto-detection: the upload controller iterates registered parsers and picks
 * the highest canParse() score. The GenericFallbackParser anchors the
 * collection with score=0.1 so something always matches.
 *
 * Parsers MUST be pure: read the file, return data, never write to the DB.
 * The orchestrating job (ParseMarketReportJob) persists the result and feeds
 * extracted addresses through TrackedPropertyMatchOrCreateService.
 *
 * Spec: .ai/specs/mic-complete-spec.md §8.2.
 */
interface MarketReportParser
{
    /**
     * Inspect the file and report whether this parser can handle it.
     * 0.0 = definitely not. 1.0 = definitely this format. Anything between
     * is a confidence weight — the upload pipeline picks the highest.
     */
    public function canParse(string $filePath): ParserConfidence;

    /**
     * Extract structured data from the file. No DB writes — the caller
     * orchestrates persistence.
     */
    public function parse(string $filePath, MarketReport $report): MarketReportParseResult;

    /** Parser semver — bumped when extraction logic changes meaningfully. */
    public function getVersion(): string;

    /** Stable key matching market_report_types.key (e.g. 'cma_info_market_analysis'). */
    public function getReportTypeKey(): string;
}
