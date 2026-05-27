<?php

namespace App\Services\MarketAnalytics\Adapters;

use App\Services\MarketAnalytics\Contracts\HasSourceRecord;
use App\Services\MarketAnalytics\Contracts\SoldTransactionsSource;
use App\Services\MarketAnalytics\DTOs\SoldTransactionsFilter;
use App\Services\MarketAnalytics\Helpers\QueryHasher;
use App\Services\MarketAnalytics\Support\SourceRecord;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Returns completed (registered) internal deals as sold transaction records.
 *
 * Field mapping from deals table:
 *   sold_date      ← registration_date  (canonical settled date; only registered deals returned)
 *   sold_price_inc ← property_value     (stored INCL VAT — confirmed by Deal model docblock)
 *   suburb_slug    ← filter value       (deals have no structured suburb field; we LIKE-match on
 *                                         property_address and then return the filter's slug as
 *                                         the canonical suburb identifier)
 *   property_type  ← null               (not stored in deals table)
 *   bedrooms       ← null               (not stored in deals table)
 *   listed_date    ← null               (not stored in deals table)
 */
class InternalDealsAdapter implements SoldTransactionsSource, HasSourceRecord
{
    public const SOURCE_TAG = 'internal_deals_v1';

    private ?SourceRecord $lastSourceRecord = null;

    public function getRecords(SoldTransactionsFilter $filter): Collection
    {
        // De-slug for LIKE matching: 'north-shore' → 'north shore'
        $suburbName = mb_strtolower(str_replace('-', ' ', $filter->suburbSlug));

        // Phase 3i — when property_id is populated, prefer joining via the FK
        // and filtering by the linked property's suburb. Falls back to the
        // legacy LOWER(property_address) LIKE when property_id is null. Two
        // unioned subqueries would be cleaner but adds query-hash churn for
        // listeners; instead we expand the WHERE.
        $query = DB::table('deals')
            ->leftJoin('properties', 'properties.id', '=', 'deals.property_id')
            ->whereNotNull('deals.registration_date')
            ->where(function ($q) {
                // Exclude declined deals
                $q->whereNull('deals.accepted_status')
                  ->orWhere('deals.accepted_status', '!=', 'D');
            })
            // Phase 3h Step 9 — demo/real isolation.
            ->where('deals.is_demo', $filter->subjectIsDemo)
            ->whereBetween('deals.registration_date', [$filter->dateFrom, $filter->dateTo])
            ->where(function ($q) use ($suburbName) {
                // Linked path: match the FK property's suburb (case-insensitive).
                $q->whereRaw('LOWER(properties.suburb) = ?', [$suburbName])
                  // Fallback: legacy address-LIKE for unlinked deals.
                  ->orWhere(function ($qq) use ($suburbName) {
                      $qq->whereNull('deals.property_id')
                         ->whereRaw('LOWER(deals.property_address) LIKE ?', ['%' . $suburbName . '%']);
                  });
            })
            ->select([
                'deals.id', 'deals.registration_date',
                'deals.property_value', 'deals.sale_price',
                'deals.property_address', 'deals.property_id',
            ]);

        if ($filter->branchId !== null) {
            $query->where('deals.branch_id', $filter->branchId);
        }

        // Capture stable query hash before execution
        $qHash = QueryHasher::hash($query->toSql(), $query->getBindings());

        $deals = $query->get();

        $this->lastSourceRecord = new SourceRecord(
            sourceTag: self::SOURCE_TAG,
            rowCount:  $deals->count(),
            queryHash: $qHash,
        );

        return $deals->map(fn ($deal) => [
            'source_tag'     => self::SOURCE_TAG,
            'deal_id'        => $deal->id,
            'sold_date'      => $deal->registration_date,   // Y-m-d string from DB
            // Phase 3i — prefer canonical sale_price, fall back to legacy decimal.
            'sold_price_inc' => (float) ($deal->sale_price ?? $deal->property_value ?? 0),
            'suburb_slug'    => $filter->suburbSlug,
            'property_type'  => null,
            'bedrooms'       => null,
            'listed_date'    => null,
            'property_id'    => $deal->property_id ? (int) $deal->property_id : null,
            'hfc_sold'       => true,  // every row from this adapter is HFC-internal
        ]);
    }

    public function getLastSourceRecord(): ?SourceRecord
    {
        return $this->lastSourceRecord;
    }
}
