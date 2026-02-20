<?php

namespace Tests\Unit\MarketAnalytics;

use App\Services\MarketAnalytics\Metrics\PricePerSqmDeviationMetric;
use PHPUnit\Framework\TestCase;

class PricePerSqmDeviationMetricTest extends TestCase
{
    private function metric(): PricePerSqmDeviationMetric
    {
        return new PricePerSqmDeviationMetric();
    }

    /**
     * Build a minimal comp row with sold_price_inc and optional size_m2.
     */
    private function makeComp(float $price, ?int $size = null): array
    {
        return [
            'sold_price_inc' => $price,
            'size_m2'        => $size,
            'sold_date'      => '2024-01-15',
            'suburb_slug'    => 'test-suburb',
            'row_hash'       => hash('sha256', (string)$price . (string)$size),
        ];
    }

    // -------------------------------------------------------------------------
    // Formula name is pinned
    // -------------------------------------------------------------------------

    public function test_formula_name_is_pinned(): void
    {
        $result = $this->metric()->compute(100, 500000.0, [], 'hash');
        $this->assertSame('price_sqm_deviation_v1', $result['breakdown']['formula_name']);
        $this->assertSame('price_sqm_deviation_v1', PricePerSqmDeviationMetric::FORMULA_NAME);
    }

    // -------------------------------------------------------------------------
    // Normal computation
    // -------------------------------------------------------------------------

    public function test_computes_positive_deviation(): void
    {
        // Comps price/sqm: [4000, 5000, 6000] → median=5000
        // Subject: 600000 / 100 = 6000 → deviation = +20.0%
        $comps = [
            $this->makeComp(400000.0, 100),
            $this->makeComp(500000.0, 100),
            $this->makeComp(600000.0, 100),
        ];

        $result = $this->metric()->compute(100, 600000.0, $comps, 'hash');

        $this->assertNull($result['skip_reason']);
        $this->assertSame(20.0, $result['value']);
    }

    public function test_computes_negative_deviation(): void
    {
        // Same comps (median=5000), subject 4000/sqm → -20.0%
        $comps = [
            $this->makeComp(400000.0, 100),
            $this->makeComp(500000.0, 100),
            $this->makeComp(600000.0, 100),
        ];

        $result = $this->metric()->compute(100, 400000.0, $comps, 'hash');

        $this->assertNull($result['skip_reason']);
        $this->assertSame(-20.0, $result['value']);
    }

    public function test_computes_zero_deviation_when_equal_to_median(): void
    {
        // Subject at exactly median price/sqm → 0.0%
        $comps = [
            $this->makeComp(400000.0, 100),
            $this->makeComp(500000.0, 100),
            $this->makeComp(600000.0, 100),
        ];

        $result = $this->metric()->compute(100, 500000.0, $comps, 'hash');

        $this->assertNull($result['skip_reason']);
        $this->assertSame(0.0, $result['value']);
    }

    public function test_median_for_even_comp_count(): void
    {
        // price/sqm: [4000, 5000, 6000, 7000] → median = (5000+6000)/2 = 5500
        // Subject: 5500/sqm (size=100, price=550000) → deviation=0
        $comps = [
            $this->makeComp(400000.0, 100),
            $this->makeComp(500000.0, 100),
            $this->makeComp(600000.0, 100),
            $this->makeComp(700000.0, 100),
        ];

        $result = $this->metric()->compute(100, 550000.0, $comps, 'hash');

        $this->assertNull($result['skip_reason']);
        $this->assertSame(0.0, $result['value']);
        $this->assertSame(5500.0, $result['breakdown']['median_comp_price_per_sqm']);
    }

    public function test_result_rounded_to_4_decimal_places(): void
    {
        // Comps: [3700, 4700, 5700] → median = 4700
        // Subject: 510000/100 = 5100; deviation = (400/4700)*100 = 8.51063...
        // round(8.51063..., 4) = 8.5106
        $comps = [
            $this->makeComp(370000.0, 100),
            $this->makeComp(470000.0, 100),
            $this->makeComp(570000.0, 100),
        ];

        $result = $this->metric()->compute(100, 510000.0, $comps, 'hash');

        $this->assertSame(8.5106, $result['value']);
    }

    // -------------------------------------------------------------------------
    // Median correctness
    // -------------------------------------------------------------------------

    public function test_median_odd_count_is_middle_value(): void
    {
        // price/sqm: [3000, 5000, 7000] → median = 5000 (not average)
        $comps = [
            $this->makeComp(300000.0, 100),
            $this->makeComp(700000.0, 100),
            $this->makeComp(500000.0, 100),  // intentionally unordered
        ];

        $result = $this->metric()->compute(100, 500000.0, $comps, 'hash');

        $this->assertNull($result['skip_reason']);
        $this->assertSame(5000.0, $result['breakdown']['median_comp_price_per_sqm']);
        $this->assertSame(0.0,    $result['value']);
    }

