<?php

declare(strict_types=1);

namespace App\Services\MarketAnalytics\Adapters;

use App\Services\MarketAnalytics\Contracts\HasSourceRecord;
use App\Services\MarketAnalytics\Contracts\SoldTransactionsSource;
use App\Services\MarketAnalytics\DTOs\SoldTransactionsFilter;
use App\Services\MarketAnalytics\Helpers\QueryHasher;
use App\Services\MarketAnalytics\Support\SourceRecord;
use App\Support\MarketAnalytics\HaversineDistance;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Phase 3b — sold-comp source backed by `market_report_comp_rows` (MIC).
 *
 * Sits between InternalDealsAdapter (primary, HFC's own deals) and the
 * legacy presentation_sold_comps fallback. Reads agency-wide CMA Info data
 * that the MIC importer has accumulated.
 *
 * Scope branching (driven by filter):
 *   - radius_all  → Haversine match within compRadiusM of subject lat/lng
 *                   (falls back to suburb match for rows missing geo).
 *   - suburb_only → suburb_normalised match only (legacy semantic).
 *
 * No agency scope on the read: market_report_comp_rows.agency_id is audit-
 * only per the MIC shared-pool design (mic-complete-spec §13). Every agency
 * benefits from every CMA Info import across the system.
 */
final class MarketCompRowsSoldAdapter implements SoldTransactionsSource, HasSourceRecord
{
    public const SOURCE_TAG = 'market_report_comp_rows_sold_v1';

    private ?SourceRecord $lastSourceRecord = null;

    public function getRecords(SoldTransactionsFilter $filter): Collection
    {
        $query = DB::table('market_report_comp_rows')
            ->whereNull('deleted_at')
            ->where('row_type', 'comp')
            ->whereNotNull('sale_date')
            ->whereNotNull('sale_price')
            ->whereBetween('sale_date', [$filter->dateFrom, $filter->dateTo])
            ->select([
                'id', 'market_report_id', 'sale_date', 'sale_price',
                'suburb_normalised', 'scheme_name', 'section_number',
                'property_type', 'extent_m2', 'latitude', 'longitude',
                'address',
            ]);

        $rows = $query->get();

        // Filter in PHP based on scope. We pull the full date-windowed set
        // first (cheap due to the suburb-date index) and let scope decide
        // which rows match.
        $rows = $this->applyScope($rows, $filter);
        $rows = $this->applyPropertyTypeFilter($rows, $filter->propertyType);

        $qHash = QueryHasher::hash(
            $query->toSql() . '|scope:' . $filter->compScope . '|r:' . $filter->compRadiusM,
            $query->getBindings(),
        );

        $this->lastSourceRecord = new SourceRecord(
            sourceTag: self::SOURCE_TAG,
            rowCount:  $rows->count(),
            queryHash: $qHash,
        );

        return $rows->map(fn ($row) => [
            'source_tag'     => self::SOURCE_TAG,
            'deal_id'        => 'mrcr_' . $row->id,
            'sold_date'      => $row->sale_date,
            'sold_price_inc' => (float) ($row->sale_price ?? 0),
            'suburb_slug'    => $filter->suburbSlug,
            'property_type'  => $row->property_type ?? null,
            'bedrooms'       => null,  // not captured in market_report_comp_rows
            'listed_date'    => null,
        ]);
    }

    public function getLastSourceRecord(): ?SourceRecord
    {
        return $this->lastSourceRecord;
    }

    /**
     * Apply the comp-scope filter:
     *   - radius_all  → Haversine within compRadiusM (suburb fallback when geo missing)
     *   - suburb_only → suburb_normalised LIKE %suburb%
     */
    private function applyScope(Collection $rows, SoldTransactionsFilter $filter): Collection
    {
        $suburbNeedle = mb_strtolower(str_replace('-', ' ', $filter->suburbSlug));

        if ($filter->compScope === SoldTransactionsFilter::SCOPE_SUBURB_ONLY) {
            return $rows->filter(fn ($row) => $this->matchesSuburb($row->suburb_normalised, $suburbNeedle))->values();
        }

        // radius_all
        $haveSubjectGeo = $filter->subjectLatitude !== null && $filter->subjectLongitude !== null;
        $radius = max(1, $filter->compRadiusM);

        return $rows->filter(function ($row) use ($haveSubjectGeo, $filter, $suburbNeedle, $radius) {
            // Row has geo + subject has geo → Haversine.
            if ($haveSubjectGeo && $row->latitude !== null && $row->longitude !== null) {
                $d = HaversineDistance::distanceMetres(
                    (float) $filter->subjectLatitude,
                    (float) $filter->subjectLongitude,
                    (float) $row->latitude,
                    (float) $row->longitude,
                );
                return $d <= $radius;
            }
            // Missing geo on either side → suburb match (graceful degrade).
            return $this->matchesSuburb($row->suburb_normalised, $suburbNeedle);
        })->values();
    }

    private function matchesSuburb(?string $rowSuburb, string $needle): bool
    {
        if ($needle === '') return true;
        if (!is_string($rowSuburb) || $rowSuburb === '') return false;
        return str_contains(mb_strtolower($rowSuburb), $needle);
    }

    /**
     * Loose property-type alignment. CMA Info uses "Residence" — we map that
     * to the engine's "house" / "unit" buckets via simple substring sniff.
     * When the filter type is empty, accept all rows.
     */
    private function applyPropertyTypeFilter(Collection $rows, string $type): Collection
    {
        $type = strtolower(trim($type));
        if ($type === '' || $type === 'other') return $rows;

        return $rows->filter(function ($row) use ($type) {
            $rowType = strtolower((string) ($row->property_type ?? ''));
            if ($rowType === '' || $rowType === 'residence') return true;
            return str_contains($rowType, $type);
        })->values();
    }
}
