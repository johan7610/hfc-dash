<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\AI\AINarrativeCache;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Read-only aggregator over `ai_narrative_cache`. Backs the (future) admin
 * AI-usage dashboard at /admin/ai-usage. Lands now alongside the gateway
 * so it ships tested.
 *
 * Spec: .ai/specs/mic-complete-spec.md §4.8 (cost dashboard, cache hit rate).
 */
final class AICostAggregator
{
    /**
     * Total ZAR spend in the given month, optionally narrowed to one agency.
     *
     * @param int|null $agencyId   Null = all agencies (global + agency-scoped rows).
     * @param CarbonInterface|null $month  Defaults to current month.
     */
    public function monthlyCostZar(?int $agencyId = null, ?CarbonInterface $month = null): float
    {
        $month ??= Carbon::now();
        $q = AINarrativeCache::query()
            ->whereBetween('generated_at', [
                $month->copy()->startOfMonth(),
                $month->copy()->endOfMonth(),
            ]);
        if ($agencyId !== null) {
            $q->where('agency_id', $agencyId);
        }
        return (float) $q->sum('cost_zar');
    }

    /**
     * Spend broken down by narrative_type (weekly_brief, tile_copy, …).
     *
     * @return array<string, float>  narrative_type => ZAR sum
     */
    public function monthlyCostByNarrativeType(?int $agencyId = null, ?CarbonInterface $month = null): array
    {
        $month ??= Carbon::now();
        $q = AINarrativeCache::query()
            ->select('narrative_type', DB::raw('SUM(cost_zar) AS cost_zar_sum'))
            ->whereBetween('generated_at', [
                $month->copy()->startOfMonth(),
                $month->copy()->endOfMonth(),
            ])
            ->groupBy('narrative_type');
        if ($agencyId !== null) {
            $q->where('agency_id', $agencyId);
        }
        return $q->get()
            ->mapWithKeys(fn ($r) => [(string) $r->narrative_type => (float) $r->cost_zar_sum])
            ->all();
    }

    /**
     * Cache hit rate (%) over the last N days.
     *
     * We can't directly count hits vs misses from `ai_narrative_cache`
     * (the table only holds the latest row per cache_key) — so this is
     * approximated from `agent_activity_events`: ai.narrative_generated
     * events ≈ misses (every generation writes the row + fires the event);
     * cache hits do NOT fire the event. The denominator is the union
     * of generations + lookups; lookups are inferred from total HTTP
     * requests against the gateway, which we approximate as 1× cache rows
     * (one per cache_key alive in the window) since every active key was
     * looked up at least once to keep it alive.
     *
     * Best-effort metric. Replace with a per-call counter (Redis) if you
     * want true precision.
     */
    public function cacheHitRate(int $days = 30): float
    {
        $since = Carbon::now()->subDays($days);

        // Generations in the window (each = one cache MISS that hit the API).
        $generations = DB::table('agent_activity_events')
            ->where('event_type', 'ai.narrative_generated')
            ->where('occurred_at', '>=', $since)
            ->count();

        // Estimated hits = active cache rows whose generated_at is OLDER than
        // the window (i.e. their first generation already happened, every
        // subsequent fetch in the window was a hit). Heuristic but stable.
        $estimatedHits = AINarrativeCache::query()
            ->where('generated_at', '<', $since)
            ->where('expires_at', '>=', $since)
            ->count();

        $denominator = $generations + $estimatedHits;
        if ($denominator === 0) return 0.0;

        return round(($estimatedHits / $denominator) * 100, 2);
    }

    /**
     * Total input + output tokens for the current month, optionally scoped to
     * one agency.
     *
     * @return array{input:int, output:int}
     */
    public function totalTokensThisMonth(?int $agencyId = null): array
    {
        $now = Carbon::now();
        $q = AINarrativeCache::query()
            ->whereBetween('generated_at', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()]);
        if ($agencyId !== null) {
            $q->where('agency_id', $agencyId);
        }
        $row = $q->selectRaw('COALESCE(SUM(input_tokens),0) AS in_t, COALESCE(SUM(output_tokens),0) AS out_t')->first();
        return [
            'input'  => (int) ($row->in_t ?? 0),
            'output' => (int) ($row->out_t ?? 0),
        ];
    }
}
