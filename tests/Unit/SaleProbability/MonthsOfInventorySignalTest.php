<?php

namespace Tests\Unit\SaleProbability;

use App\Services\MarketAnalytics\DTOs\MarketAnalyticsResult;
use App\Services\SaleProbability\DTOs\SaleProbabilityInput;
use App\Services\SaleProbability\Signals\MonthsOfInventorySignal;
use App\Services\SaleProbability\Support\ModelConfig;
use PHPUnit\Framework\TestCase;

class MonthsOfInventorySignalTest extends TestCase
{
    private function signal(): MonthsOfInventorySignal
    {
        return new MonthsOfInventorySignal();
    }

    private function makeInput(?float $monthsOfInventory): SaleProbabilityInput
    {
        $ma = MarketAnalyticsResult::empty();
        $ma->monthsOfInventory = $monthsOfInventory;

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
        $this->assertSame('absorption', MonthsOfInventorySignal::SIGNAL_NAME);
    }

    public function test_signal_is_required(): void
    {
        $this->assertTrue(MonthsOfInventorySignal::REQUIRED);
    }

    // ── Anchor points ────────────────────────────────────────────────────────

    public function test_normalized_at_ideal_anchor(): void
    {
        // 1 month → (6-1)/5 = 1.0
        $result = $this->signal()->compute($this->makeInput(1.0));
        $this->assertFalse($result['skip']);
        $this->assertSame(1.0, $result['normalized']);
    }

    public function test_normalized_at_bad_anchor(): void
    {
        // 6 months → (6-6)/5 = 0.0
        $result = $this->signal()->compute($this->makeInput(6.0));
        $this->assertFalse($result['skip']);
        $this->assertSame(0.0, $result['normalized']);
    }

    public function test_normalized_at_midpoint(): void
    {
        // 3.5 months → (6-3.5)/5 = 2.5/5 = 0.5
        $result = $this->signal()->compute($this->makeInput(3.5));
        $this->assertFalse($result['skip']);
        $this->assertSame(0.5, $result['normalized']);
    }

    // ── Clamp behavior ───────────────────────────────────────────────────────

    public function test_clamp_below_ideal(): void
    {
        // 0 months → (6-0)/5 = 1.2 → clamped to 1.0
        $result = $this->signal()->compute($this->makeInput(0.0));
        $this->assertFalse($result['skip']);
        $this->assertSame(1.0, $result['normalized']);
    }

    public function test_clamp_above_bad(): void
    {
        // 11 months → (6-11)/5 = −1.0 → clamped to 0.0
        $result = $this->signal()->compute($this->makeInput(11.0));
        $this->assertFalse($result['skip']);
        $this->assertSame(0.0, $result['normalized']);
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
        $this->assertSame(MonthsOfInventorySignal::SKIP_REASON, $result['skip_reason']);
        $this->assertSame('insufficient_absorption_data', $result['skip_reason']);
    }

    public function test_not_skipped_when_zero(): void
    {
        // 0 months is a valid value (extremely hot market)
        $result = $this->signal()->compute($this->makeInput(0.0));
        $this->assertFalse($result['skip']);
        $this->assertNull($result['skip_reason']);
    }

    // ── Raw value echoed ─────────────────────────────────────────────────────

    public function test_raw_value_echoed(): void
    {
        $result = $this->signal()->compute($this->makeInput(4.2));
        $this->assertSame(4.2, $result['raw']);
    }

    // ── Anchors stored ───────────────────────────────────────────────────────

    public function test_anchors_contain_ideal_months(): void
    {
        $result = $this->signal()->compute($this->makeInput(3.0));
        $this->assertArrayHasKey('ideal_months', $result['anchors']);
        $this->assertSame(ModelConfig::ABSORPTION_IDEAL_MONTHS, $result['anchors']['ideal_months']);
    }

    public function test_anchors_contain_bad_months(): void
    {
        $result = $this->signal()->compute($this->makeInput(3.0));
        $this->assertArrayHasKey('bad_months', $result['anchors']);
        $this->assertSame(ModelConfig::ABSORPTION_BAD_MONTHS, $result['anchors']['bad_months']);
    }

    public function test_anchors_present_on_skip(): void
    {
        $result = $this->signal()->compute($this->makeInput(null));
        $this->assertArrayHasKey('anchors', $result);
        $this->assertArrayHasKey('ideal_months', $result['anchors']);
        $this->assertArrayHasKey('bad_months', $result['anchors']);
    }

    // ── Determinism ──────────────────────────────────────────────────────────

    public function test_deterministic(): void
    {
        $input   = $this->makeInput(2.5);
        $first   = $this->signal()->compute($input);
        $second  = $this->signal()->compute($input);
        $this->assertSame($first['normalized'], $second['normalized']);
    }
}
