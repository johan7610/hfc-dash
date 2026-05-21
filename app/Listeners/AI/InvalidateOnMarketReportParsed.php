<?php

declare(strict_types=1);

namespace App\Listeners\AI;

use App\Events\MarketReports\MarketReportParsed;
use App\Services\AI\CacheInvalidator;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * MIC Phase B2 — when a market report is parsed (data points written), the
 * agency's weekly brief and any suburb-pocket narratives that may have used
 * the prior dataset are now stale.
 *
 * Failure-isolated.
 *
 * Spec: .ai/specs/mic-complete-spec.md §4.8.
 */
final class InvalidateOnMarketReportParsed
{
    public function __construct(private readonly CacheInvalidator $invalidator) {}

    public function handle(MarketReportParsed $event): void
    {
        try {
            $agencyId = (int) ($event->report->agency_id ?? 0);
            if ($agencyId > 0) {
                $this->invalidator->invalidateWeeklyBriefForAgency($agencyId);
            }
            // Suburb-pocket narratives are scoped by suburb id; the spec
            // convention is suburb_pocket:suburb:{id}: — we don't know which
            // suburbs this report touched without re-querying market_data_points,
            // so the next data-point query that crosses a fresh suburb will
            // naturally regenerate. Cheaper than scanning the whole pocket
            // namespace; revisit if drift becomes visible in the dashboard.
        } catch (Throwable $e) {
            Log::warning('InvalidateOnMarketReportParsed failed', [
                'report_id' => $event->report->id ?? null,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}
