<?php

namespace App\Services\SaleProbability\Signals;

use App\Services\SaleProbability\DTOs\SaleProbabilityInput;
use App\Services\SaleProbability\Support\Clamp;
use App\Services\SaleProbability\Support\ModelConfig;

class MonthsOfInventorySignal
{
    public const SIGNAL_NAME = 'absorption';
    public const SKIP_REASON = 'insufficient_absorption_data';
    public const REQUIRED    = true;

    /**
     * Normalises months-of-inventory (absorption rate) to [0, 1].
     *
     * Formula: clamp((BAD - raw) / (BAD - IDEAL), 0, 1)
     * Anchors: ≤IDEAL months → 1.0 (seller's market); ≥BAD months → 0.0.
     */
    public function compute(SaleProbabilityInput $input): array
    {
        $raw = $input->marketAnalyticsResult->monthsOfInventory;

        $anchors = [
            'ideal_months' => ModelConfig::ABSORPTION_IDEAL_MONTHS,
            'bad_months'   => ModelConfig::ABSORPTION_BAD_MONTHS,
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

        $range      = ModelConfig::ABSORPTION_BAD_MONTHS - ModelConfig::ABSORPTION_IDEAL_MONTHS;
        $normalized = Clamp::between(
            (ModelConfig::ABSORPTION_BAD_MONTHS - $raw) / $range,
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
