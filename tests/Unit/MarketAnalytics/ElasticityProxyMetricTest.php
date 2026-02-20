<?php

namespace Tests\Unit\MarketAnalytics;

use App\Services\MarketAnalytics\Metrics\ElasticityProxyMetric;
use PHPUnit\Framework\TestCase;

class ElasticityProxyMetricTest extends TestCase
{
    private function metric(): ElasticityProxyMetric
    {
        return new ElasticityProxyMetric();
    }

    /**
     * Build a minimal comp row with sold_price_inc and dom_days.
     * Pass listed_date/sold_date to test date-based DOM resolution;
     * dom_days=null + dates present → DOM is computed from dates.
     */
    private function makeComp(
        float   $price,
        ?int    $domDays,
        ?string $soldDate   = null,
        ?string $listedDate = null,
    ): array {
        $row = [
            'sold_price_inc' => $price,
            'sold_date'      => $soldDate   ?? '2024-06-15',
            'suburb_slug'    => 'test-suburb',
            'row_hash'       => hash('sha256', (string)$price . (string)$domDays),
        ];

        // Only include dom_days key if caller supplies it (null key omitted → force date path)
        if ($domDays !== null || ($listedDate === null && $soldDate === null)) {
            $row['dom_days'] = $domDays;
        }

        if ($listedDate !== null) {
            $row['listed_date'] = $listedDate;
        }

        return $row;
    }

    /**
     * 5-comp dataset where each comp lands in a different bucket and DOM forms
     * a perfect line y = 50 + 2x across midpoints [-20,-10,0,10,20].
     *
     * Prices: [420000, 450000, 500000, 550000, 580000] → median = 500000
     * Deviations: [-16%, -10%, 0%, +10%, +16%] → buckets [0,1,2,3,4]
     * DOM:        [10, 30, 50, 70, 90] → slope = 2.0, R² = 1.0
     */
    private function perfectLineComps(): array
    {
        return [
            $this->makeComp(420000.0, 10),   // bucket 0, midpoint -20
            $this->makeComp(450000.0, 30),   // bucket 1, midpoint -10
            $this->makeComp(500000.0, 50),   // bucket 2, midpoint  0
            $this->makeComp(550000.0, 70),   // bucket 3, midpoint +10
            $this->makeComp(580000.0, 90),   // bucket 4, midpoint +20
        ];
    }

    // -------------------------------------------------------------------------
    // Constants are pinned
    // -------------------------------------------------------------------------

    public function test_formula_name_is_pinned(): void
    {
        $result = $this->metric()->compute($this->perfectLineComps(), 'h');
        $this->assertSame('elasticity_proxy_v1', $result['breakdown']['formula_name']);
        $this->assertSame('elasticity_proxy_v1', ElasticityProxyMetric::FORMULA_NAME);
    }

    public function test_units_are_pinned(): void
    {
        $result = $this->metric()->compute($this->perfectLineComps(), 'h');
        $this->assertSame('days_per_pct', $result['breakdown']['units']);
        $this->assertSame('days_per_pct', ElasticityProxyMetric::UNITS);
    }

    // -------------------------------------------------------------------------
    // Normal computation — slope and R²
    // -------------------------------------------------------------------------

    public function test_computes_slope_for_perfect_line_dataset(): void
    {
        // y = 50 + 2x → slope = 2.0 exactly
        $result = $this->metric()->compute($this->perfectLineComps(), 'h');

        $this->assertNull($result['skip_reason']);
        $this->assertSame(2.0, $result['value']);
    }

    public function test_r_squared_is_1_for_perfect_line(): void
    {
        $result = $this->metric()->compute($this->perfectLineComps(), 'h');

        $this->assertSame(1.0, $result['breakdown']['r_squared']);
    }

    public function test_negative_slope_when_higher_price_sells_faster(): void
    {
        // Reverse DOM: cheap = slow, expensive = fast → slope < 0
        $comps = [
            $this->makeComp(420000.0, 90),   // bucket 0, high DOM
            $this->makeComp(450000.0, 70),   // bucket 1
            $this->makeComp(500000.0, 50),   // bucket 2
            $this->makeComp(550000.0, 30),   // bucket 3
            $this->makeComp(580000.0, 10),   // bucket 4, low DOM
        ];

        $result = $this->metric()->compute($comps, 'h');

        $this->assertNull($result['skip_reason']);
        $this->assertLessThan(0.0, $result['value']);
    }

    public function test_slope_rounded_to_4_decimal_places(): void
    {
        // Verify slope is rounded: round($slope, 4) === $slope
        $result = $this->metric()->compute($this->perfectLineComps(), 'h');

        $this->assertSame(round((float)$result['value'], 4), $result['value']);
    }

