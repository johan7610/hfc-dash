<?php

namespace App\Services\SaleProbability\Signals;

use App\Services\SaleProbability\DTOs\SaleProbabilityInput;
use App\Services\SaleProbability\Support\ModelConfig;
use App\Services\SaleProbability\Support\Sigmoid;

class DemandSupplySignal
{
    public const SIGNAL_NAME = 'pressure';
    public const SKIP_REASON = 'insufficient_stock_pressure_data';
    public const REQUIRED    = true;

    /**
     * Normalises demand-supply ratio to [0, 1] via sigmoid.
     *
     * Formula: sigmoid((raw - OFFSET) * STEEPNESS)
     * Anchors: DSR 1.0 → 0.5 (balanced); DSR 2.0 → ≈0.95 (demand); DSR 0.0 → ≈0.05 (oversupply).
     */
    public function compute(SaleProbabilityInput $input): array
    {
        $raw = $input->marketAnalyticsResult->demandSupplyRatio;

        $anchors = [
            'sigmoid_offset'     => ModelConfig::PRESSURE_SIGMOID_OFFSET,
            'sigmoid_steepness'  => ModelConfig::PRESSURE_SIGMOID_STEEPNESS,
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

        $normalized = Sigmoid::compute(
            ($raw - ModelConfig::PRESSURE_SIGMOID_OFFSET) * ModelConfig::PRESSURE_SIGMOID_STEEPNESS
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
