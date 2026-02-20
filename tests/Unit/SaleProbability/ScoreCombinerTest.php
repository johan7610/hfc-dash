<?php

namespace Tests\Unit\SaleProbability;

use App\Services\SaleProbability\Support\ModelConfig;
use App\Services\SaleProbability\Support\ScoreCombiner;
use PHPUnit\Framework\TestCase;

class ScoreCombinerTest extends TestCase
{
    // ── Helpers ───────────────────────────────────────────────────────────────

    private function sig(float $normalized, bool $skip = false, ?string $skipReason = null): array
    {
        return [
            'raw'         => $skip ? null : $normalized,
            'normalized'  => $skip ? null : $normalized,
            'skip'        => $skip,
            'skip_reason' => $skipReason,
            'anchors'     => [],
        ];
    }

    private function allSignals(array $overrides = []): array
    {
        return array_merge([
            'price'      => $this->sig(0.5),
            'absorption' => $this->sig(0.5),
            'pressure'   => $this->sig(0.5),
            'dom'        => $this->sig(0.5),
            'elasticity' => $this->sig(0.5),
        ], $overrides);
    }

    // ── configuredWeights ─────────────────────────────────────────────────────

    public function test_configured_weights_sum_to_one(): void
    {
        $sum = array_sum(ScoreCombiner::configuredWeights());
        $this->assertEqualsWithDelta(1.0, $sum, 0.000001);
    }

    public function test_configured_weights_has_all_five_signals(): void
    {
        $weights = ScoreCombiner::configuredWeights();
        foreach (['price', 'absorption', 'pressure', 'dom', 'elasticity'] as $name) {
            $this->assertArrayHasKey($name, $weights);
        }
    }

    public function test_configured_weights_match_model_config(): void
    {
        $weights = ScoreCombiner::configuredWeights();
        $this->assertSame(ModelConfig::WEIGHT_PRICE,      $weights['price']);
        $this->assertSame(ModelConfig::WEIGHT_ABSORPTION, $weights['absorption']);
        $this->assertSame(ModelConfig::WEIGHT_PRESSURE,   $weights['pressure']);
        $this->assertSame(ModelConfig::WEIGHT_DOM,        $weights['dom']);
        $this->assertSame(ModelConfig::WEIGHT_ELASTICITY, $weights['elasticity']);
    }

    // ── Weight redistribution flag ────────────────────────────────────────────

    public function test_all_signals_active_no_redistribution(): void
    {
        $result = ScoreCombiner::combine($this->allSignals());
        $this->assertFalse($result['weight_redistribution']);
    }

    public function test_skipped_required_signal_triggers_redistribution(): void
    {
        $result = ScoreCombiner::combine($this->allSignals([
            'price' => $this->sig(0.0, skip: true, skipReason: 'insufficient_price_sqm_data'),
        ]));
        $this->assertTrue($result['weight_redistribution']);
    }

    public function test_skipped_optional_signal_does_not_trigger_redistribution(): void
    {
        $result = ScoreCombiner::combine($this->allSignals([
            'elasticity' => $this->sig(0.0, skip: true, skipReason: 'insufficient_elasticity_data'),
        ]));
        $this->assertFalse($result['weight_redistribution']);
    }

    // ── Composite score ───────────────────────────────────────────────────────

    public function test_composite_score_all_normalized_one(): void
    {
        $signals = $this->allSignals([
            'price'      => $this->sig(1.0),
            'absorption' => $this->sig(1.0),
            'pressure'   => $this->sig(1.0),
            'dom'        => $this->sig(1.0),
            'elasticity' => $this->sig(1.0),
        ]);
        $result = ScoreCombiner::combine($signals);
        $this->assertEqualsWithDelta(1.0, $result['composite_score'], 0.000001);
    }

    public function test_composite_score_all_normalized_zero(): void
    {
        $signals = $this->allSignals([
            'price'      => $this->sig(0.0),
            'absorption' => $this->sig(0.0),
            'pressure'   => $this->sig(0.0),
            'dom'        => $this->sig(0.0),
            'elasticity' => $this->sig(0.0),
        ]);
        $result = ScoreCombiner::combine($signals);
        $this->assertEqualsWithDelta(0.0, $result['composite_score'], 0.000001);
    }

    public function test_composite_score_midpoint_all_signals(): void
    {
        $result = ScoreCombiner::combine($this->allSignals()); // all normalized=0.5
        $this->assertEqualsWithDelta(0.5, $result['composite_score'], 0.000001);
    }

    public function test_composite_score_with_one_required_signal_skipped(): void
    {
        // Price skipped; remaining signals all normalized=1.0
        // active_weight = 0.25 + 0.20 + 0.15 + 0.10 = 0.70; sum = 0.70/0.70 = 1.0
        $signals = $this->allSignals([
            'price'      => $this->sig(0.0, skip: true, skipReason: 'test'),
            'absorption' => $this->sig(1.0),
            'pressure'   => $this->sig(1.0),
            'dom'        => $this->sig(1.0),
            'elasticity' => $this->sig(1.0),
        ]);
        $result = ScoreCombiner::combine($signals);
        $this->assertEqualsWithDelta(1.0, $result['composite_score'], 0.000001);
    }