    public function test_r_squared_rounded_to_4_decimal_places(): void
    {
        $result = $this->metric()->compute($this->perfectLineComps(), 'h');

        $bd = $result['breakdown']['r_squared'];
        $this->assertSame(round((float)$bd, 4), $bd);
    }

    // -------------------------------------------------------------------------
    // Determinism
    // -------------------------------------------------------------------------

    public function test_same_inputs_produce_identical_output(): void
    {
        $m    = $this->metric();
        $comps = $this->perfectLineComps();

        $r1 = $m->compute($comps, 'hash-abc');
        $r2 = $m->compute($comps, 'hash-abc');

        $this->assertSame($r1, $r2);
    }

    public function test_comp_order_does_not_affect_result(): void
    {
        $comps    = $this->perfectLineComps();
        $shuffled = array_reverse($comps);

        $r1 = $this->metric()->compute($comps,    'h');
        $r2 = $this->metric()->compute($shuffled, 'h');

        $this->assertSame($r1['value'],                    $r2['value']);
        $this->assertSame($r1['breakdown']['r_squared'],   $r2['breakdown']['r_squared']);
        $this->assertSame($r1['breakdown']['median_price_inc'], $r2['breakdown']['median_price_inc']);
    }

    // -------------------------------------------------------------------------
    // Median correctness
    // -------------------------------------------------------------------------

    public function test_median_odd_comp_count_uses_middle_value(): void
    {
        // 5 prices: [420000, 450000, 500000, 550000, 580000] → median = 500000
        $result = $this->metric()->compute($this->perfectLineComps(), 'h');

        $this->assertSame(500000.0, $result['breakdown']['median_price_inc']);
    }

    public function test_median_even_comp_count_uses_average_of_two_middles(): void
    {
        // 6 prices (add 600000): [420000,450000,500000,550000,580000,600000]
        // median = (500000+550000)/2 = 525000
        $comps   = $this->perfectLineComps();
        $comps[] = $this->makeComp(600000.0, 45);   // extra comp, bucket 4 (dev=14.3%)

        $result = $this->metric()->compute($comps, 'h');

        $this->assertSame(525000.0, $result['breakdown']['median_price_inc']);
    }

    // -------------------------------------------------------------------------
    // Bucket boundary assignment
    // -------------------------------------------------------------------------

    public function test_deviation_exactly_minus15_goes_to_second_band(): void
    {
        // Prices: [425000,450000,500000,550000,600000] → median = 500000
        // 425000 → dev = -15% exactly → NOT < -15, so lands in band 1 "-15_to_-5"
        $comps = [
            $this->makeComp(425000.0, 30),   // dev = -15% → bucket 1
            $this->makeComp(450000.0, 30),   // dev = -10% → bucket 1
            $this->makeComp(500000.0, 30),   // dev =   0% → bucket 2
            $this->makeComp(550000.0, 30),   // dev = +10% → bucket 3
            $this->makeComp(600000.0, 30),   // dev = +20% → bucket 4
        ];

        $result = $this->metric()->compute($comps, 'h');
        $table  = $result['breakdown']['bucket_table'];

        $this->assertSame(0, $table[0]['count'], 'Bucket 0 should be empty (dev=-15% is NOT <-15)');
        $this->assertSame(2, $table[1]['count'], 'Bucket 1 should have 2 comps (-15% and -10%)');
    }

    public function test_deviation_below_minus15_goes_to_first_band(): void
    {
        // 420000 → dev = -16% → bucket 0 "<-15"
        $result = $this->metric()->compute($this->perfectLineComps(), 'h');
        $table  = $result['breakdown']['bucket_table'];

        $this->assertSame(1, $table[0]['count']);
        $this->assertSame('<-15', $table[0]['band_label']);
    }

    public function test_deviation_exactly_15_goes_to_fifth_band(): void
    {
        // Prices: [425000,450000,500000,550000,575000] → median = 500000
        // 575000 → dev = +15% exactly → lands in band 4 ">15" (≥ 15)
        $comps = [
            $this->makeComp(425000.0, 30),   // dev = -15% → bucket 1
            $this->makeComp(450000.0, 30),   // dev = -10% → bucket 1
            $this->makeComp(500000.0, 30),   // dev =   0% → bucket 2
            $this->makeComp(550000.0, 30),   // dev = +10% → bucket 3
            $this->makeComp(575000.0, 30),   // dev = +15% → bucket 4 (≥15)
        ];

        $result = $this->metric()->compute($comps, 'h');
        $table  = $result['breakdown']['bucket_table'];

        $this->assertSame(1, $table[4]['count'], 'Bucket 4 should contain the +15% comp');
        $this->assertSame('>15', $table[4]['band_label']);
    }

