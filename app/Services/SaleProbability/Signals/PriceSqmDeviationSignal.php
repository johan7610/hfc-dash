<?php

namespace App\Services\SaleProbability\Signals;

use App\Services\SaleProbability\DTOs\SaleProbabilityInput;
use App\Services\SaleProbability\Support\Clamp;
use App\Services\SaleProbability\Support\ModelConfig;

class PriceSqmDeviationSignal
{
    public const SIGNAL_NAME = 'price';
    public const SKIP_REASON = 'insufficient_price_sqm_data';
    public const REQUIRED    = true;

    /**
     * Normalises price-per-m² deviation to [0, 1].
     *
     * Formula: clamp(0.5 - (deviation_pct / PRICE_DEVIATION_RANGE), 0, 1)
     * Anchors: −30% deviation → 1.0 (cheap vs market); +30% deviation → 0.0 (expensive).
     */
    public function compute(SaleProbabilityInput $input): array
    {
        $raw = $input->marketAnalyticsResult->pricePerSqmDeviationPct;

        $anchors = [
            'deviation_range' => ModelConfig::PRICE_DEVIATION_RANGE,
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

        $normalized = Clamp::between(
            0.5 - ($raw / ModelConfig::PRICE_DEVIATION_RANGE),
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
