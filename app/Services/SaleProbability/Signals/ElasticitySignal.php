<?php

namespace App\Services\SaleProbability\Signals;

use App\Services\SaleProbability\DTOs\SaleProbabilityInput;
use App\Services\SaleProbability\Support\Clamp;
use App\Services\SaleProbability\Support\ModelConfig;

class ElasticitySignal
{
    public const SIGNAL_NAME = 'elasticity';
    public const SKIP_REASON = 'insufficient_elasticity_data';
    public const REQUIRED    = false;

    /**
     * Normalises the elasticity proxy slope to [0, 1].
     *
     * Raw input: elasticityDaysPerPct — days of DOM added per 1% price deviation.
     * A negative value means price cuts accelerate sales (ideal = −2 days/%);
     * a positive value means the market is price-inelastic (bad = +2 days/%).
     *
     * Formula: clamp((CLAMP_MAX − clamp(raw, CLAMP_MIN, CLAMP_MAX)) / (CLAMP_MAX − CLAMP_MIN), 0, 1)
     * Range clamped to [−5, +5] before mapping.
     * Anchors: −5 → 1.0 (most helpful); 0 → 0.5; +5 → 0.0 (most harmful).
     *
     * Optional signal — skip only records null; does NOT count toward required_signals_missing.
     */
    public function compute(SaleProbabilityInput $input): array
    {
        $raw = $input->marketAnalyticsResult->elasticityDaysPerPct;

        $anchors = [
            'clamp_min' => ModelConfig::ELASTICITY_CLAMP_MIN,
            'clamp_max' => ModelConfig::ELASTICITY_CLAMP_MAX,
            'ideal'     => ModelConfig::ELASTICITY_IDEAL,
            'bad'       => ModelConfig::ELASTICITY_BAD,
        ];

        if ($raw === null) {
            return [
                'raw'         => null,
                'normalized'  => null,
                'skip'        => true,
                'skip_reason' => self::SKIP_REASON,
                'anchors'     => $anchors,
            ];
        }

        $clamped    = Clamp::between($raw, ModelConfig::ELASTICITY_CLAMP_MIN, ModelConfig::ELASTICITY_CLAMP_MAX);
        $range      = ModelConfig::ELASTICITY_CLAMP_MAX - ModelConfig::ELASTICITY_CLAMP_MIN;
        $normalized = Clamp::between(
            (ModelConfig::ELASTICITY_CLAMP_MAX - $clamped) / $range,
            0.0,
            1.0,
        );

        return [
            'raw'         => $raw,
            'normalized'  => round($normalized, 6),
            'skip'        => false,
            'skip_reason' => null,
            'anchors'     => $anchors,
        ];
    }
}