    public function test_deviation_exactly_0_goes_to_middle_band(): void
    {
        $result = $this->metric()->compute($this->perfectLineComps(), 'h');
        $table  = $result['breakdown']['bucket_table'];

        // comp at 500000 has dev=0% → bucket 2 "-5_to_5"
        $this->assertSame('-5_to_5', $table[2]['band_label']);
        $this->assertGreaterThanOrEqual(1, $table[2]['count']);
    }

    // -------------------------------------------------------------------------
    // Skip conditions
    // -------------------------------------------------------------------------

    public function test_skip_dom_unavailable_when_no_comps_have_dom(): void
    {
        $comps = [
            ['sold_price_inc' => 500000.0, 'row_hash' => 'a'],
            ['sold_price_inc' => 510000.0, 'row_hash' => 'b'],
            ['sold_price_inc' => 520000.0, 'row_hash' => 'c'],
            ['sold_price_inc' => 530000.0, 'row_hash' => 'd'],
            ['sold_price_inc' => 540000.0, 'row_hash' => 'e'],
        ];

        $result = $this->metric()->compute($comps, 'h');

        $this->assertNull($result['value']);
        $this->assertSame('dom_unavailable', $result['skip_reason']);
    }

    public function test_skip_dom_unavailable_when_no_comps_at_all(): void
    {
        $result = $this->metric()->compute([], 'h');

        $this->assertNull($result['value']);
        $this->assertSame('dom_unavailable', $result['skip_reason']);
    }

    public function test_skip_insufficient_samples_when_4_usable(): void
    {
        // 4 usable comps < minSamples (5)
        $comps = [
            $this->makeComp(420000.0, 10),
            $this->makeComp(450000.0, 30),
            $this->makeComp(500000.0, 50),
            $this->makeComp(550000.0, 70),
        ];

        $result = $this->metric()->compute($comps, 'h');

        $this->assertNull($result['value']);
        $this->assertSame('insufficient_samples', $result['skip_reason']);
        $this->assertSame(4, $result['breakdown']['usable_count']);
    }

    public function test_skip_insufficient_buckets_when_all_comps_in_one_bucket(): void
    {
        // 5 comps all at same price → deviation = 0% → all in bucket 2 → 1 non-empty < 3
        $comps = [
            $this->makeComp(500000.0, 30),
            $this->makeComp(500000.0, 40),
            $this->makeComp(500000.0, 50),
            $this->makeComp(500000.0, 60),
            $this->makeComp(500000.0, 70),
        ];

        $result = $this->metric()->compute($comps, 'h');

        $this->assertNull($result['value']);
        $this->assertSame('insufficient_buckets', $result['skip_reason']);
    }

    public function test_skip_order_dom_unavailable_beats_insufficient_samples(): void
    {
        // 0 comps → dom_unavailable wins (insufficient_samples would also apply)
        $result = $this->metric()->compute([], 'h');

        $this->assertSame('dom_unavailable', $result['skip_reason']);
    }

    // -------------------------------------------------------------------------
    // Never return 0 for missing data
    // -------------------------------------------------------------------------

    public function test_does_not_return_zero_on_dom_unavailable(): void
    {
        $result = $this->metric()->compute([], 'h');

        $this->assertNull($result['value'], 'Expected null skip, not 0.0');
        $this->assertNotSame(0.0, $result['value']);
    }

    public function test_does_not_return_zero_on_insufficient_samples(): void
    {
        $comps  = [$this->makeComp(500000.0, 30), $this->makeComp(510000.0, 40)];
        $result = $this->metric()->compute($comps, 'h');

        $this->assertNull($result['value'], 'Expected null skip, not 0.0');
        $this->assertNotSame(0.0, $result['value']);
    }

    // -------------------------------------------------------------------------
    // Comp exclusion
    // -------------------------------------------------------------------------

    public function test_comp_without_dom_days_counted_as_skipped(): void
    {
        // Add a comp with no dom_days and no dates → excluded
        $comps   = $this->perfectLineComps();
        $comps[] = ['sold_price_inc' => 500000.0, 'row_hash' => 'no-dom'];

        $result = $this->metric()->compute($comps, 'h');

        $this->assertNull($result['skip_reason']);
        $this->assertSame(5, $result['breakdown']['usable_count']);
        $this->assertSame(1, $result['breakdown']['skipped_count']);
    }

    public function test_comp_with_zero_price_excluded(): void
    {
        $comps   = $this->perfectLineComps();
        $comps[] = $this->makeComp(0.0, 50);

        $result = $this->metric()->compute($comps, 'h');

        $this->assertNull($result['skip_reason']);
        $this->assertSame(5, $result['breakdown']['usable_count']);
    }

