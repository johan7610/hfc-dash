<?php

namespace Tests\Unit\SaleProbability;

use App\Services\MarketAnalytics\DTOs\MarketAnalyticsResult;
use App\Services\SaleProbability\DTOs\SaleProbabilityInput;
use App\Services\SaleProbability\Signals\ElasticitySignal;
use App\Services\SaleProbability\Support\ModelConfig;
use PHPUnit\Framework\TestCase;

class ElasticitySignalTest extends TestCase
{
    private function signal(): ElasticitySignal
    {
        return new ElasticitySignal();
    }

    private function makeInput(?float $elasticityDaysPerPct): SaleProbabilityInput
    {
        $ma = MarketAnalyticsResult::empty();
        $ma->elasticityDaysPerPct = $elasticityDaysPerPct;

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
        $this->assertSame('elasticity', ElasticitySignal::SIGNAL_NAME);
    }

    public function test_signal_is_optional(): void
    {
        $this->assertFalse(ElasticitySignal::REQUIRED);
    }

    // ── Anchor points ────────────────────────────────────────────────────────

    public function test_normalized_at_clamp_min(): void
    {
        // raw = −5 → (5 − (−5)) / 10 = 10/10 = 1.0
        $result = $this->signal()->compute($this->makeInput(-5.0));
        $this->assertFalse($result['skip']);
        $this->assertSame(1.0, $result['normalized']);
    }

    public function test_normalized_at_clamp_max(): void
    {
        // raw = +5 → (5 − 5) / 10 = 0.0
        $result = $this->signal()->compute($this->makeInput(5.0));
        $this->assertFalse($result['skip']);
        $this->assertSame(0.0, $result['normalized']);
    }

    public function test_normalized_at_midpoint(): void
    {
        // raw = 0 → (5 − 0) / 10 = 0.5
        $result = $this->signal()->compute($this->makeInput(0.0));
        $this->assertFalse($result['skip']);
        $this->assertSame(0.5, $result['normalized']);
    }

    public function test_normalized_at_ideal(): void
    {
        // raw = −2 (ideal) → (5 − (−2)) / 10 = 7/10 = 0.7
        $result = $this->signal()->compute($this->makeInput(-2.0));
        $this->assertFalse($result['skip']);
        $this->assertSame(0.7, $result['normalized']);
    }

    public function test_normalized_at_bad(): void
    {
        // raw = +2 (bad) → (5 − 2) / 10 = 3/10 = 0.3
        $result = $this->signal()->compute($this->makeInput(2.0));
        $this->assertFalse($result['skip']);
        $this->assertSame(0.3, $result['normalized']);
    }

    // ── Clamp behavior ───────────────────────────────────────────────────────

    public function test_clamp_below_min(): void
    {
        // raw = −10 → clamped to −5 → normalized = 1.0
        $result = $this->signal()->compute($this->makeInput(-10.0));
        $this->assertSame(1.0, $result['normalized']);
    }

    public function test_clamp_above_max(): void
    {
        // raw = +10 → clamped to +5 → normalized = 0.0
        $result = $this->signal()->compute($this->makeInput(10.0));
        $this->assertSame(0.0, $result['normalized']);
    }

    public function test_raw_value_stored_unclamped(): void
    {
        // Raw is stored before clamping (actual observed value)
        $result = $this->signal()->compute($this->makeInput(-10.0));
        $this->assertSame(-10.0, $result['raw']);
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
        $this->assertSame(ElasticitySignal::SKIP_REASON, $result['skip_reason']);
        $this->assertSame('insufficient_elasticity_data', $result['skip_reason']);
    }

    public function test_not_skipped_when_zero(): void
    {
        // 0 days/% is a valid (neutral elasticity) value
        $result = $this->signal()->compute($this->makeInput(0.0));
        $this->assertFalse($result['skip']);
        $this->assertNull($result['skip_reason']);
    }

    // ── Anchors stored ───────────────────────────────────────────────────────

    public function test_anchors_contain_all_keys(): void
    {
        $result  = $this->signal()->compute($this->makeInput(0.0));
        $anchors = $result['anchors'];
        $this->assertArrayHasKey('clamp_min', $anchors);
        $this->assertArrayHasKey('clamp_max', $anchors);
        $this->assertArrayHasKey('ideal', $anchors);
        $this->assertArrayHasKey('bad', $anchors);
    }

    public function test_anchors_match_model_config(): void
    {
        $result  = $this->signal()->compute($this->makeInput(0.0));
        $anchors = $result['anchors'];
        $this->assertSame(ModelConfig::ELASTICITY_CLAMP_MIN, $anchors['clamp_min']);
        $this->assertSame(ModelConfig::ELASTICITY_CLAMP_MAX, $anchors['clamp_max']);
        $this->assertSame(ModelConfig::ELASTICITY_IDEAL, $anchors['ideal']);
        $this->assertSame(ModelConfig::ELASTICITY_BAD, $anchors['bad']);
    }

    public function test_anchors_present_on_skip(): void
    {
        $result = $this->signal()->compute($this->makeInput(null));
        $this->assertArrayHasKey('anchors', $result);
        $this->assertArrayHasKey('clamp_min', $result['anchors']);
    }

    // ── Determinism ──────────────────────────────────────────────────────────

    public function test_deterministic(): void
    {
        $input  = $this->makeInput(1.5);
        $first  = $this->signal()->compute($input);
        $second = $this->signal()->compute($input);
        $this->assertSame($first['normalized'], $second['normalized']);
    }
}
