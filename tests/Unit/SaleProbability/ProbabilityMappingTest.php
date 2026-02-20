<?php

namespace Tests\Unit\SaleProbability;

use App\Services\SaleProbability\Support\ModelConfig;
use App\Services\SaleProbability\Support\ProbabilityMapper;
use PHPUnit\Framework\TestCase;

class ProbabilityMappingTest extends TestCase
{
    private function map(float $score): array
    {
        return ProbabilityMapper::map($score);
    }

    // ── Return shape ──────────────────────────────────────────────────────────

    public function test_returns_array_with_expected_keys(): void
    {
        $result = $this->map(0.5);
        $this->assertArrayHasKey('p30', $result);
        $this->assertArrayHasKey('p60', $result);
        $this->assertArrayHasKey('p90', $result);
    }

    public function test_all_values_are_floats(): void
    {
        $result = $this->map(0.5);
        $this->assertIsFloat($result['p30']);
        $this->assertIsFloat($result['p60']);
        $this->assertIsFloat($result['p90']);
    }

    // ── Monotonic invariant ───────────────────────────────────────────────────

    public function test_monotonic_p30_le_p60_le_p90_at_various_scores(): void
    {
        foreach ([0.0, 0.1, 0.25, 0.3, 0.5, 0.7, 0.9, 1.0] as $score) {
            $r = $this->map($score);
            $this->assertLessThanOrEqual($r['p60'], $r['p30'], "p30 ≤ p60 failed at score=$score");
            $this->assertLessThanOrEqual($r['p90'], $r['p60'], "p60 ≤ p90 failed at score=$score");
        }
    }

    // ── Anchor / known values ─────────────────────────────────────────────────

    public function test_p60_at_centre_is_half(): void
    {
        // P60_CENTRE = 0.5, steepness=7 → sigmoid(0) = 0.5 → round(0.5,4) = 0.5
        $result = $this->map(ModelConfig::P60_CENTRE);
        $this->assertSame(0.5, $result['p60']);
    }

    public function test_p30_below_half_at_p60_centre(): void
    {
        // At score=0.5 (below P30_CENTRE=0.7), p30 < 0.5
        $result = $this->map(ModelConfig::P60_CENTRE);
        $this->assertLessThan(0.5, $result['p30']);
    }

    public function test_p90_above_half_at_p60_centre(): void
    {
        // At score=0.5 (above P90_CENTRE=0.3), p90 > 0.5
        $result = $this->map(ModelConfig::P60_CENTRE);
        $this->assertGreaterThan(0.5, $result['p90']);
    }

    public function test_at_score_one_all_probabilities_high(): void
    {
        $result = $this->map(1.0);
        $this->assertGreaterThan(0.9, $result['p30']);
        $this->assertGreaterThan(0.9, $result['p60']);
        $this->assertGreaterThan(0.9, $result['p90']);
    }

    public function test_at_score_zero_all_probabilities_low(): void
    {
        $result = $this->map(0.0);
        $this->assertLessThan(0.1, $result['p30']);
        $this->assertLessThan(0.2, $result['p60']);
        $this->assertLessThan(0.5, $result['p90']);
    }

    // ── Rounding ──────────────────────────────────────────────────────────────

    public function test_output_rounded_to_four_decimal_places(): void
    {
        // round($v, 4) should return the same value (i.e. already rounded)
        foreach ([0.0, 0.3, 0.5, 0.7, 1.0] as $score) {
            $result = $this->map($score);
            $this->assertSame(round($result['p30'], 4), $result['p30'], "p30 not 4dp at score=$score");
            $this->assertSame(round($result['p60'], 4), $result['p60'], "p60 not 4dp at score=$score");
            $this->assertSame(round($result['p90'], 4), $result['p90'], "p90 not 4dp at score=$score");
        }
    }

    // ── Monotonicity increases with score ─────────────────────────────────────

    public function test_p30_increases_with_score(): void
    {
        $low  = $this->map(0.3)['p30'];
        $high = $this->map(0.8)['p30'];
        $this->assertGreaterThan($low, $high);
    }

    public function test_p60_increases_with_score(): void
    {
        $low  = $this->map(0.2)['p60'];
        $high = $this->map(0.8)['p60'];
        $this->assertGreaterThan($low, $high);
    }

    public function test_p90_increases_with_score(): void
    {
        $low  = $this->map(0.1)['p90'];
        $high = $this->map(0.9)['p90'];
        $this->assertGreaterThan($low, $high);
    }

    // ── Determinism ──────────────────────────────────────────────────────────

    public function test_deterministic(): void
    {
        $first  = $this->map(0.65);
        $second = $this->map(0.65);
        $this->assertSame($first['p30'], $second['p30']);
        $this->assertSame($first['p60'], $second['p60']);
        $this->assertSame($first['p90'], $second['p90']);
    }

    // ── Values in bounds ─────────────────────────────────────────────────────

    public function test_all_values_between_zero_and_one(): void
    {
        foreach ([0.0, 0.25, 0.5, 0.75, 1.0] as $score) {
            $result = $this->map($score);
            foreach (['p30', 'p60', 'p90'] as $key) {
                $this->assertGreaterThanOrEqual(0.0, $result[$key], "$key out of bounds at score=$score");
                $this->assertLessThanOrEqual(1.0, $result[$key], "$key out of bounds at score=$score");
            }
        }
    }
}
