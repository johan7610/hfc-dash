<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Property;
use App\Models\Prospecting\TrackedProperty;
use App\Services\Geocoding\PropertyGeoBackfillService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Phase 3f C1 — bulk GPS backfill.
 *
 *   php artisan geocoding:backfill              (both types, all unresolved)
 *   php artisan geocoding:backfill --type=properties
 *   php artisan geocoding:backfill --type=tracked_properties --limit=500
 *   php artisan geocoding:backfill --force      (re-resolves rows that already have GPS)
 *
 * Reports progress every 50 rows + a final breakdown by source/confidence.
 * Warns when GOOGLE_GEOCODING_API_KEY isn't configured — the run will still
 * complete from MIC + portal_capture data, but coverage may be low.
 */
final class GeocodingBackfillCommand extends Command
{
    protected $signature = 'geocoding:backfill
        {--limit=0 : Max rows to process (0 = all unresolved)}
        {--type=both : both | properties | tracked_properties}
        {--force : Re-resolve rows that already have GPS}';

    protected $description = 'Resolve GPS + municipal value for properties and tracked properties via the geocoding waterfall.';

    public function handle(PropertyGeoBackfillService $svc): int
    {
        $type  = (string) $this->option('type');
        $limit = (int)    $this->option('limit');
        $force = (bool)   $this->option('force');

        if (!in_array($type, ['both', 'properties', 'tracked_properties'], true)) {
            $this->error("Invalid --type: {$type}");
            return self::INVALID;
        }

        if (empty(config('services.google.geocoding_api_key'))) {
            $this->warn('GOOGLE_GEOCODING_API_KEY is NOT configured.');
            $this->warn('Resolution will be limited to MIC + portal_capture data only.');
            $this->warn('To enable Google Geocoding: add GOOGLE_GEOCODING_API_KEY=... to .env and re-run.');
            $this->newLine();
        }

        $batchId = (string) Str::uuid();
        $tallyTotal = ['resolved' => 0, 'failed' => 0, 'pre_existing' => 0];
        $bySource   = [];

        if ($type === 'both' || $type === 'properties') {
            $this->info('=== Backfilling properties ===');
            $this->runForModel(Property::query()->withoutGlobalScopes(), $svc, 'property', $limit, $force, $batchId, $tallyTotal, $bySource);
            $this->newLine();
        }

        if ($type === 'both' || $type === 'tracked_properties') {
            $this->info('=== Backfilling tracked_properties ===');
            $this->runForModel(TrackedProperty::query()->withoutGlobalScopes(), $svc, 'tracked_property', $limit, $force, $batchId, $tallyTotal, $bySource);
            $this->newLine();
        }

        $this->newLine();
        $this->info('=== Summary ===');
        $this->line(sprintf(
            'Resolved: %d | Pre-existing GPS: %d | Failed: %d',
            $tallyTotal['resolved'],
            $tallyTotal['pre_existing'],
            $tallyTotal['failed'],
        ));
        if (!empty($bySource)) {
            $this->line('By source:');
            ksort($bySource);
            foreach ($bySource as $src => $n) {
                $this->line("  {$src}: {$n}");
            }
        }
        $this->line("Batch ID: {$batchId}");
        return self::SUCCESS;
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array{resolved:int, failed:int, pre_existing:int} $tallyTotal
     * @param array<string,int> $bySource
     */
    private function runForModel(
        $query,
        PropertyGeoBackfillService $svc,
        string $kind,
        int $limit,
        bool $force,
        string $batchId,
        array &$tallyTotal,
        array &$bySource,
    ): void {
        if (!$force) {
            $query = $query->where(function ($q) {
                $q->whereNull('latitude')->orWhereNull('longitude');
            });
        }
        if ($limit > 0) {
            $query = $query->limit($limit);
        }

        $rows = $query->get();
        $total = $rows->count();
        if ($total === 0) {
            $this->line('  Nothing to resolve.');
            return;
        }

        $processed = 0;
        foreach ($rows as $row) {
            try {
                $result = $kind === 'property'
                    ? $svc->backfillProperty($row, $batchId)
                    : $svc->backfillTrackedProperty($row, $batchId);
            } catch (\Throwable $e) {
                $this->warn("  {$kind}#{$row->id}: " . $e->getMessage());
                $tallyTotal['failed']++;
                $processed++;
                continue;
            }

            $src = $result['source'] ?? 'unknown';
            $bySource[$src] = ($bySource[$src] ?? 0) + 1;

            if ($result['lat_lng_resolved']) {
                $tallyTotal[$src === 'pre_existing' ? 'pre_existing' : 'resolved']++;
            } else {
                $tallyTotal['failed']++;
            }

            $processed++;
            if ($processed % 50 === 0 || $processed === $total) {
                $this->line(sprintf(
                    '  %d/%d processed — %d resolved, %d failed.',
                    $processed,
                    $total,
                    $tallyTotal['resolved'],
                    $tallyTotal['failed'],
                ));
            }
        }
    }
}
