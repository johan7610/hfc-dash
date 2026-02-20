<?php

namespace App\Services\MarketAnalytics\Metrics;

/**
 * PricePerSqmDeviationMetric — subject price/m² vs market median (step 2.6).
 *
 * Measures how far a subject property's price per square metre sits above or
 * below the median price/m² of its comparable sold set.
 *
 * Formula (when data is sufficient):
 *   subject_price_per_sqm      = subject_price_inc / subject_size_m2
 *   median_comp_price_per_sqm  = median( sold_price_inc / size_m2 )
 *                                over comps with both fields > 0
 *   deviation_pct              = (subject_price_per_sqm − median) / median × 100
 *
 * Raw (unrounded) price/sqm values are used for the deviation calculation to
 * avoid intermediate rounding cascade; rounded display values are stored in
 * the breakdown separately. deviation_pct is rounded to 4 decimal places.
 * price/sqm display values are rounded to 2 decimal places (cents-per-metre).
 *
 * Comps missing size_m2 (null, zero, or negative) are silently excluded; they
 * are not an error — they simply lack this data dimension. Until the deals
 * adapter provides size_m2, all comps will be excluded and the metric will
 * skip with insufficient_comp_sizes.
 *
 * Skip conditions (evaluated in order; first match wins):
 *   subject_size_missing      — subject_size_m2 is null/≤0, OR subject_price_inc is null
 *   insufficient_comp_sizes   — comps with valid size_m2 < MIN_COMP_SIZES (3)
 *   median_comp_size_missing  — defensive guard (valid comp set produced no median)
 */
class PricePerSqmDeviationMetric
{
    public const FORMULA_NAME    = 'price_sqm_deviation_v1';
    public const MIN_COMP_SIZES  = 3;

    /**
     * Compute price/m² deviation.
     *
     * @param  ?int    $subjectSizeM2   Subject floor area in m² (from input)
     * @param  ?float  $subjectPriceInc Subject sold/list price incl. VAT (from input)
     * @param  array   $compRows        ComparableSet rows (each may have size_m2 + sold_price_inc)
     * @param  string  $compsHash       SHA-256 of the comparable set (for audit trail)
     * @return array{value: float|null, skip_reason: string|null, breakdown: array}
     */
    public function compute(
        ?int    $subjectSizeM2,
        ?float  $subjectPriceInc,
        array   $compRows,
        string  $compsHash,
    ): array {
        $breakdown = [
            'formula_name'              => self::FORMULA_NAME,
            'subject_size_m2'           => $subjectSizeM2,
            'subject_price_inc'         => $subjectPriceInc,
            'subject_price_per_sqm'     => null,   // null until computable
            'comps_with_size_count'     => 0,
            'median_comp_price_per_sqm' => null,   // null until computable
            'deviation_pct'             => null,
            'comps_hash'                => $compsHash,
            'value'                     => null,
            'skip_reason'               => null,
        ];

        // --- Skip conditions ---

        if ($subjectSizeM2 === null || $subjectSizeM2 <= 0 || $subjectPriceInc === null) {
            return $this->skip('subject_size_missing', $breakdown);
        }

        // --- Extract valid comp price/sqm values ---

        $compPricesPerSqm = [];
        foreach ($compRows as $row) {
            $size  = isset($row['size_m2']) ? (int)$row['size_m2'] : null;
            $price = isset($row['sold_price_inc']) ? (float)$row['sold_price_inc'] : null;

            if ($size === null || $size <= 0 || $price === null || $price <= 0.0) {
                continue;
            }

            $compPricesPerSqm[] = $price / $size;
        }

        $compsWithSize = count($compPricesPerSqm);
        $breakdown['comps_with_size_count'] = $compsWithSize;

        if ($compsWithSize < self::MIN_COMP_SIZES) {
            return $this->skip('insufficient_comp_sizes', $breakdown);
        }

        // --- Compute median ---

        sort($compPricesPerSqm);  // ascending

        $medianRaw = $this->median($compPricesPerSqm);

        if ($medianRaw === null) {
            return $this->skip('median_comp_size_missing', $breakdown);  // defensive
        }

        // --- Compute deviation ---

        $subjectRaw    = $subjectPriceInc / $subjectSizeM2;
        $deviationRaw  = ($subjectRaw - $medianRaw) / $medianRaw * 100;

        $subjectPerSqm = round($subjectRaw, 2);
        $medianPerSqm  = round($medianRaw, 2);
        $deviationPct  = round($deviationRaw, 4);

        $breakdown['subject_price_per_sqm']     = $subjectPerSqm;
        $breakdown['median_comp_price_per_sqm'] = $medianPerSqm;
        $breakdown['deviation_pct']             = $deviationPct;
        $breakdown['value']                     = $deviationPct;

        return [
            'value'       => $deviationPct,
            'skip_reason' => null,
            'breakdown'   => $breakdown,
        ];
    }

    // -------------------------------------------------------------------------

    /**
     * Standard median: middle value for odd n; average of two middles for even n.
     * Input array MUST be sorted ascending.
     *
     * @param  float[] $sorted
     */
    private function median(array $sorted): ?float
    {
        $n = count($sorted);
        if ($n === 0) {
            return null;
        }

        $mid = (int)floor($n / 2);

        if ($n % 2 === 1) {
            return $sorted[$mid];
        }

        return ($sorted[$mid - 1] + $sorted[$mid]) / 2.0;
    }

    private function skip(string $reason, array $breakdown): array
    {
        $breakdown['skip_reason'] = $reason;

        return ['value' => null, 'skip_reason' => $reason, 'breakdown' => $breakdown];
    }
}