    public function test_median_even_count_is_average_of_two_middles(): void
    {
        // price/sqm: [2000, 4000, 6000, 8000] → median = (4000+6000)/2 = 5000
        $comps = [
            $this->makeComp(200000.0, 100),
            $this->makeComp(400000.0, 100),
            $this->makeComp(600000.0, 100),
            $this->makeComp(800000.0, 100),
        ];

        $result = $this->metric()->compute(100, 500000.0, $comps, 'hash');

        $this->assertSame(5000.0, $result['breakdown']['median_comp_price_per_sqm']);
        $this->assertSame(0.0,    $result['value']);
    }

    // -------------------------------------------------------------------------
    // Determinism
    // -------------------------------------------------------------------------

    public function test_same_inputs_produce_identical_output(): void
    {
        $comps = [
            $this->makeComp(400000.0, 100),
            $this->makeComp(500000.0, 100),
            $this->makeComp(600000.0, 100),
        ];

        $m  = $this->metric();
        $r1 = $m->compute(100, 600000.0, $comps, 'h');
        $r2 = $m->compute(100, 600000.0, $comps, 'h');

        $this->assertSame($r1, $r2);
    }

    public function test_comp_order_does_not_affect_median(): void
    {
        // Comps in two orderings → same median → same deviation
        $r1 = $this->metric()->compute(100, 600000.0, [
            $this->makeComp(400000.0, 100),
            $this->makeComp(500000.0, 100),
            $this->makeComp(600000.0, 100),
        ], 'h');

        $r2 = $this->metric()->compute(100, 600000.0, [
            $this->makeComp(600000.0, 100),
            $this->makeComp(400000.0, 100),
            $this->makeComp(500000.0, 100),
        ], 'h');

        $this->assertSame($r1['value'], $r2['value']);
        $this->assertSame($r1['breakdown']['median_comp_price_per_sqm'],
                          $r2['breakdown']['median_comp_price_per_sqm']);
    }

    // -------------------------------------------------------------------------
    // Skip conditions
    // -------------------------------------------------------------------------

    public function test_skip_subject_size_missing_when_size_null(): void
    {
        $comps  = [$this->makeComp(500000.0, 100), $this->makeComp(600000.0, 100), $this->makeComp(700000.0, 100)];
        $result = $this->metric()->compute(null, 500000.0, $comps, 'h');

        $this->assertNull($result['value']);
        $this->assertSame('subject_size_missing', $result['skip_reason']);
    }

    public function test_skip_subject_size_missing_when_price_null(): void
    {
        $comps  = [$this->makeComp(500000.0, 100), $this->makeComp(600000.0, 100), $this->makeComp(700000.0, 100)];
        $result = $this->metric()->compute(100, null, $comps, 'h');

        $this->assertNull($result['value']);
        $this->assertSame('subject_size_missing', $result['skip_reason']);
    }

    public function test_skip_subject_size_missing_when_size_zero(): void
    {
        $comps  = [$this->makeComp(500000.0, 100), $this->makeComp(600000.0, 100), $this->makeComp(700000.0, 100)];
        $result = $this->metric()->compute(0, 500000.0, $comps, 'h');

        $this->assertNull($result['value']);
        $this->assertSame('subject_size_missing', $result['skip_reason']);
    }

    public function test_skip_insufficient_comp_sizes_when_no_comps_have_size(): void
    {
        $comps = [
            $this->makeComp(500000.0, null),
            $this->makeComp(600000.0, null),
            $this->makeComp(700000.0, null),
        ];

        $result = $this->metric()->compute(100, 500000.0, $comps, 'h');

        $this->assertNull($result['value']);
        $this->assertSame('insufficient_comp_sizes', $result['skip_reason']);
        $this->assertSame(0, $result['breakdown']['comps_with_size_count']);
    }

    public function test_skip_insufficient_comp_sizes_when_only_2_comps_have_size(): void
    {
        $comps = [
            $this->makeComp(400000.0, 100),
            $this->makeComp(500000.0, 100),
            $this->makeComp(600000.0, null),  // no size
        ];

        $result = $this->metric()->compute(100, 500000.0, $comps, 'h');

        $this->assertNull($result['value']);
        $this->assertSame('insufficient_comp_sizes', $result['skip_reason']);
        $this->assertSame(2, $result['breakdown']['comps_with_size_count']);
    }

    public function test_three_comps_with_size_is_sufficient(): void
    {
        $comps = [
            $this->makeComp(400000.0, 100),
            $this->makeComp(500000.0, 100),
            $this->makeComp(600000.0, 100),
        ];

        $result = $this->metric()->compute(100, 600000.0, $comps, 'h');

        $this->assertSame(3, $result['breakdown']['comps_with_size_count']);
        $this->assertNull($result['skip_reason']);
    }

    public function test_skip_insufficient_comp_sizes_when_no_comps_at_all(): void
    {
        $result = $this->metric()->compute(100, 500000.0, [], 'h');

        $this->assertNull($result['value']);
        $this->assertSame('insufficient_comp_sizes', $result['skip_reason']);
    }

