<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\HoldingCostDataPoint;
use App\Models\Presentation;
use App\Models\Property;
use App\Support\Presentations\SuburbMatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-shot retroactive seed for holding_cost_data_points from:
 *   1. properties.levy / rates_taxes / special_levy
 *      (source = property_record — authoritative captured values)
 *   2. presentations.monthly_*
 *      (source = manual_capture — agent values entered on past
 *      presentations, useful supplementary signal for Tier 1 averaging)
 *
 * Idempotent: re-run drops any prior auto-seed rows for the same
 * source + source_ref combo before re-inserting, so repeated runs
 * stay consistent with whatever's currently in properties /
 * presentations. Soft-deleted rows aren't touched (admin may have
 * manually excluded them).
 *
 * Use:
 *   php artisan holding-cost:seed-from-properties [--agency=N] [--dry-run]
 */
final class SeedHoldingCostFromProperties extends Command
{
    protected $signature = 'holding-cost:seed-from-properties
                            {--agency= : Limit to a specific agency_id}
                            {--dry-run : Report counts only, no writes}';

    protected $description = 'Seed holding_cost_data_points from properties (Tier 0 captures) and presentations (historic agent values).';

    public function handle(): int
    {
        $agencyId = $this->option('agency') !== null ? (int) $this->option('agency') : null;
        $dryRun   = (bool) $this->option('dry-run');

        $this->info('Holding-cost dataset seed' . ($dryRun ? ' [DRY RUN]' : '')
            . ($agencyId !== null ? " (agency {$agencyId})" : ' (all agencies)'));

        $propertiesSeeded   = $this->seedFromProperties($agencyId, $dryRun);
        $presentationsSeeded = $this->seedFromPresentations($agencyId, $dryRun);

        $this->newLine();
        $this->info('Summary' . ($dryRun ? ' (no writes)' : '') . ':');
        $this->line('  From properties (property_record source):');
        foreach ($propertiesSeeded as $component => $count) {
            $this->line('    ' . str_pad($component, 12) . ': ' . $count);
        }
        $this->line('  From presentations (manual_capture source):');
        foreach ($presentationsSeeded as $component => $count) {
            $this->line('    ' . str_pad($component, 12) . ': ' . $count);
        }
        $this->newLine();
        $this->line('  Total property_record:  ' . array_sum($propertiesSeeded));
        $this->line('  Total manual_capture:   ' . array_sum($presentationsSeeded));
        $this->line('  GRAND TOTAL:            ' . (array_sum($propertiesSeeded) + array_sum($presentationsSeeded)));

        return self::SUCCESS;
    }

    /**
     * Walk properties — write one data point per non-null
     * levy / rates_taxes value. levy folds in special_levy when
     * present (same convention HoldingCostEstimator's Tier 0
     * uses).
     *
     * @return array<string, int>
     */
    private function seedFromProperties(?int $agencyId, bool $dryRun): array
    {
        $query = Property::query()->withoutGlobalScopes()->whereNull('deleted_at');
        if ($agencyId !== null) $query->where('agency_id', $agencyId);

        $counts = ['levy' => 0, 'rates' => 0];
        $now    = now();

        $query->chunkById(500, function ($props) use (&$counts, $now, $dryRun) {
            foreach ($props as $p) {
                $context = $this->propertyContext($p);

                // levy (with special_levy added)
                if ($p->levy !== null && (int) $p->levy > 0) {
                    $value = (int) $p->levy + (int) ($p->special_levy ?? 0);
                    if (!$dryRun) {
                        $this->upsertSeed([
                            'agency_id'           => $p->agency_id,
                            'component'           => HoldingCostDataPoint::COMPONENT_LEVY,
                            'monthly_value_zar'   => $value,
                            'scheme_name'         => $context['scheme_name'],
                            'suburb_normalised'   => $context['suburb_normalised'],
                            'property_type'       => $context['property_type'],
                            'title_type'          => $context['title_type'],
                            'property_value_band' => $context['property_value_band'],
                            'property_id'         => $p->id,
                            'source'              => HoldingCostDataPoint::SOURCE_PROPERTY_RECORD,
                            'source_ref'          => 'property:' . $p->id,
                            'created_at'          => $now,
                            'updated_at'          => $now,
                        ]);
                    }
                    $counts['levy']++;
                }

                // rates_taxes
                if ($p->rates_taxes !== null && (int) $p->rates_taxes > 0) {
                    if (!$dryRun) {
                        $this->upsertSeed([
                            'agency_id'           => $p->agency_id,
                            'component'           => HoldingCostDataPoint::COMPONENT_RATES,
                            'monthly_value_zar'   => (int) $p->rates_taxes,
                            'suburb_normalised'   => $context['suburb_normalised'],
                            'property_type'       => $context['property_type'],
                            'title_type'          => $context['title_type'],
                            'property_value_band' => $context['property_value_band'],
                            'property_id'         => $p->id,
                            'source'              => HoldingCostDataPoint::SOURCE_PROPERTY_RECORD,
                            'source_ref'          => 'property:' . $p->id,
                            'created_at'          => $now,
                            'updated_at'          => $now,
                        ]);
                    }
                    $counts['rates']++;
                }
            }
        });

        return $counts;
    }

