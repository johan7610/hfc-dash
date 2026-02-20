<?php

namespace Tests\Unit\SaleProbability;

use App\Services\MarketAnalytics\DTOs\MarketAnalyticsResult;
use App\Services\SaleProbability\DTOs\SaleProbabilityInput;
use App\Services\SaleProbability\Signals\DemandSupplySignal;
use App\Services\SaleProbability\Support\ModelConfig;
use PHPUnit\Framework\TestCase;

class DemandSupplySignalTest extends TestCase
{
    private function signal(): DemandSupplySignal
    {
        return new DemandSupplySignal();
    }

    private function makeInput(?float $demandSupplyRatio): SaleProbabilityInput
    {
        $ma = MarketAnalyticsResult::empty();
        $ma->demandSupplyRatio = $demandSupplyRatio;

        return new SaleProbabilityInput(
            marketAnalyticsRunId:        1,
            marketAnalyticsModelVersion: 'v1.0.0',
            marketAnalyticsInputsHash:   'abc123',
            marketAnalyticsResult:       $ma,
        );
    }

    // ── Signal name & classification ─────────────────────────────────────────

    public function test_signal_name_is_pinned(): void
    {
        $this->assertSame('pressure', DemandSupplySignal::SIGNAL_NAME);
    }

    public function test_signal_is_required(): void
    {
        $this->assertTrue(DemandSupplySignal::REQUIRED);
    }

    // ── Anchor points ────────────────────────────────────────────────────────

    public function test_normalized_at_equilibrium(): void
    {
        // DSR = 1.0 → sigmoid((1.0 - 1.0) * 3) = sigmoid(0) = 0.5
        $result = $this->signal()->compute($this->makeInput(1.0));
        $this->assertFalse($result['skip']);
        $this->assertSame(0.5, $result['normalized']);
    }

    public function test_normalized_high_demand(): void
    {
        // DSR = 2.0 → sigmoid((2.0 - 1.0) * 3) = sigmoid(3) ≈ 0.952574
        $result = $this->signal()->compute($this->makeInput(2.0));
        $this->assertFalse($result['skip']);
        $expected = round(1.0 / (1.0 + exp(-3.0)), 6);
        $this->assertSame($expected, $result['normalized']);
        $this->assertGreaterThan(0.95, $result['normalized']);
    }

    public function test_normalized_low_demand(): void
    {
        // DSR = 0.0 → sigmoid((0.0 - 1.0) * 3) = sigmoid(-3) ≈ 0.047426
        $result = $this->signal()->compute($this->makeInput(0.0));
        $this->assertFalse($result['skip']);
        $expected = round(1.0 / (1.0 + exp(3.0)), 6);
        $this->assertSame($expected, $result['normalized']);
        $this->assertLessThan(0.05, $result['normalized']);
    }

    public function test_normalized_is_symmetric_around_equilibrium(): void
    {
        // sigmoid is symmetric: normalized(1+d) + normalized(1-d) ≈ 1.0
        $above = $this->signal()->compute($this->makeInput(1.5))['normalized'];
        $below = $this->signal()->compute($this->makeInput(0.5))['normalized'];
        $this->assertEqualsWithDelta(1.0, $above + $below, 0.000001);
    }

    // ── Clamp: sigmoid output is naturally bounded (0, 1) ────────────────────

    public function test_extreme_high_dsr_approaches_one(): void
    {
        $result = $this->signal()->compute($this->makeInput(100.0));
        $this->assertGreaterThan(0.9999, $result['normalized']);
        $this->assertLessThanOrEqual(1.0, $result['normalized']);
    }

    public function test_extreme_low_dsr_approaches_zero(): void
    {
        $result = $this->signal()->compute($this->makeInput(-100.0));
        $this->assertLessThan(0.0001, $result['normalized']);
        $this->assertGreaterThanOrEqual(0.0, $result['normalized']);
    }

    // ── Skip behavior ────────────────────────────────────────────────────────

    public function test_skip_when_null(): void
    {
        $result = $this->signal()->compute($this->makeInput(null));
        $this->assertTrue($result['skip']);
        $this->assertNull($result['normalized']);
        $this->assertNull($result['raw']);
    }

    public function test_skip_reason_when_null(): void
    {
        $result = $this->signal()->compute($this->makeInput(null));
        $this->assertSame(DemandSupplySignal::SKIP_REASON, $result['skip_reason']);
        $this->assertSame('insufficient_stock_pressure_data', $result['skip_reason']);
    }

    public function test_not_skipped_when_zero(): void
    {
        // DSR = 0 is a valid (extreme buyer's market) value
        $result = $this->signal()->compute($this->makeInput(0.0));
        $this->assertFalse($result['skip']);
        $this->assertNull($result['skip_reason']);
    }

    // ── Raw value echoed ─────────────────────────────────────────────────────

    public function test_raw_value_echoed(): void
    {
        $result = $this->signal()->compute($this->makeInput(1.4));
        $this->assertSame(1.4, $result['raw']);
    }

    // ── Anchors stored ───────────────────────────────────────────────────────

    public function test_anchors_contain_offset_and_steepness(): void
    {
        $result = $this->signal()->compute($this->makeInput(1.0));
        $this->assertSame(ModelConfig::PRESSURE_SIGMOID_OFFSET, $result['anchors']['sigmoid_offset']);
        $this->assertSame(ModelConfig::PRESSURE_SIGMOID_STEEPNESS, $result['anchors']['sigmoid_steepness']);
    }

    public function test_anchors_present_on_skip(): void
    {
        $result = $this->signal()->compute($this->makeInput(null));
        $this->assertArrayHasKey('anchors', $result);
        $this->assertArrayHasKey('sigmoid_offset', $result['anchors']);
    }

    // ── Determinism ──────────────────────────────────────────────────────────

    public function test_deterministic(): void
    {
        $input  = $this->makeInput(1.3);
        $first  = $this->signal()->compute($input);
        $second = $this->signal()->compute($input);
        $this->assertSame($first['normalized'], $second['normalized']);
    }
}