    // -------------------------------------------------------------------------
    // Comps without size are silently excluded
    // -------------------------------------------------------------------------

    public function test_comps_without_size_excluded_from_median(): void
    {
        // 3 comps with size (median=5000) + 1 without — excluded comp should not affect result
        $comps = [
            $this->makeComp(400000.0, 100),
            $this->makeComp(500000.0, 100),
            $this->makeComp(600000.0, 100),
            $this->makeComp(999999.0, null),   // no size → excluded
        ];

        $result = $this->metric()->compute(100, 500000.0, $comps, 'h');

        $this->assertNull($result['skip_reason']);
        $this->assertSame(3,      $result['breakdown']['comps_with_size_count']);
        $this->assertSame(5000.0, $result['breakdown']['median_comp_price_per_sqm']);
    }

    public function test_comp_with_zero_size_excluded(): void
    {
        $comps = [
            $this->makeComp(400000.0, 100),
            $this->makeComp(500000.0, 100),
            $this->makeComp(600000.0, 100),
            $this->makeComp(999999.0, 0),   // zero size → excluded (division by zero guard)
        ];

        $result = $this->metric()->compute(100, 600000.0, $comps, 'h');

        $this->assertSame(3, $result['breakdown']['comps_with_size_count']);
    }

    // -------------------------------------------------------------------------
    // Never return 0 on missing data
    // -------------------------------------------------------------------------

    public function test_does_not_return_zero_on_missing_subject_size(): void
    {
        $result = $this->metric()->compute(null, 500000.0, [], 'h');

        $this->assertNull($result['value'], 'Expected null skip, not 0.0');
        $this->assertNotSame(0.0, $result['value']);
    }

    public function test_does_not_return_zero_on_no_comp_sizes(): void
    {
        $comps  = [$this->makeComp(500000.0, null), $this->makeComp(600000.0, null), $this->makeComp(700000.0, null)];
        $result = $this->metric()->compute(100, 500000.0, $comps, 'h');

        $this->assertNull($result['value'], 'Expected null skip, not 0.0');
        $this->assertNotSame(0.0, $result['value']);
    }

    // -------------------------------------------------------------------------
    // Breakdown structure
    // -------------------------------------------------------------------------

    public function test_breakdown_has_all_required_fields(): void
    {
        $comps  = [$this->makeComp(400000.0, 100), $this->makeComp(500000.0, 100), $this->makeComp(600000.0, 100)];
        $result = $this->metric()->compute(100, 600000.0, $comps, 'abc123');
        $bd     = $result['breakdown'];

        foreach ([
            'formula_name', 'subject_size_m2', 'subject_price_inc',
            'subject_price_per_sqm', 'comps_with_size_count',
            'median_comp_price_per_sqm', 'deviation_pct',
            'comps_hash', 'value', 'skip_reason',
        ] as $key) {
            $this->assertArrayHasKey($key, $bd, "Missing breakdown key: $key");
        }

        $this->assertSame('abc123', $bd['comps_hash']);
        $this->assertSame(100,      $bd['subject_size_m2']);
        $this->assertSame(600000.0, $bd['subject_price_inc']);
    }

    public function test_breakdown_is_fully_populated_on_skip(): void
    {
        $result = $this->metric()->compute(null, null, [], 'hash999');
        $bd     = $result['breakdown'];

        $this->assertSame('price_sqm_deviation_v1', $bd['formula_name']);
        $this->assertSame('subject_size_missing',   $bd['skip_reason']);
        $this->assertNull($bd['value']);
        $this->assertNull($bd['subject_price_per_sqm']);
        $this->assertNull($bd['median_comp_price_per_sqm']);
        $this->assertNull($bd['deviation_pct']);
    }

    public function test_breakdown_subject_price_per_sqm_matches_formula(): void
    {
        // 600000 / 100 = 6000.00
        $comps  = [$this->makeComp(400000.0, 100), $this->makeComp(500000.0, 100), $this->makeComp(600000.0, 100)];
        $result = $this->metric()->compute(100, 600000.0, $comps, 'h');

        $this->assertSame(6000.0, $result['breakdown']['subject_price_per_sqm']);
    }

    public function test_breakdown_deviation_pct_matches_value(): void
    {
        $comps  = [$this->makeComp(400000.0, 100), $this->makeComp(500000.0, 100), $this->makeComp(600000.0, 100)];
        $result = $this->metric()->compute(100, 600000.0, $comps, 'h');

        $this->assertSame($result['value'], $result['breakdown']['deviation_pct']);
        $this->assertSame($result['value'], $result['breakdown']['value']);
    }

    public function test_comps_hash_stored_in_breakdown_on_skip(): void
    {
        // Even when subject is missing, comps_hash should be in breakdown
        $result = $this->metric()->compute(null, null, [], 'xyz789');

        $this->assertSame('xyz789', $result['breakdown']['comps_hash']);
    }
}
