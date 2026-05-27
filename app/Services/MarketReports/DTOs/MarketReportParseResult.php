<?php

declare(strict_types=1);

namespace App\Services\MarketReports\DTOs;

/**
 * Pure-data result of MarketReportParser::parse(). The job that orchestrates
 * the parse persists $dataPoints to market_data_points and feeds
 * $extractedAddresses through TrackedPropertyMatchOrCreateService.
 *
 * Parsers never write to the DB themselves.
 *
 * Spec: .ai/specs/mic-complete-spec.md §8.2.
 */
final class MarketReportParseResult
{
    /**
     * @param array<int, array<string, mixed>> $dataPoints  Raw point rows to insert into market_data_points.
     *        Recognised keys per row: tracked_property_id, suburb_normalised,
     *        town, metric_key, metric_value_numeric, metric_value_date,
     *        metric_value_string, metric_date, confidence.
     * @param array<int, array<string, mixed>> $extractedAddresses  Address records suitable for
     *        TrackedPropertyMatchOrCreateService::matchOrCreate() (source_type
     *        set to 'cmainfo' by the job).
     * @param array<string, mixed> $rawJson  The raw extraction payload — kept on
     *        market_reports.raw_extracted_json for debugging.
     * @param array<string, mixed> $subjectMeta  Subject-property metadata written
     *        back to the market_reports row (Phase 3a). Recognised keys:
     *        subject_address, subject_scheme_name, subject_section_number,
     *        subject_latitude, subject_longitude, subject_extent_m2, radius_metres.
     * @param array<int, array<string, mixed>> $compRows  Per-row evidence written
     *        to market_report_comp_rows (Phase 3a). Recognised keys map 1:1
     *        with the table columns; raw_row_json is the un-normalised payload.
     * @param array<int, array<string, mixed>> $schemeOwners  Owner entries from
     *        Scheme Owners List reports (Phase 3a) → scheme_owners table.
     */
    public function __construct(
        public readonly array $dataPoints = [],
        public readonly array $extractedAddresses = [],
        public readonly array $rawJson = [],
        public readonly array $subjectMeta = [],
        public readonly array $compRows = [],
        public readonly array $schemeOwners = [],
    ) {}

    public function withDataPoint(array $point): self
    {
        return new self(
            dataPoints:        [...$this->dataPoints, $point],
            extractedAddresses: $this->extractedAddresses,
            rawJson:           $this->rawJson,
            subjectMeta:       $this->subjectMeta,
            compRows:          $this->compRows,
            schemeOwners:      $this->schemeOwners,
        );
    }
}
