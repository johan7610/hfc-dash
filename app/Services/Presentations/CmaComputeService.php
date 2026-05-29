<?php

declare(strict_types=1);

namespace App\Services\Presentations;

use App\Models\Presentation;
use App\Models\PresentationSoldComp;
use App\Models\PresentationVersion;
use Illuminate\Support\Collection;

/**
 * Build 8a — CoreX's INDEPENDENT CMA compute engine.
 *
 * Today AnalysisDataService::compileCmaValuation reprints the values
 * CMA Info itself stated (cma.lower/middle/upper extracted from the
 * uploaded PDF) with Build 3's condition multiplier on Middle. There
 * is no maths against the actual comp pool — see lineage spec §2.8.
 *
 * This service computes three independent valuations from the in-pool
 * comps:
 *   • method_median      — median of sold_price_inc
 *   • method_mean        — mean of sold_price_inc
 *   • method_rm2_extent  — median R/m² × subject extent
 *
 * Each method produces a condition-adjusted variant alongside the raw.
 * The pool's full distribution (min / p25 / median / p75 / max) is
 * surfaced as pool_stats so the divergence between the methods and
 * CMA Info's stated middle can be diagnosed at-a-glance — pool problem
 * vs method-choice problem.
 *
 * INTERNAL ONLY this build. The output rides on the AnalysisDataService
 * payload as 'cma_computed' (sibling to the existing 'cma_valuation')
 * and is automatically frozen into snapshot_payload at publish via the
 * Build 5 mechanism. No review-screen, PDF, or public/show render.
 *
 * Hard rule: ALL money math uses bcmath. Sums, products, divisions,
 * percentile selection — never native float arithmetic. The condition
 * adjustment likewise uses bcmath here (ConditionAdjustmentService's
 * existing applyToMiddle uses native float — Build 3 contract — but
 * this service must hold the line for its own outputs).
 */
final class CmaComputeService
{
    /** bcmath scale for intermediate money math (cents-level precision). */
    public const SCALE = 2;

    /**
     * Compute CoreX's independent CMA outputs.
     *
     * Caller provides the already-loaded comp collection. The pool
     * membership rule (whereNull('deleted_at') + included_comp_ids_json
     * whitelist) is enforced by the caller before handing in — this
     * service trusts whatever it receives as "in pool".
     *
     * @param  Presentation                 $presentation
     * @param  Collection<PresentationSoldComp> $inPoolComps
     * @param  bool                         $isSectional
     * @param  array{pct?: ?float, label?: ?string, source?: string}  $conditionContext
     * @return array
     */
    public function compute(
        Presentation $presentation,
        Collection $inPoolComps,
        bool $isSectional,
        array $conditionContext = [],
    ): array {
        // CMA Info's stated middle — copied alongside as the at-a-glance
        // delta anchor. Read from extracted presentation_fields directly
        // so we don't depend on compileCmaValuation's output shape.
        $cmaInfoStatedMiddle = $this->intOrNull(
            optional($presentation->fields->firstWhere('field_key', 'cma.middle_range'))->final_value
        );

        $conditionPct = isset($conditionContext['pct']) && is_numeric($conditionContext['pct'])
            ? (float) $conditionContext['pct']
            : null;

        $extent = $this->resolveSubjectExtentM2($presentation, $isSectional);

        $prices       = $this->extractPrices($inPoolComps);
        $pricesWithSz = $this->extractPricesWithSize($inPoolComps);

        return [
            'cma_info_stated_middle' => $cmaInfoStatedMiddle,
            'pool_stats'             => $this->poolStats($prices, count($pricesWithSz)),
            'method_median'          => $this->methodMedian($prices, $conditionPct),
            'method_mean'             => $this->methodMean($prices, $conditionPct),
            'method_rm2_extent'      => $this->methodRm2Extent($pricesWithSz, $extent, $conditionPct),
        ];
    }

    // ── Distribution stats ──────────────────────────────────────────────

