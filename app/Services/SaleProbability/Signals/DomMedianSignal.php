<?php

namespace App\Services\SaleProbability\Signals;

use App\Services\SaleProbability\DTOs\SaleProbabilityInput;
use App\Services\SaleProbability\Support\Clamp;
use App\Services\SaleProbability\Support\ModelConfig;

class DomMedianSignal
{
    public const SIGNAL_NAME = 'dom';
    public const SKIP_REASON = 'insufficient_dom_data';
    public const REQUIRED    = true;

    /**
     * Normalises the market DOM median (p50) to [0, 1].
     *
     * Formula: clamp(1 - (p50 - IDEAL) / (BAD - IDEAL), 0, 1)
     * Anchors: ≤IDEAL days → 1.0 (hot market); ≥BAD days → 0.0 (slow market).
     *
     * Raw value is domCurve['p50'] from MarketAnalyticsResult.
     * Skip if domCurve is null or p50 is missing.
     */
    public function compute(SaleProbabilityInput $input): array
    {
        $domCurve = $input->marketAnalyticsResult->domCurve;
        $p50      = is_array($domCurve) ? ($domCurve['p50'] ?? null) : null;

        $anchors = [
            'ideal_days' => ModelConfig::DOM_IDEAL_DAYS,
            'bad_days'   => ModelConfig::DOM_BAD_DAYS,
        ];

        if ($p50 === null) {
            return [
                'raw'         => null,
                'normalized'  => null,
                'skip'        => true,
                'skip_reason' => self::SKIP_REASON,
                'anchors'     => $anchors,
            ];
        }

        $range      = ModelConfig::DOM_BAD_DAYS - ModelConfig::DOM_IDEAL_DAYS;
        $normalized = Clamp::between(
            1.0 - ((float) $p50 - ModelConfig::DOM_IDEAL_DAYS) / $range,
            0.0,
            1.0,
        );

        return [
            'raw'         => (float) $p50,
            'normalized'  => round($normalized, 6),
            'skip'        => false,
            'skip_reason' => null,
            'anchors'     => $anchors,
        ];
    }
}
