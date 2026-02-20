<?php

namespace App\Services\MarketAnalytics\Metrics;

/**
 * ElasticityProxyMetric — price elasticity proxy via bucket regression (step 2.7).
 *
 * Measures how DOM varies with price deviation from market median, using OLS
 * linear regression on bucket-averaged data points.
 *
 * Methodology:
 *   1. Baseline: median sold_price_inc across all comps with a valid price.
 *   2. Each usable comp (has sold_price_inc AND resolvable dom_days) gets a
 *      price_deviation_pct: (sold_price_inc − median_price) / median_price × 100
 *   3. Comps are classified into 5 price-deviation bands.
 *   4. avg_dom_days computed per non-empty bucket (rounded to 1 dp for display).
 *   5. OLS regression on (midpoint_pct, avg_dom_days) across non-empty buckets.
 *   6. slope (β) = days per 1% price increase.
 *      Negative slope → higher-priced items sell faster (typical seller market).
 *
 * DOM resolution per comp (first available source wins):
 *   a. dom_days key already in row (pre-computed)
 *   b. listed_date + sold_date in row (computed directly, same as DomCurveMetric)
 *   c. domResolutionMap[row_hash] if provided (future Tier 2 proxy)
 *
 * Band definitions (lower bound inclusive, upper bound exclusive):
 *   Band 0  "<-15"       deviation < −15 %      midpoint −20
 *   Band 1  "-15_to_-5"  −15 ≤ deviation < −5   midpoint −10
 *   Band 2  "-5_to_5"    −5  ≤ deviation <  5   midpoint   0
 *   Band 3  "5_to_15"     5  ≤ deviation < 15   midpoint  10
 *   Band 4  ">15"        deviation ≥  15 %      midpoint  20
 *
 * Skip conditions (evaluated in order; first match wins):
 *   dom_unavailable      — 0 usable comps (no DOM data at all)
 *   insufficient_samples — usable < minSamples (default 5)
 *   insufficient_buckets — non-empty buckets < minBuckets (default 3)
 *
 * Rounding:
 *   slope     → 4 decimal places
 *   r_squared → 4 decimal places
 *   avg_dom   → 1 decimal place (display in breakdown)
 *
 * Constants:
 *   FORMULA_NAME = 'elasticity_proxy_v1'
 *   UNITS        = 'days_per_pct'
 */
class ElasticityProxyMetric
{
    public const FORMULA_NAME = 'elasticity_proxy_v1';
    public const UNITS        = 'days_per_pct';

    private const BUCKET_LABELS    = ['<-15', '-15_to_-5', '-5_to_5', '5_to_15', '>15'];
    private const BUCKET_MIDPOINTS = [-20.0, -10.0, 0.0, 10.0, 20.0];