    /**
     * pool_stats — Min / P25 / Median / P75 / Max of sold_price_inc,
     * plus the two counts the user asked for (total, with-size).
     *
     * Percentile rule: nearest-rank on the sorted list:
     *   p25 = sorted[floor(n * 0.25)]
     *   median = sorted[floor(n * 0.5)]
     *   p75 = sorted[floor(n * 0.75)]
     * Matches the Phase A probe output exactly so the discovery numbers
     * reproduce.
     *
     * @param  list<string> $sortedPricesAsc   already sorted ascending
     * @param  int          $nWithSize
     * @return array
     */
    private function poolStats(array $sortedPricesAsc, int $nWithSize): array
    {
        $n = count($sortedPricesAsc);
        if ($n === 0) {
            return [
                'n_total'     => 0,
                'n_with_size' => 0,
                'min'         => null,
                'p25'         => null,
                'median'      => null,
                'p75'         => null,
                'max'         => null,
            ];
        }
        return [
            'n_total'     => $n,
            'n_with_size' => $nWithSize,
            'min'         => $this->toInt($sortedPricesAsc[0]),
            'p25'         => $this->toInt($sortedPricesAsc[(int) floor($n * 0.25)]),
            'median'      => $this->toInt($sortedPricesAsc[(int) floor($n * 0.5)]),
            'p75'         => $this->toInt($sortedPricesAsc[(int) floor($n * 0.75)]),
            'max'         => $this->toInt($sortedPricesAsc[$n - 1]),
        ];
    }

    // ── method_median ───────────────────────────────────────────────────

    /**
     * @param  list<string> $sortedPricesAsc
     */
    private function methodMedian(array $sortedPricesAsc, ?float $conditionPct): array
    {
        $n = count($sortedPricesAsc);
        if ($n === 0) {
            return $this->emptyMethodResult($conditionPct);
        }
        $raw = $sortedPricesAsc[(int) floor($n * 0.5)];
        return $this->methodResult($raw, $conditionPct, $n);
    }

    // ── method_mean ─────────────────────────────────────────────────────

    /**
     * @param  list<string> $sortedPricesAsc
     */
    private function methodMean(array $sortedPricesAsc, ?float $conditionPct): array
    {
        $n = count($sortedPricesAsc);
        if ($n === 0) {
            return $this->emptyMethodResult($conditionPct);
        }
        $sum = '0';
        foreach ($sortedPricesAsc as $p) {
            $sum = bcadd($sum, $p, self::SCALE);
        }
        $raw = bcdiv($sum, (string) $n, 0); // integer rand for display
        return $this->methodResult($raw, $conditionPct, $n);
    }

    // ── method_rm2_extent ───────────────────────────────────────────────

    /**
     * Median R/m² across comps with both price and size, multiplied by
     * the subject's extent.
     *
     * @param  list<array{price:string, size:int}> $pricesWithSize
     * @param  ?int  $subjectExtent
     */
    private function methodRm2Extent(array $pricesWithSize, ?int $subjectExtent, ?float $conditionPct): array
    {
        if (empty($pricesWithSize) || $subjectExtent === null || $subjectExtent <= 0) {
            return [
                'rm2_median'         => null,
                'subject_extent_m2'  => $subjectExtent,
                'raw'                => null,
                'condition_adjusted' => null,
                'condition_pct'      => $conditionPct,
                'n'                  => count($pricesWithSize),
            ];
        }
        $rm2s = [];
        foreach ($pricesWithSize as $row) {
            $rm2s[] = bcdiv($row['price'], (string) $row['size'], self::SCALE);
        }
        sort($rm2s, SORT_STRING);
        // Re-sort numerically — bcmath strings sort lexicographically in
        // SORT_STRING ('100.00' < '99.00'). Use a usort callback wrapping
        // bccomp for correctness.
        usort($rm2s, fn($a, $b) => bccomp($a, $b, self::SCALE));
        $rm2Median = $rm2s[(int) floor(count($rm2s) * 0.5)];
        $raw       = bcmul($rm2Median, (string) $subjectExtent, 0);

        return [
            'rm2_median'         => $this->toInt($rm2Median),
            'subject_extent_m2'  => $subjectExtent,
            'raw'                => $this->toInt($raw),
            'condition_adjusted' => $this->applyConditionBc($raw, $conditionPct),
            'condition_pct'      => $conditionPct,
            'n'                  => count($pricesWithSize),
        ];
    }

    // ── Subject extent picker ───────────────────────────────────────────

