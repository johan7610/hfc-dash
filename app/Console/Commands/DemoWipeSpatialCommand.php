<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Phase 3h Step 8 — wipe all is_demo=true rows across the spatial layer.
 *
 *   php artisan demo:wipe-spatial --confirm                (wipes agency 1)
 *   php artisan demo:wipe-spatial --agency=2 --confirm     (wipes agency 2)
 *
 * Requires --confirm to actually execute. Without --confirm, prints what
 * would be deleted but takes no action. This protects against accidental
 * `demo:wipe-spatial` on the live HFC demo site where the synthetic data
 * IS the data being shown to customers.
 */
final class DemoWipeSpatialCommand extends Command
{
    protected $signature = 'demo:wipe-spatial
        {--agency=1 : Target agency_id}
        {--confirm  : Required to actually execute}';

    protected $description = 'Delete every is_demo=true row across properties/reports/owners/deals for an agency.';

    public function handle(): int
    {
        $agencyId = (int) $this->option('agency');
        $confirm  = (bool) $this->option('confirm');

        $tables = [
            'market_report_comp_rows'        => null,
            'scheme_owners'                  => 'agency_id',
            'deals'                          => 'agency_id',
            'presentation_sold_comps'        => null,
            'presentation_active_listings'   => null,
            'tracked_properties'             => 'agency_id',
            'properties'                     => 'agency_id',
            'market_reports'                 => 'agency_id',
        ];

        $counts = [];
        foreach ($tables as $table => $agencyCol) {
            $q = DB::table($table)->where('is_demo', true);
            if ($agencyCol) $q->where($agencyCol, $agencyId);
            $counts[$table] = $q->count();
        }
        $total = array_sum($counts);

        if (!$confirm) {
            $this->warn('Dry run (no --confirm) — would delete:');
            foreach ($counts as $t => $n) $this->line(sprintf('  %-32s %d', $t, $n));
            $this->newLine();
            $this->line("Total: {$total} rows. Re-run with --confirm to execute.");
            return self::SUCCESS;
        }

        $this->info("Wiping demo data for agency #{$agencyId}…");
        $deleted = [];
        foreach ($tables as $table => $agencyCol) {
            $q = DB::table($table)->where('is_demo', true);
            if ($agencyCol) $q->where($agencyCol, $agencyId);
            $deleted[$table] = $q->delete();
            $this->line(sprintf('  %-32s %d deleted', $table, $deleted[$table]));
        }
        $this->newLine();
        $this->info('Total: ' . array_sum($deleted) . ' rows deleted.');
        return self::SUCCESS;
    }
}