    /**
     * Compute price–DOM elasticity proxy.
     *
     * @param  array   $compRows         ComparableSet rows
     * @param  string  $compsHash        SHA-256 of the comparable set (audit trail)
     * @param  ?array  $domResolutionMap row_hash → dom_days (future Tier 2; null = disabled)
     * @param  int     $minSamples       Minimum usable comps required (default 5)
     * @param  int     $minBuckets       Minimum non-empty buckets for regression (default 3)
     * @return array{value: float|null, skip_reason: string|null, breakdown: array}
     */
    public function compute(
        array   $compRows,
        string  $compsHash,
        ?array  $domResolutionMap = null,
        int     $minSamples       = 5,
        int     $minBuckets       = 3,
    ): array {
        $breakdown = [
            'formula_name'       => self::FORMULA_NAME,
            'units'              => self::UNITS,
            'comps_hash'         => $compsHash,
            'usable_count'       => 0,
            'skipped_count'      => 0,
            'median_price_inc'   => null,
            'bucket_table'       => [],
            'slope_days_per_pct' => null,
            'r_squared'          => null,
            'value'              => null,
            'skip_reason'        => null,
        ];

        // Mutable bucket accumulators (keyed 0–4); _dom_sum stripped before returning.
        $buckets = [];
        for ($i = 0; $i < 5; $i++) {
            $buckets[$i] = [
                'band_label'   => self::BUCKET_LABELS[$i],
                'midpoint_pct' => self::BUCKET_MIDPOINTS[$i],
                'count'        => 0,
                'avg_dom_days' => null,
                '_dom_sum'     => 0.0,
            ];
        }

        // ── Step 1: Compute baseline median from all comps with a valid price ──
        $allPrices = [];
        foreach ($compRows as $row) {
            $price = isset($row['sold_price_inc']) ? (float)$row['sold_price_inc'] : null;
            if ($price !== null && $price > 0.0) {
                $allPrices[] = $price;
            }
        }

        sort($allPrices);
        $medianPrice                 = $this->median($allPrices);
        $breakdown['median_price_inc'] = $medianPrice;

        // ── Step 2: Classify each comp into a bucket ──────────────────────────
        $usableCount  = 0;
        $skippedCount = 0;

        foreach ($compRows as $row) {
            $price   = isset($row['sold_price_inc']) ? (float)$row['sold_price_inc'] : null;
            $domDays = $this->resolveDom($row, $domResolutionMap);

            if (
                $price      === null || $price <= 0.0
                || $domDays === null || $domDays < 0
                || $medianPrice === null || $medianPrice <= 0.0
            ) {
                $skippedCount++;
                continue;
            }

            $deviationPct = ($price - $medianPrice) / $medianPrice * 100.0;
            $idx          = $this->assignBucket($deviationPct);

            $buckets[$idx]['count']++;
            $buckets[$idx]['_dom_sum'] += (float)$domDays;
            $usableCount++;
        }

        $breakdown['usable_count']  = $usableCount;
        $breakdown['skipped_count'] = $skippedCount;

        // ── Skip: dom_unavailable ─────────────────────────────────────────────
        if ($usableCount === 0) {
            return $this->skip('dom_unavailable', $breakdown, $buckets);
        }

        // ── Skip: insufficient_samples ────────────────────────────────────────
        if ($usableCount < $minSamples) {
            return $this->skip('insufficient_samples', $breakdown, $buckets);
        }

        // ── Compute avg_dom per bucket and count non-empty ones ───────────────
        $nonEmptyCount = 0;
        for ($i = 0; $i < 5; $i++) {
            if ($buckets[$i]['count'] > 0) {
                $buckets[$i]['avg_dom_days'] = round(
                    $buckets[$i]['_dom_sum'] / $buckets[$i]['count'],
                    1
                );
                $nonEmptyCount++;
            }
        }

        // ── Skip: insufficient_buckets ────────────────────────────────────────
        if ($nonEmptyCount < $minBuckets) {
            return $this->skip('insufficient_buckets', $breakdown, $buckets);
        }

        // ── Step 3: OLS regression on non-empty buckets ───────────────────────
        $points = [];
        for ($i = 0; $i < 5; $i++) {
            if ($buckets[$i]['count'] > 0) {
                $points[] = [
                    'x' => self::BUCKET_MIDPOINTS[$i],
                    'y' => $buckets[$i]['avg_dom_days'],
                ];
            }
        }

        [$slope, $rSquared] = $this->olsRegression($points);

        $slopeRounded    = round($slope,    4);
        $rSquaredRounded = round($rSquared, 4);

        $breakdown['slope_days_per_pct'] = $slopeRounded;
        $breakdown['r_squared']          = $rSquaredRounded;
        $breakdown['value']              = $slopeRounded;
        $breakdown['bucket_table']       = $this->formatBuckets($buckets);

        return [
            'value'       => $slopeRounded,
            'skip_reason' => null,
            'breakdown'   => $breakdown,
        ];
    }

    // -------------------------------------------------------------------------

    /**
     * Assign a price deviation % to one of 5 bucket indices (0–4).
     * Bands: lower bound inclusive, upper bound exclusive; last band open-ended.
     *   0: dev < −15     1: −15 ≤ dev < −5     2: −5 ≤ dev < 5
     *   3: 5 ≤ dev < 15  4: dev ≥ 15
     */
    private function assignBucket(float $deviationPct): int
    {
        if ($deviationPct < -15.0) return 0;
        if ($deviationPct < -5.0)  return 1;
        if ($deviationPct < 5.0)   return 2;
        if ($deviationPct < 15.0)  return 3;
        return 4;
    }

