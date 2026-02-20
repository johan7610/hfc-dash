<?php

namespace Tests\Unit\SaleProbability;

use App\Services\MarketAnalytics\DTOs\MarketAnalyticsResult;
use App\Services\SaleProbability\DTOs\SaleProbabilityInput;
use App\Services\SaleProbability\Signals\DomMedianSignal;
use App\Services\SaleProbability\Support\ModelConfig;
use PHPUnit\Framework\TestCase;

class DomMedianSignalTest extends TestCase
{
    private function signal(): DomMedianSignal
    {
        return new DomMedianSignal();
    }

    private function makeInput(?array $domCurve): SaleProbabilityInput
    {
        $ma = MarketAnalyticsResult::empty();
        $ma->domCurve = $domCurve;

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
        $this->assertSame('dom', DomMedianSignal::SIGNAL_NAME);
    }

    public function test_signal_is_required(): void
    {
        $this->assertTrue(DomMedianSignal::REQUIRED);
    }

    // ── Anchor points ────────────────────────────────────────────────────────

    public function test_normalized_at_ideal_anchor(): void
    {
        // p50 = 30 → 1 − (30 − 30) / 150 = 1.0
        $result = $this->signal()->compute($this->makeInput(['p25' => 20.0, 'p50' => 30.0, 'p75' => 50.0]));
        $this->assertFalse($result['skip']);
        $this->assertSame(1.0, $result['normalized']);
    }

    public function test_normalized_at_bad_anchor(): void
    {
        // p50 = 180 → 1 − (180 − 30) / 150 = 1 − 1 = 0.0
        $result = $this->signal()->compute($this->makeInput(['p25' => 90.0, 'p50' => 180.0, 'p75' => 240.0]));
        $this->assertFalse($result['skip']);
        $this->assertSame(0.0, $result['normalized']);
    }

    public function test_normalized_at_midpoint(): void
    {
        // p50 = 105 → 1 − (105 − 30) / 150 = 1 − 75/150 = 0.5
        $result = $this->signal()->compute($this->makeInput(['p50' => 105.0]));
        $this->assertFalse($result['skip']);
        $this->assertSame(0.5, $result['normalized']);
    }

    // ── Clamp behavior ───────────────────────────────────────────────────────

    public function test_clamp_below_ideal(): void
    {
        // p50 = 0 → 1 − (0 − 30) / 150 = 1 + 0.2 = 1.2 → clamped to 1.0
        $result = $this->signal()->compute($this->makeInput(['p50' => 0.0]));
        $this->assertSame(1.0, $result['normalized']);
    }

    public function test_clamp_above_bad(): void
    {
        // p50 = 300 → 1 − (300 − 30) / 150 = 1 − 1.8 = −0.8 → clamped to 0.0
        $result = $this->signal()->compute($this->makeInput(['p50' => 300.0]));
        $this->assertSame(0.0, $result['normalized']);
    }

    // ── Skip behavior ────────────────────────────────────────────────────────

    public function test_skip_when_dom_curve_null(): void
    {
        $result = $this->signal()->compute($this->makeInput(null));
        $this->assertTrue($result['skip']);
        $this->assertNull($result['normalized']);
        $this->assertNull($result['raw']);
    }

    public function test_skip_reason_when_dom_curve_null(): void
    {
        $result = $this->signal()->compute($this->makeInput(null));
        $this->assertSame(DomMedianSignal::SKIP_REASON, $result['skip_reason']);
        $this->assertSame('insufficient_dom_data', $result['skip_reason']);
    }

    public function test_skip_when_p50_missing_from_curve(): void
    {
        // domCurve present but missing p50 key
        $result = $this->signal()->compute($this->makeInput(['p25' => 20.0, 'p75' => 60.0]));
        $this->assertTrue($result['skip']);
        $this->assertNull($result['normalized']);
    }

    public function test_skip_reason_when_p50_missing(): void
    {
        $result = $this->signal()->compute($this->makeInput(['p25' => 20.0]));
        $this->assertSame('insufficient_dom_data', $result['skip_reason']);
    }

    // ── Raw value echoed ─────────────────────────────────────────────────────

    public function test_raw_value_is_p50(): void
    {
        $result = $this->signal()->compute($this->makeInput(['p50' => 45.0]));
        $this->assertSame(45.0, $result['raw']);
    }

    // ── Anchors stored ───────────────────────────────────────────────────────

    public function test_anchors_contain_ideal_and_bad_days(): void
    {
        $result = $this->signal()->compute($this->makeInput(['p50' => 60.0]));
        $this->assertSame(ModelConfig::DOM_IDEAL_DAYS, $result['anchors']['ideal_days']);
        $this->assertSame(ModelConfig::DOM_BAD_DAYS, $result['anchors']['bad_days']);
    }

    public function test_anchors_present_on_skip(): void
    {
        $result = $this->signal()->compute($this->makeInput(null));
        $this->assertArrayHasKey('anchors', $result);
        $this->assertArrayHasKey('ideal_days', $result['anchors']);
        $this->assertArrayHasKey('bad_days', $result['anchors']);
    }

    // ── Determinism ──────────────────────────────────────────────────────────

    public function test_deterministic(): void
    {
        $input  = $this->makeInput(['p50' => 45.0]);
        $first  = $this->signal()->compute($input);
        $second = $this->signal()->compute($input);
        $this->assertSame($first['normalized'], $second['normalized']);
    }
}