    /**
     * Walk presentations — write one data point per non-null
     * monthly_* value. Lower confidence than property_record because
     * the values are agent estimates rather than captured per-property
     * facts; the Tier 1 average treats them equally (the agency
     * exclude-grid is the future tightening lever).
     *
     * @return array<string, int>
     */
    private function seedFromPresentations(?int $agencyId, bool $dryRun): array
    {
        $query = Presentation::query()
            ->withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->with('property');
        if ($agencyId !== null) $query->where('agency_id', $agencyId);

        $columnMap = [
            'monthly_rates'     => HoldingCostDataPoint::COMPONENT_RATES,
            'monthly_levies'    => HoldingCostDataPoint::COMPONENT_LEVY,
            'monthly_insurance' => HoldingCostDataPoint::COMPONENT_INSURANCE,
            'monthly_utilities' => HoldingCostDataPoint::COMPONENT_UTILITIES,
            'monthly_garden'    => HoldingCostDataPoint::COMPONENT_GARDEN,
            'monthly_pool'      => HoldingCostDataPoint::COMPONENT_POOL,
            'monthly_security'  => HoldingCostDataPoint::COMPONENT_SECURITY,
        ];

        $counts = array_fill_keys(array_values($columnMap), 0);
        $now    = now();

        $query->chunkById(200, function ($presentations) use (&$counts, $columnMap, $now, $dryRun) {
            foreach ($presentations as $pres) {
                $context = $this->presentationContext($pres);

                foreach ($columnMap as $col => $component) {
                    $val = $pres->{$col} ?? null;
                    if ($val === null || (float) $val <= 0) continue;

                    if (!$dryRun) {
                        $this->upsertSeed([
                            'agency_id'           => $pres->agency_id,
                            'component'           => $component,
                            'monthly_value_zar'   => (int) round((float) $val),
                            'scheme_name'         => $context['scheme_name'],
                            'suburb_normalised'   => $context['suburb_normalised'],
                            'property_type'       => $context['property_type'],
                            'title_type'          => $context['title_type'],
                            'property_value_band' => $context['property_value_band'],
                            'property_id'         => $pres->property_id,
                            'source'              => HoldingCostDataPoint::SOURCE_MANUAL_CAPTURE,
                            'source_ref'          => 'presentation:' . $pres->id . ':' . $col,
                            'created_at'          => $now,
                            'updated_at'          => $now,
                        ]);
                    }
                    $counts[$component]++;
                }
            }
        });

        return $counts;
    }

    /**
     * Idempotent upsert — delete any prior row for the same
     * agency+component+source_ref combo, then insert. Source_ref is
     * the row's identity (e.g. "property:42" → at most one rates
     * + one levy row per property).
     */
    private function upsertSeed(array $row): void
    {
        DB::table('holding_cost_data_points')
            ->where('agency_id', $row['agency_id'])
            ->where('component', $row['component'])
            ->where('source_ref', $row['source_ref'])
            ->whereNull('deleted_at')
            ->delete();
        DB::table('holding_cost_data_points')->insert($row);
    }

    private function propertyContext(Property $p): array
    {
        return [
            'scheme_name'         => null, // No reliable scheme_name column on properties; tracked_properties has complex_name — out of scope for this seed.
            'suburb_normalised'   => $p->suburb_normalised
                ?: SuburbMatcher::normaliseSuburbToken((string) ($p->suburb ?? '')),
            'property_type'       => $p->property_type,
            'title_type'          => $p->title_type,
            'property_value_band' => HoldingCostDataPoint::valueBandFor(
                $p->price !== null ? (int) $p->price : null,
            ),
        ];
    }

    private function presentationContext(Presentation $pres): array
    {
        $property = $pres->property;
        return [
            'scheme_name'         => null,
            'suburb_normalised'   => $property?->suburb_normalised
                ?: SuburbMatcher::normaliseSuburbToken((string) ($pres->suburb ?? '')),
            'property_type'       => $property?->property_type ?? $pres->property_type,
            'title_type'          => $property?->title_type,
            'property_value_band' => HoldingCostDataPoint::valueBandFor(
                $pres->asking_price_inc !== null ? (int) $pres->asking_price_inc : null,
            ),
        ];
    }
}