    /**
     * Mirrors Build 7's per-title_type extent read-order so the engine
     * picks the same extent the review screen + PDF size row use.
     *   sectional   → floor area (property.size_m2 || presentation.floor_area_m2)
     *   full/vacant → erf size   (property.erf_size_m2 || presentation.erf_size_m2)
     *   other/null  → try erf first, then size, both layers
     */
    private function resolveSubjectExtentM2(Presentation $presentation, bool $isSectional): ?int
    {
        if ($isSectional) {
            return $this->intOrNull($presentation->property?->size_m2)
                ?? $this->intOrNull($presentation->floor_area_m2);
        }
        return $this->intOrNull($presentation->property?->erf_size_m2)
            ?? $this->intOrNull($presentation->erf_size_m2)
            ?? $this->intOrNull($presentation->property?->size_m2)
            ?? $this->intOrNull($presentation->floor_area_m2);
    }

    // ── Pool extraction ─────────────────────────────────────────────────

    /**
     * Sorted (ascending, bcmath-correct) list of in-pool sold prices.
     *
     * @return list<string>
     */
    private function extractPrices(Collection $comps): array
    {
        $prices = [];
        foreach ($comps as $c) {
            if ($c->sold_price_inc === null) continue;
            $p = (string) (int) $c->sold_price_inc;
            if (bccomp($p, '0', self::SCALE) <= 0) continue;
            $prices[] = $p;
        }
        usort($prices, fn($a, $b) => bccomp($a, $b, self::SCALE));
        return $prices;
    }

    /**
     * In-pool comps that contributed BOTH a non-null price AND a
     * non-zero size_m2. Used as the R/m² denominator. Order does not
     * matter for the median calc — we sort the derived R/m² list.
     *
     * @return list<array{price:string, size:int}>
     */
    private function extractPricesWithSize(Collection $comps): array
    {
        $out = [];
        foreach ($comps as $c) {
            if ($c->sold_price_inc === null) continue;
            $sz = $c->size_m2 !== null ? (int) $c->size_m2 : 0;
            if ($sz <= 0) continue; // div-by-zero guard
            $p = (string) (int) $c->sold_price_inc;
            if (bccomp($p, '0', self::SCALE) <= 0) continue;
            $out[] = ['price' => $p, 'size' => $sz];
        }
        return $out;
    }

    // ── Condition adjustment (bcmath) ───────────────────────────────────

    /**
     * Apply the condition pct to a base value using bcmath. Mirrors
     * ConditionAdjustmentService::applyToMiddle's behaviour but holds
     * the bcmath discipline this build requires for new financial math.
     *
     *   adjusted = round(base * (1 + pct/100))
     */
    private function applyConditionBc(?string $base, ?float $pct): ?int
    {
        if ($base === null) return null;
        if ($pct === null || abs($pct) < 0.005) {
            return $this->toInt($base);
        }
        // pct as a bcmath-safe string. number_format avoids locale woes.
        $pctStr = number_format($pct, 6, '.', '');
        // factor = 1 + pct/100
        $factor = bcadd('1', bcdiv($pctStr, '100', 8), 8);
        $adjusted = bcmul($base, $factor, 4);
        // Round to nearest integer rand (away from zero, simple half-up).
        $adjusted = $this->bcRoundHalfUp($adjusted);
        return $this->toInt($adjusted);
    }

    private function bcRoundHalfUp(string $val): string
    {
        // Add 0.5 (positive) or -0.5 (negative) then truncate via bcadd 0.
        $half = bccomp($val, '0', 8) >= 0 ? '0.5' : '-0.5';
        $adj  = bcadd($val, $half, 8);
        return bcadd($adj, '0', 0);
    }

    // ── Shared method-result shape ──────────────────────────────────────

    private function methodResult(string $raw, ?float $conditionPct, int $n): array
    {
        return [
            'raw'                => $this->toInt($raw),
            'condition_adjusted' => $this->applyConditionBc($raw, $conditionPct),
            'condition_pct'      => $conditionPct,
            'n'                  => $n,
        ];
    }

    private function emptyMethodResult(?float $conditionPct): array
    {
        return [
            'raw'                => null,
            'condition_adjusted' => null,
            'condition_pct'      => $conditionPct,
            'n'                  => 0,
        ];
    }

    // ── Small utilities ─────────────────────────────────────────────────

    private function intOrNull($v): ?int
    {
        if ($v === null) return null;
        if (is_string($v) && trim($v) === '') return null;
        if (is_numeric($v)) {
            $i = (int) $v;
            return $i !== 0 || ((string) $v === '0' || $v === 0)
                ? $i
                : null;
        }
        return null;
    }

    /** Bcmath-string → int. Truncates fraction; no float round-trip. */
    private function toInt(string $bcStr): int
    {
        $whole = bcadd($bcStr, '0', 0);
        return (int) $whole;
    }
}
