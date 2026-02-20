<?php

namespace App\Services\SaleProbability\Support;

use App\Services\SaleProbability\DTOs\SaleProbabilityInput;

class SensitivityRunner
{
    /** Total number of price steps (−10 … 0 … +10). */
    public const STEPS             = 21;

    /** Rand amount per step (±500 k total range). */
    public const STEP_AMOUNT_RANDS = 50_000;

    /** Half-range (STEPS − 1) / 2. */
    private const STEP_RANGE = 10;

    /**
     * Run a price-sensitivity sweep across 21 evenly-spaced price deltas.
     *
     * @param SaleProbabilityInput $input           Original input (for medianSalePrice proxy).
     * @param array                $signalsBreakdown Raw signal outputs keyed by signal name
     *                                               (as produced by the signal compute() calls,
     *                                               before ScoreCombiner augmentation).
     * @param callable             $computeFn        Recomputes probabilities given a modified
     *                                               signals array. Signature:
     *                                               fn(array $signals): array{
     *                                                 composite_score: float,
     *                                                 p30: float, p60: float, p90: float,
     *                                                 expected_days: ?int
     *                                               }
     * @return array  21 entries, one per step.
     */
    public static function run(
        SaleProbabilityInput $input,
        array $signalsBreakdown,
        callable $computeFn,
    ): array {
        $priceSignal = $signalsBreakdown['price'] ?? null;
        $baseDevPct  = ($priceSignal !== null && !$priceSignal['skip'])
            ? (float) $priceSignal['raw']
            : null;

        // Reference price for rands → % conversion.
        // Uses market median as a proxy when subject price is unknown.
        $targetPrice = $input->marketAnalyticsResult->medianSalePrice;

        $steps = [];

        for ($i = -self::STEP_RANGE; $i <= self::STEP_RANGE; $i++) {
            $deltaRands = $i * self::STEP_AMOUNT_RANDS;

            // ── Price signal unavailable → produce null entry ─────────────────
            if ($baseDevPct === null) {
                $steps[] = [
                    'delta_rands'            => $deltaRands,
                    'adjusted_deviation_pct' => null,
                    'composite_score'        => null,
                    'p30'                    => null,
                    'p60'                    => null,
                    'p90'                    => null,
                    'expected_days'          => null,
                    'skip_reason'            => 'insufficient_price_sqm_data',
                ];
                continue;
            }

            // ── Compute adjusted deviation pct ────────────────────────────────
            if ($targetPrice !== null && $targetPrice > 0.0) {
                $adjDevPct = $baseDevPct + ($deltaRands / $targetPrice) * 100.0;
            } else {
                // No reference price → rands conversion impossible; step is flat
                $adjDevPct = $baseDevPct;
            }

            // Recompute price signal normalized value at adjusted deviation
            $clampedAdj    = Clamp::between($adjDevPct, -ModelConfig::PRICE_DEVIATION_RANGE, ModelConfig::PRICE_DEVIATION_RANGE);
            $newNormalized = Clamp::between(
                0.5 - ($clampedAdj / ModelConfig::PRICE_DEVIATION_RANGE),
                0.0,
                1.0,
            );

            // Inject adjusted price signal (keep all other signals unchanged)
            $modifiedSignals           = $signalsBreakdown;
            $modifiedSignals['price']  = array_merge($priceSignal, [
                'raw'         => $adjDevPct,           // unclamped observed value
                'normalized'  => round($newNormalized, 6),
                'skip'        => false,
                'skip_reason' => null,
            ]);

            $computed = $computeFn($modifiedSignals);

            $steps[] = [
                'delta_rands'            => $deltaRands,
                'adjusted_deviation_pct' => round($adjDevPct, 4),
                'composite_score'        => $computed['composite_score'],
                'p30'                    => $computed['p30'],
                'p60'                    => $computed['p60'],
                'p90'                    => $computed['p90'],
                'expected_days'          => $computed['expected_days'],
                'skip_reason'            => null,
            ];
        }

        return $steps;
    }
}