    public function test_composite_score_clamped_to_zero(): void
    {
        // All normalized=0.0 → score=0.0 → clamp(0,0,1)=0.0
        $signals = $this->allSignals([
            'price'      => $this->sig(0.0),
            'absorption' => $this->sig(0.0),
            'pressure'   => $this->sig(0.0),
            'dom'        => $this->sig(0.0),
            'elasticity' => $this->sig(0.0),
        ]);
        $result = ScoreCombiner::combine($signals);
        $this->assertGreaterThanOrEqual(0.0, $result['composite_score']);
        $this->assertLessThanOrEqual(1.0, $result['composite_score']);
    }

    // ── Contributions ─────────────────────────────────────────────────────────

    public function test_skipped_signal_has_null_contribution(): void
    {
        $result  = ScoreCombiner::combine($this->allSignals([
            'price' => $this->sig(0.0, skip: true, skipReason: 'test'),
        ]));
        $this->assertNull($result['signals']['price']['contribution']);
    }

    public function test_active_signals_have_float_contribution(): void
    {
        $result = ScoreCombiner::combine($this->allSignals()); // all active
        foreach (['price', 'absorption', 'pressure', 'dom', 'elasticity'] as $name) {
            $this->assertIsFloat($result['signals'][$name]['contribution']);
        }
    }

    public function test_contributions_sum_to_composite_score(): void
    {
        $result = ScoreCombiner::combine($this->allSignals()); // all normalized=0.5
        $sum    = 0.0;
        foreach ($result['signals'] as $signal) {
            if ($signal['contribution'] !== null) {
                $sum += $signal['contribution'];
            }
        }
        $this->assertEqualsWithDelta($result['composite_score'], $sum, 0.000001);
    }

    public function test_signals_augmented_with_weight_key(): void
    {
        $weights = ScoreCombiner::configuredWeights();
        $result  = ScoreCombiner::combine($this->allSignals());
        foreach ($result['signals'] as $name => $signal) {
            $this->assertArrayHasKey('weight', $signal);
            $this->assertSame($weights[$name], $signal['weight']);
        }
    }

    // ── weights_used ──────────────────────────────────────────────────────────

    public function test_weights_used_matches_configured_weights(): void
    {
        $result = ScoreCombiner::combine($this->allSignals());
        $this->assertSame(ScoreCombiner::configuredWeights(), $result['weights_used']);
    }

    // ── computeExpectedDays ───────────────────────────────────────────────────

    public function test_expected_days_score_one_returns_p50(): void
    {
        // score=1.0 → base = p75 - (p75 - p50) * 1.0 = p50
        $days = ScoreCombiner::computeExpectedDays(1.0, 60.0, 90.0, null, null);
        $this->assertSame(60, $days);
    }

    public function test_expected_days_score_zero_returns_p75(): void
    {
        // score=0.0 → base = p75 - (p75 - p50) * 0.0 = p75
        $days = ScoreCombiner::computeExpectedDays(0.0, 60.0, 90.0, null, null);
        $this->assertSame(90, $days);
    }

    public function test_expected_days_midpoint_interpolation(): void
    {
        // score=0.5, p50=60, p75=90 → base = 90 - (90-60)*0.5 = 90 - 15 = 75
        $days = ScoreCombiner::computeExpectedDays(0.5, 60.0, 90.0, null, null);
        $this->assertSame(75, $days);
    }

    public function test_expected_days_with_elasticity_adjustment(): void
    {
        // score=0.5, p50=60, p75=90 → base=75
        // elasticity=2.0, deviation=10.0 → +2.0*10.0 = 20 → total=95
        $days = ScoreCombiner::computeExpectedDays(0.5, 60.0, 90.0, 2.0, 10.0);
        $this->assertSame(95, $days);
    }

    public function test_expected_days_elasticity_ignored_when_deviation_not_positive(): void
    {
        // priceDeviationPct <= 0 → max(dev, 0) = 0 → no adjustment
        $days = ScoreCombiner::computeExpectedDays(0.5, 60.0, 90.0, 2.0, -5.0);
        $this->assertSame(75, $days);
    }

    public function test_expected_days_null_when_dom_p50_missing(): void
    {
        $days = ScoreCombiner::computeExpectedDays(0.5, null, 90.0, null, null);
        $this->assertNull($days);
    }

    public function test_expected_days_null_when_dom_p75_missing(): void
    {
        $days = ScoreCombiner::computeExpectedDays(0.5, 60.0, null, null, null);
        $this->assertNull($days);
    }

    public function test_expected_days_clamped_to_min(): void
    {
        // score=1.0, p50=0.1, p75=0.2 → base≈0 → clamp to EXPECTED_DAYS_MIN
        $days = ScoreCombiner::computeExpectedDays(1.0, 0.1, 0.2, null, null);
        $this->assertSame(ModelConfig::EXPECTED_DAYS_MIN, $days);
    }

    public function test_expected_days_clamped_to_max(): void
    {
        // score=0.0, p50=500, p75=1000 → base=1000 → clamp to EXPECTED_DAYS_MAX
        $days = ScoreCombiner::computeExpectedDays(0.0, 500.0, 1000.0, null, null);
        $this->assertSame(ModelConfig::EXPECTED_DAYS_MAX, $days);
    }
}
