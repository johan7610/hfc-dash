<?php

namespace Tests\Unit\SaleProbability;

use App\Services\MarketAnalytics\DTOs\MarketAnalyticsResult;
use App\Services\SaleProbability\DTOs\SaleProbabilityInput;
use App\Services\SaleProbability\Signals\PriceSqmDeviationSignal;
use App\Services\SaleProbability\Support\ModelConfig;
use PHPUnit\Framework\TestCase;

class PriceSqmDeviationSignalTest extends TestCase
{
    private function signal(): PriceSqmDeviationSignal
    {
        return new PriceSqmDeviationSignal();
    }

    private function makeInput(?float $pricePerSqmDeviationPct): SaleProbabilityInput
    {
        $ma = MarketAnalyticsResult::empty();
        $ma->pricePerSqmDeviationPct = $pricePerSqmDeviationPct;

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
        $this->assertSame('price', PriceSqmDeviationSignal::SIGNAL_NAME);
    }

    public function test_signal_is_required(): void
    {
        $this->assertTrue(PriceSqmDeviationSignal::REQUIRED);
    }

    // ── Anchor points ────────────────────────────────────────────────────────

    public function test_normalized_at_negative_anchor(): void
    {
        // deviation = −30% → 0.5 − (−30/60) = 0.5 + 0.5 = 1.0
        $result = $this->signal()->compute($this->makeInput(-30.0));
        $this->assertFalse($result['skip']);
        $this->assertSame(1.0, $result['normalized']);
    }

    public function test_normalized_at_positive_anchor(): void
    {
        // deviation = +30% → 0.5 − (30/60) = 0.5 − 0.5 = 0.0
        $result = $this->signal()->compute($this->makeInput(30.0));
        $this->assertFalse($result['skip']);
        $this->assertSame(0.0, $result['normalized']);
    }

    public function test_normalized_at_zero_deviation(): void
    {
        // deviation = 0% → 0.5 − 0 = 0.5
        $result = $this->signal()->compute($this->makeInput(0.0));
        $this->assertFalse($result['skip']);
        $this->assertSame(0.5, $result['normalized']);
    }

    public function test_normalized_at_midpoint_negative(): void
    {
        // deviation = −15% → 0.5 − (−15/60) = 0.5 + 0.25 = 0.75
        $result = $this->signal()->compute($this->makeInput(-15.0));
        $this->assertFalse($result['skip']);
        $this->assertSame(0.75, $result['normalized']);
    }

    public function test_normalized_at_midpoint_positive(): void
    {
        // deviation = +15% → 0.5 − (15/60) = 0.5 − 0.25 = 0.25
        $result = $this->signal()->compute($this->makeInput(15.0));
        $this->assertFalse($result['skip']);
        $this->assertSame(0.25, $result['normalized']);
    }

    // ── Clamp behavior ───────────────────────────────────────────────────────

    public function test_clamp_below_negative_anchor(): void
    {
        // deviation = −60% → would be 1.5 → clamped to 1.0
        $result = $this->signal()->compute($this->makeInput(-60.0));
        $this->assertSame(1.0, $result['normalized']);
    }

    public function test_clamp_above_positive_anchor(): void
    {
        // deviation = +60% → would be −0.5 → clamped to 0.0
        $result = $this->signal()->compute($this->makeInput(60.0));
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
        $this->assertSame(PriceSqmDeviationSignal::SKIP_REASON, $result['skip_reason']);
        $this->assertSame('insufficient_price_sqm_data', $result['skip_reason']);
    }

    public function test_not_skipped_when_zero(): void
    {
        // 0% deviation is a valid value (priced at market)
        $result = $this->signal()->compute($this->makeInput(0.0));
        $this->assertFalse($result['skip']);
        $this->assertNull($result['skip_reason']);
    }

    // ── Raw value echoed ─────────────────────────────────────────────────────

    public function test_raw_value_echoed(): void
    {
        $result = $this->signal()->compute($this->makeInput(-8.3));
        $this->assertSame(-8.3, $result['raw']);
    }

    // ── Anchors stored ───────────────────────────────────────────────────────

    public function test_anchors_contain_deviation_range(): void
    {
        $result = $this->signal()->compute($this->makeInput(0.0));
        $this->assertArrayHasKey('deviation_range', $result['anchors']);
        $this->assertSame(ModelConfig::PRICE_DEVIATION_RANGE, $result['anchors']['deviation_range']);
    }

    public function test_anchors_present_on_skip(): void
    {
        $result = $this->signal()->compute($this->makeInput(null));
        $this->assertArrayHasKey('anchors', $result);
        $this->assertArrayHasKey('deviation_range', $result['anchors']);
    }

    // ── Determinism ──────────────────────────────────────────────────────────

    public function test_deterministic(): void
    {
        $input  = $this->makeInput(-8.3);
        $first  = $this->signal()->compute($input);
        $second = $this->signal()->compute($input);
        $this->assertSame($first['normalized'], $second['normalized']);
    }
}
