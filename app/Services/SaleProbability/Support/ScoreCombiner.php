<?php

namespace App\Services\SaleProbability\Support;

class ScoreCombiner
{
    // ── Configured weights (audit copy, read-only) ────────────────────────────

    /**
     * Signal name → configured weight map.
     * Weights must sum to 1.0 across all signals (required + optional).
     */
    public static function configuredWeights(): array
    {
        return [
            'price'       => ModelConfig::WEIGHT_PRICE,
            'absorption'  => ModelConfig::WEIGHT_ABSORPTION,
            'pressure'    => ModelConfig::WEIGHT_PRESSURE,
            'dom'         => ModelConfig::WEIGHT_DOM,
            'elasticity'  => ModelConfig::WEIGHT_ELASTICITY,
        ];
    }

    // ── Composite score ───────────────────────────────────────────────────────

    /**
     * Compute weighted composite score from signal outputs.
     *
     * Skipped signals are excluded from the denominator so their weight is
     * redistributed proportionally across the remaining active signals.
     *
     * Returns an array containing:
     *   composite_score       float   clamped [0, 1]
     *   weight_redistribution bool    true if any required signal was skipped
     *   weights_used          array   signal → configured weight (for audit)
     *   signals               array   input signals augmented with 'weight' + 'contribution'
     */
    public static function combine(array $signals): array
    {
        $configuredWeights    = self::configuredWeights();
        $requiredSignals      = ModelConfig::REQUIRED_SIGNALS;
        $activeWeightTotal    = 0.0;
        $weightRedistribution = false;

        // First pass: sum active weights, detect redistribution
        foreach ($signals as $name => $signal) {
            if (!$signal['skip']) {
                $activeWeightTotal += $configuredWeights[$name] ?? 0.0;
            } elseif (in_array($name, $requiredSignals, true)) {
                $weightRedistribution = true;
            }
        }

        // Second pass: compute contributions and composite score
        $compositeScore   = 0.0;
        $augmentedSignals = [];

        foreach ($signals as $name => $signal) {
            $weight = $configuredWeights[$name] ?? 0.0;

            if (!$signal['skip'] && $activeWeightTotal > 0.0) {
                $contribution    = ($signal['normalized'] * $weight) / $activeWeightTotal;
                $compositeScore += $contribution;
            } else {
                $contribution = null;
            }

            $augmentedSignals[$name] = array_merge($signal, [
                'weight'       => $weight,
                'contribution' => $contribution,
            ]);
        }

        $compositeScore = Clamp::between($compositeScore, 0.0, 1.0);

        return [
            'composite_score'       => $compositeScore,
            'weight_redistribution' => $weightRedistribution,
            'weights_used'          => $configuredWeights,
            'signals'               => $augmentedSignals,
        ];
    }

    // ── Expected days ─────────────────────────────────────────────────────────

    /**
     * Estimate expected days on market.
     *
     * Base interpolation: domP75 → domP50 as composite score goes 0 → 1.
     * Elasticity adjustment: adds days when subject is priced above market.
     *
     * Returns null if domP50 or domP75 is not available.
     */
    public static function computeExpectedDays(
        float  $compositeScore,
        ?float $domP50,
        ?float $domP75,
        ?float $elasticityDaysPerPct,
        ?float $priceDeviationPct,
    ): ?int {
        if ($domP50 === null || $domP75 === null) {
            return null;
        }

        // Interpolate between domP75 (score=0, slow) and domP50 (score=1, fast)
        $base = $domP75 - (($domP75 - $domP50) * $compositeScore);

        // Apply elasticity adjustment when subject is above market price
        if ($elasticityDaysPerPct !== null && $priceDeviationPct !== null) {
            $base += $elasticityDaysPerPct * max($priceDeviationPct, 0.0);
        }

        return (int) round(Clamp::between($base, ModelConfig::EXPECTED_DAYS_MIN, ModelConfig::EXPECTED_DAYS_MAX));
    }
}
