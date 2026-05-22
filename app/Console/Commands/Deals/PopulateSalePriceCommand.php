<?php

declare(strict_types=1);

namespace App\Console\Commands\Deals;

use App\Services\Deals\DealPropertyLinkService;
use Illuminate\Console\Command;

/**
 * Phase 3i E2 — populate the new canonical sale_price + sale_date columns
 * from legacy property_value + registration_date. Idempotent.
 *
 *   php artisan deals:populate-sale-price --agency=1
 *   php artisan deals:populate-sale-price --dry-run
 */
final class PopulateSalePriceCommand extends Command
{
    protected $signature = 'deals:populate-sale-price
        {--agency= : Restrict to a single agency_id (otherwise all agencies)}
        {--dry-run : Report counts without writing}';

    protected $description = 'Phase 3i — copy property_value→sale_price and registration_date→sale_date where missing.';

    public function handle(DealPropertyLinkService $svc): int
    {
        $agencyId = $this->option('agency') !== null ? (int) $this->option('agency') : null;
        $dryRun   = (bool) $this->option('dry-run');

        $summary = $svc->populateSaleColumns($agencyId, $dryRun);

        $this->table(['Outcome', 'Count'], [
            ['Rows touched',         $summary['touched']],
            ['sale_price filled',    $summary['sale_price_filled']],
            ['sale_date filled',     $summary['sale_date_filled']],
        ]);

        if ($dryRun) {
            $this->warn('Dry run — nothing was written.');
        }

        return self::SUCCESS;
    }
}