    /**
     * Standard median. Input array MUST be sorted ascending.
     * Returns null for empty input.
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

        return $n % 2 === 1
            ? (float)$sorted[$mid]
            : ($sorted[$mid - 1] + $sorted[$mid]) / 2.0;
    }

    /**
     * Resolve DOM days for a comp row. First available source wins:
     *   1. dom_days key already in row
     *   2. listed_date + sold_date computed directly (UTC, avoids DST)
     *   3. domResolutionMap[row_hash] when map is provided
     *
     * Returns null if DOM cannot be determined or is anomalous.
     */
    private function resolveDom(array $row, ?array $domResolutionMap): ?int
    {
        // Priority 1: pre-computed dom_days
        if (array_key_exists('dom_days', $row) && $row['dom_days'] !== null) {
            $d = (int)$row['dom_days'];
            return $d >= 0 ? $d : null;
        }

        // Priority 2: compute from listed_date + sold_date
        $listedDate = $row['listed_date'] ?? null;
        $soldDate   = $row['sold_date']   ?? null;

        if ($listedDate !== null && $soldDate !== null) {
            return $this->calcDom($soldDate, $listedDate);
        }

        // Priority 3: resolution map (future Tier 2)
        if ($domResolutionMap !== null) {
            $hash = $row['row_hash'] ?? null;
            if ($hash !== null && array_key_exists($hash, $domResolutionMap)) {
                $d = $domResolutionMap[$hash];
                return ($d !== null && (int)$d >= 0) ? (int)$d : null;
            }
        }

        return null;
    }

    /**
     * Compute DOM in whole days. Returns null if anomalous (listed after sold).
     * Uses UTC midnight for both dates to avoid DST boundary artefacts.
     */
    private function calcDom(string $soldDate, string $listedDate): ?int
    {
        try {
            $utc    = new \DateTimeZone('UTC');
            $listed = new \DateTimeImmutable($listedDate, $utc);
            $sold   = new \DateTimeImmutable($soldDate,   $utc);
        } catch (\Exception) {
            return null;
        }

        $diff = $listed->diff($sold);

        return $diff->invert === 1 ? null : $diff->days;
    }

    /**
     * OLS linear regression on an array of ['x' => float, 'y' => float] points.
     * Returns [slope, r_squared].
     *
     * When SS_tot ≈ 0 (all y equal → perfect horizontal fit), R² = 1.0.
     * When the x values are all identical (degenerate), returns [0.0, 0.0].
     * R² is clamped to [0, 1] to avoid slightly negative values from float errors.
     *
     * @param  array $points   Each element: ['x' => float, 'y' => float]
     * @return array{float, float}  [slope, r_squared]
     */
    private function olsRegression(array $points): array
    {
        $n     = count($points);
        $sumX  = 0.0;
        $sumY  = 0.0;
        $sumXY = 0.0;
        $sumX2 = 0.0;

        foreach ($points as $pt) {
            $sumX  += $pt['x'];
            $sumY  += $pt['y'];
            $sumXY += $pt['x'] * $pt['y'];
            $sumX2 += $pt['x'] * $pt['x'];
        }

        $denom = $n * $sumX2 - $sumX * $sumX;

        if (abs($denom) < 1e-10) {
            return [0.0, 0.0];  // all x identical — slope undefined
        }

        $slope     = ($n * $sumXY - $sumX * $sumY) / $denom;
        $intercept = ($sumY - $slope * $sumX) / $n;

        $yMean = $sumY / $n;
        $ssTot = 0.0;
        $ssRes = 0.0;

        foreach ($points as $pt) {
            $ssTot += ($pt['y'] - $yMean) ** 2;
            $ssRes += ($pt['y'] - ($intercept + $slope * $pt['x'])) ** 2;
        }

        $rSquared = ($ssTot < 1e-10) ? 1.0 : max(0.0, 1.0 - $ssRes / $ssTot);

        return [$slope, $rSquared];
    }

    /**
     * Strip the internal _dom_sum accumulator before exposing bucket data.
     *
     * @param  array $buckets  Raw bucket array (0-indexed, keyed 0–4)
     * @return array           Plain list of bucket descriptors (no _dom_sum)
     */
    private function formatBuckets(array $buckets): array
    {
        return array_values(array_map(static function (array $b): array {
            unset($b['_dom_sum']);
            return $b;
        }, $buckets));
    }

    private function skip(string $reason, array $breakdown, array $buckets): array
    {
        $breakdown['skip_reason']  = $reason;
        $breakdown['bucket_table'] = $this->formatBuckets($buckets);

        return ['value' => null, 'skip_reason' => $reason, 'breakdown' => $breakdown];
    }
}
