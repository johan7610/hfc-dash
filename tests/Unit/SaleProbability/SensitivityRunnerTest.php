<?php

namespace Tests\Unit\SaleProbability;

use App\Services\MarketAnalytics\DTOs\MarketAnalyticsResult;
use App\Services\SaleProbability\DTOs\SaleProbabilityInput;
use App\Services\SaleProbability\Support\Clamp;
use App\Services\SaleProbability\Support\ProbabilityMapper;
use App\Services\SaleProbability\Support\ScoreCombiner;
use App\Services\SaleProbability\Support\SensitivityRunner;
use PHPUnit\Framework\TestCase;

class SensitivityRunnerTest extends TestCase
{
    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeInput(?float $medianSalePrice, ?float $priceDeviation = null): SaleProbabilityInput
    {
        $ma                      = MarketAnalyticsResult::empty();
        $ma->medianSalePrice     = $medianSalePrice;
        $ma->pricePerSqmDeviationPct = $priceDeviation;

        return new SaleProbabilityInput(
            marketAnalyticsRunId:        1,
            marketAnalyticsModelVersion: 'v1.0.0',
            marketAnalyticsInputsHash:   'abc123',
            marketAnalyticsResult:       $ma,
        );
    }

    /**
     * Build a full signals array with the price signal set to $priceDeviation.
     * Other signals are fixed at sensible non-skipped values.
     */
    private function makeSignals(float $priceDeviation): array
    {
        $priceNormalized = round(Clamp::between(0.5 - ($priceDeviation / 60.0), 0.0, 1.0), 6);

        return [
            'price'      => [
                'raw'         => $priceDeviation,
                'normalized'  => $priceNormalized,
                'skip'        => false,
                'skip_reason' => null,
                'anchors'     => ['deviation_range' => 60.0],
            ],
            'absorption' => ['raw' => 3.0,  'normalized' => 0.6, 'skip' => false, 'skip_reason' => null, 'anchors' => []],
            'pressure'   => ['raw' => 1.0,  'normalized' => 0.5, 'skip' => false, 'skip_reason' => null, 'anchors' => []],
            'dom'        => ['raw' => 75.0, 'normalized' => 0.7, 'skip' => false, 'skip_reason' => null, 'anchors' => []],
            'elasticity' => ['raw' => 0.0,  'normalized' => 0.5, 'skip' => false, 'skip_reason' => null, 'anchors' => []],
        ];
    }

    private function makeSkippedPriceSignals(): array
    {
        return [
            'price'      => ['raw' => null, 'normalized' => null, 'skip' => true, 'skip_reason' => 'insufficient_price_sqm_data', 'anchors' => []],
            'absorption' => ['raw' => 3.0,  'normalized' => 0.6,  'skip' => false, 'skip_reason' => null, 'anchors' => []],
            'pressure'   => ['raw' => 1.0,  'normalized' => 0.5,  'skip' => false, 'skip_reason' => null, 'anchors' => []],
            'dom'        => ['raw' => 75.0, 'normalized' => 0.7,  'skip' => false, 'skip_reason' => null, 'anchors' => []],
            'elasticity' => ['raw' => 0.0,  'normalized' => 0.5,  'skip' => false, 'skip_reason' => null, 'anchors' => []],
        ];
    }

    private function defaultComputeFn(): callable
    {
        return static function (array $signals): array {
            $combined = ScoreCombiner::combine($signals);
            $score    = $combined['composite_score'];
            $probs    = ProbabilityMapper::map($score);
            return [
                'composite_score' => $score,
                'p30'             => $probs['p30'],
                'p60'             => $probs['p60'],
                'p90'             => $probs['p90'],
                'expected_days'   => null,
            ];
        };
    }

    // ── Step count and delta sequence ─────────────────────────────────────────

    public function test_produces_21_steps(): void
    {
        $input   = $this->makeInput(2_000_000.0, 10.0);
        $signals = $this->makeSignals(10.0);
        $result  = SensitivityRunner::run($input, $signals, $this->defaultComputeFn());
        $this->assertCount(SensitivityRunner::STEPS, $result);
    }

    public function test_centre_step_is_at_index_10(): void
    {
        $input   = $this->makeInput(2_000_000.0, 10.0);
        $signals = $this->makeSignals(10.0);
        $result  = SensitivityRunner::run($input, $signals, $this->defaultComputeFn());
        $this->assertSame(0, $result[10]['delta_rands']);
    }

    public function test_first_step_delta_is_minus_500k(): void
    {
        $input   = $this->makeInput(2_000_000.0, 10.0);
        $signals = $this->makeSignals(10.0);
        $result  = SensitivityRunner::run($input, $signals, $this->defaultComputeFn());
        $this->assertSame(-500_000, $result[0]['delta_rands']);
    }

    public function test_last_step_delta_is_plus_500k(): void
    {
        $input   = $this->makeInput(2_000_000.0, 10.0);
        $signals = $this->makeSignals(10.0);
        $result  = SensitivityRunner::run($input, $signals, $this->defaultComputeFn());
        $this->assertSame(500_000, $result[20]['delta_rands']);
    }

