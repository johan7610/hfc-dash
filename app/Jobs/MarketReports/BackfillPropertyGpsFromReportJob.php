<?php

declare(strict_types=1);

namespace App\Jobs\MarketReports;

use App\Models\MarketReports\MarketReport;
use App\Models\Property;
use App\Models\Prospecting\TrackedProperty;
use App\Services\Geocoding\PropertyGeoBackfillService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Phase 3f C2 — after a market report parses with a fresh subject GPS,
 * propagate the geo enrichment back to any matching Property + TrackedProperty
 * row. This makes "import a CMA" the canonical path for filling CoreX's
 * spatial layer organically — no extra agent step required.
 *
 * Matching: case-insensitive contains-match on suburb + address_needle (same
 * fragment logic the resolver uses). Only updates rows that don't already
 * have GPS.
 */
final class BackfillPropertyGpsFromReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $reportId) {}

    public function handle(PropertyGeoBackfillService $svc): void
    {
        $report = MarketReport::query()->withoutGlobalScopes()->find($this->reportId);
        if (!$report || $report->subject_latitude === null || $report->subject_longitude === null) {
            return;
        }

        $subject = (string) $report->subject_address;
        $suburb  = (string) $report->source_suburb;
        if ($subject === '') return;

        $needles = $this->needles($subject);
        if (empty($needles)) return;

        $matched = 0;

        // Properties — agency-scoped to the report.
        $propQuery = Property::query()->withoutGlobalScopes();
        if ($report->agency_id) {
            $propQuery->where('agency_id', $report->agency_id);
        }
        $propQuery->where(function ($q) use ($needles) {
            foreach ($needles as $n) {
                $q->orWhereRaw('LOWER(address) LIKE ?', ['%' . $n . '%']);
            }
        });
        if ($suburb !== '') {
            $propQuery->whereRaw('LOWER(suburb) LIKE ?', ['%' . mb_strtolower($suburb) . '%']);
        }
        $propQuery->where(function ($q) {
            $q->whereNull('latitude')->orWhereNull('longitude');
        });
        foreach ($propQuery->limit(200)->get() as $property) {
            try {
                $svc->backfillProperty($property);
                $matched++;
            } catch (\Throwable $e) {
                Log::warning('Property GPS backfill failed', ['id' => $property->id, 'err' => $e->getMessage()]);
            }
        }

        // Tracked properties — agency-scoped too.
        $tpQuery = TrackedProperty::query()->withoutGlobalScopes();
        if ($report->agency_id) {
            $tpQuery->where('agency_id', $report->agency_id);
        }
        $tpQuery->where(function ($q) use ($needles) {
            foreach ($needles as $n) {
                $q->orWhereRaw('LOWER(CONCAT_WS(\' \', street_number, street_name)) LIKE ?', ['%' . $n . '%']);
            }
        });
        if ($suburb !== '') {
            $tpQuery->whereRaw('LOWER(suburb) LIKE ?', ['%' . mb_strtolower($suburb) . '%']);
        }
        $tpQuery->where(function ($q) {
            $q->whereNull('latitude')->orWhereNull('longitude');
        });
        foreach ($tpQuery->limit(200)->get() as $tp) {
            try {
                $svc->backfillTrackedProperty($tp);
                $matched++;
            } catch (\Throwable $e) {
                Log::warning('TP GPS backfill failed', ['id' => $tp->id, 'err' => $e->getMessage()]);
            }
        }

        Log::info('BackfillPropertyGpsFromReportJob: complete', [
            'report_id' => $report->id,
            'matched'   => $matched,
        ]);
    }

    private function needles(string $address): array
    {
        $needles = [];
        foreach (explode(',', $address) as $piece) {
            $piece = mb_strtolower(trim($piece));
            if (mb_strlen($piece) >= 8) $needles[] = $piece;
            $stripped = preg_replace('/^\d+\s+/', '', $piece);
            if ($stripped !== null && $stripped !== $piece && mb_strlen($stripped) >= 8) {
                $needles[] = $stripped;
            }
        }
        return array_values(array_unique($needles));
    }
}
