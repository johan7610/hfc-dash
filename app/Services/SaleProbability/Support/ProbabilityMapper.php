<?php

namespace App\Services\SaleProbability\Support;

class ProbabilityMapper
{
    /**
     * Map a composite score [0, 1] to sale-probability horizons.
     *
     * Each horizon uses a sigmoid centred at a different score level:
     *   p30 → centre 0.70 (only high-scoring properties sell fast)
     *   p60 → centre 0.50 (moderate score needed)
     *   p90 → centre 0.30 (most properties sell eventually)
     *
     * Monotonic enforcement: p30 ≤ p60 ≤ p90 (longer horizon = higher probability).
     * All values rounded to 4 decimal places.
     */
    public static function map(float $compositeScore): array
    {
        $p30 = round(Sigmoid::compute(($compositeScore - ModelConfig::P30_CENTRE) * ModelConfig::P30_STEEPNESS), 4);
        $p60 = round(Sigmoid::compute(($compositeScore - ModelConfig::P60_CENTRE) * ModelConfig::P60_STEEPNESS), 4);
        $p90 = round(Sigmoid::compute(($compositeScore - ModelConfig::P90_CENTRE) * ModelConfig::P90_STEEPNESS), 4);

        // Monotonic enforcement (handles floating-point edge cases near centre)
        $p60 = max($p60, $p30);
        $p90 = max($p90, $p60);

        return [
            'p30' => $p30,
            'p60' => $p60,
            'p90' => $p90,
        ];
    }
}