    public function test_delta_rands_step_size_is_50k(): void
    {
        $input   = $this->makeInput(2_000_000.0, 10.0);
        $signals = $this->makeSignals(10.0);
        $result  = SensitivityRunner::run($input, $signals, $this->defaultComputeFn());
        $this->assertSame(SensitivityRunner::STEP_AMOUNT_RANDS, $result[11]['delta_rands'] - $result[10]['delta_rands']);
    }

    // ── Centre step matches baseline ──────────────────────────────────────────

    public function test_centre_step_matches_base_probabilities(): void
    {
        $signals   = $this->makeSignals(10.0);
        $input     = $this->makeInput(2_000_000.0, 10.0);
        $computeFn = $this->defaultComputeFn();

        // Baseline — same signals, no delta
        $baseCombined = ScoreCombiner::combine($signals);
        $baseProbs    = ProbabilityMapper::map($baseCombined['composite_score']);

        $result     = SensitivityRunner::run($input, $signals, $computeFn);
        $centreStep = $result[10]; // i = 0

        $this->assertSame($baseProbs['p30'], $centreStep['p30']);
        $this->assertSame($baseProbs['p60'], $centreStep['p60']);
        $this->assertSame($baseProbs['p90'], $centreStep['p90']);
    }

    public function test_adjusted_deviation_at_centre_equals_base(): void
    {
        $baseDeviation = 10.0;
        $input         = $this->makeInput(2_000_000.0, $baseDeviation);
        $signals       = $this->makeSignals($baseDeviation);

        $result = SensitivityRunner::run($input, $signals, $this->defaultComputeFn());

        $this->assertSame(round($baseDeviation, 4), $result[10]['adjusted_deviation_pct']);
    }

    // ── Price direction invariant ─────────────────────────────────────────────

    public function test_probabilities_improve_as_price_decreases_when_above_market(): void
    {
        // Subject is above market (positive deviation → below-average sale probability)
        $input     = $this->makeInput(medianSalePrice: 2_000_000.0, priceDeviation: 15.0);
        $signals   = $this->makeSignals(15.0);

        $result = SensitivityRunner::run($input, $signals, $this->defaultComputeFn());

        // Reducing price by 50 k (step i=−1, index 9) should raise p30
        $this->assertGreaterThan($result[10]['p30'], $result[9]['p30']);
    }

    public function test_probabilities_worsen_as_price_increases_when_above_market(): void
    {
        $input   = $this->makeInput(medianSalePrice: 2_000_000.0, priceDeviation: 15.0);
        $signals = $this->makeSignals(15.0);

        $result = SensitivityRunner::run($input, $signals, $this->defaultComputeFn());

        // Raising price by 50 k (step i=+1, index 11) should lower p30
        $this->assertLessThan($result[10]['p30'], $result[11]['p30']);
    }

    // ── Skip: missing price signal ────────────────────────────────────────────

    public function test_all_entries_have_skip_reason_when_price_signal_missing(): void
    {
        $input   = $this->makeInput(2_000_000.0);
        $signals = $this->makeSkippedPriceSignals();
        $result  = SensitivityRunner::run($input, $signals, $this->defaultComputeFn());

        foreach ($result as $step) {
            $this->assertNotNull($step['skip_reason'], 'Expected skip_reason at delta=' . $step['delta_rands']);
        }
    }

    public function test_skip_reason_is_price_sqm_when_price_signal_missing(): void
    {
        $input   = $this->makeInput(2_000_000.0);
        $signals = $this->makeSkippedPriceSignals();
        $result  = SensitivityRunner::run($input, $signals, $this->defaultComputeFn());

        $this->assertSame('insufficient_price_sqm_data', $result[0]['skip_reason']);
    }

    public function test_null_probabilities_when_price_signal_missing(): void
    {
        $input   = $this->makeInput(2_000_000.0);
        $signals = $this->makeSkippedPriceSignals();
        $result  = SensitivityRunner::run($input, $signals, $this->defaultComputeFn());

        foreach ($result as $step) {
            $this->assertNull($step['p30']);
            $this->assertNull($step['p60']);
            $this->assertNull($step['p90']);
        }
    }

    // ── Success entries have no skip_reason ───────────────────────────────────

    public function test_skip_reason_null_for_success_entries(): void
    {
        $input   = $this->makeInput(2_000_000.0, 10.0);
        $signals = $this->makeSignals(10.0);
        $result  = SensitivityRunner::run($input, $signals, $this->defaultComputeFn());

        foreach ($result as $step) {
            $this->assertNull($step['skip_reason']);
        }
    }

    // ── Determinism ───────────────────────────────────────────────────────────

    public function test_deterministic(): void
    {
        $input     = $this->makeInput(2_000_000.0, 10.0);
        $signals   = $this->makeSignals(10.0);
        $computeFn = $this->defaultComputeFn();

        $first  = SensitivityRunner::run($input, $signals, $computeFn);
        $second = SensitivityRunner::run($input, $signals, $computeFn);

        $this->assertSame($first[10]['p30'], $second[10]['p30']);
        $this->assertSame($first[5]['p60'],  $second[5]['p60']);
    }
}