    // -------------------------------------------------------------------------
    // DOM resolution from listed_date + sold_date
    // -------------------------------------------------------------------------

    public function test_dom_resolved_from_listed_and_sold_dates(): void
    {
        // Replace dom_days with date-based resolution: listed 2024-01-01, sold 2024-04-11 = 101 days
        $dateComp = [
            'sold_price_inc' => 500000.0,
            'sold_date'      => '2024-04-11',
            'listed_date'    => '2024-01-01',
            'row_hash'       => 'date-based',
        ];
        // No dom_days key → metric should compute from dates

        $comps = [
            $this->makeComp(420000.0, 10),
            $this->makeComp(450000.0, 30),
            $dateComp,                         // bucket 2, dom from dates
            $this->makeComp(550000.0, 70),
            $this->makeComp(580000.0, 90),
        ];

        $result = $this->metric()->compute($comps, 'h');

        // Should compute (not skip); bucket 2 avg_dom should reflect 101 days for that comp
        $this->assertNull($result['skip_reason']);
        $this->assertSame(5, $result['breakdown']['usable_count']);
        $this->assertSame(101.0, $result['breakdown']['bucket_table'][2]['avg_dom_days']);
    }

    // -------------------------------------------------------------------------
    // Breakdown structure
    // -------------------------------------------------------------------------

    public function test_breakdown_has_all_required_fields(): void
    {
        $result = $this->metric()->compute($this->perfectLineComps(), 'comps-hash-xyz');
        $bd     = $result['breakdown'];

        foreach ([
            'formula_name', 'units', 'comps_hash', 'usable_count', 'skipped_count',
            'median_price_inc', 'bucket_table', 'slope_days_per_pct', 'r_squared',
            'value', 'skip_reason',
        ] as $key) {
            $this->assertArrayHasKey($key, $bd, "Missing breakdown key: $key");
        }

        $this->assertSame('comps-hash-xyz', $bd['comps_hash']);
    }

    public function test_breakdown_bucket_table_always_has_5_entries(): void
    {
        $result = $this->metric()->compute($this->perfectLineComps(), 'h');
        $table  = $result['breakdown']['bucket_table'];

        $this->assertCount(5, $table);
    }

    public function test_breakdown_bucket_table_has_correct_labels(): void
    {
        $result = $this->metric()->compute($this->perfectLineComps(), 'h');
        $table  = $result['breakdown']['bucket_table'];

        $this->assertSame('<-15',      $table[0]['band_label']);
        $this->assertSame('-15_to_-5', $table[1]['band_label']);
        $this->assertSame('-5_to_5',   $table[2]['band_label']);
        $this->assertSame('5_to_15',   $table[3]['band_label']);
        $this->assertSame('>15',       $table[4]['band_label']);
    }

    public function test_breakdown_empty_bucket_has_null_avg_dom(): void
    {
        // perfectLineComps has 1 comp per bucket → on a skip-producing dataset with bucket 0 empty
        // Use 5 comps all in buckets 1-4 (no comp in bucket 0)
        $comps = [
            $this->makeComp(450000.0, 30),   // median=500000 → dev=-10% → bucket 1
            $this->makeComp(460000.0, 35),   // dev=-8% → bucket 1
            $this->makeComp(500000.0, 50),   // dev=0% → bucket 2
            $this->makeComp(550000.0, 70),   // dev=10% → bucket 3
            $this->makeComp(580000.0, 90),   // dev=16% → bucket 4
        ];

        $result = $this->metric()->compute($comps, 'h');
        $table  = $result['breakdown']['bucket_table'];

        $this->assertNull($table[0]['avg_dom_days'], 'Empty bucket 0 should have null avg_dom');
        $this->assertSame(0, $table[0]['count']);
    }

    public function test_breakdown_is_fully_populated_on_skip(): void
    {
        $result = $this->metric()->compute([], 'hash-skip');
        $bd     = $result['breakdown'];

        $this->assertSame('elasticity_proxy_v1', $bd['formula_name']);
        $this->assertSame('dom_unavailable',     $bd['skip_reason']);
        $this->assertNull($bd['value']);
        $this->assertNull($bd['slope_days_per_pct']);
        $this->assertNull($bd['r_squared']);
        $this->assertSame('hash-skip', $bd['comps_hash']);
        $this->assertCount(5, $bd['bucket_table']);
    }

    public function test_breakdown_bucket_table_has_no_internal_dom_sum_key(): void
    {
        $result = $this->metric()->compute($this->perfectLineComps(), 'h');
        $table  = $result['breakdown']['bucket_table'];

        foreach ($table as $bucket) {
            $this->assertArrayNotHasKey('_dom_sum', $bucket, '_dom_sum should be stripped before returning');
        }
    }
}
