<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Parser-dispatch fix Phase 3 — deploy-time backstop for the
 * `cma_info_vicinity_sale` market_report_types row.
 *
 * Source of truth: `database/seeders/MarketReportTypesSeeder.php` ships
 * a row for this type (cma_info_vicinity_sale → CmaInfoVicinitySaleParser).
 * BUT seeders are not automatically applied on deploy — the operator has
 * to run `php artisan db:seed --class=MarketReportTypesSeeder --force`
 * after pulling. The team has been bitten by missed seeders before, so
 * critical type rows that the dispatch chain depends on get a migration
 * backstop alongside the seeder.
 *
 * Without this row:
 *   - upload pipeline detects vicinity parser → key='cma_info_vicinity_sale'
 *   - MarketReportType::where('key', 'cma_info_vicinity_sale')->first() → null
 *   - report stored with report_type_id = null, parses OK via the
 *     ParseMarketReportJob detect() branch, but the index/show views label
 *     it "—" instead of "CMA Info — Vicinity Sale".
 *
 * Idempotent: keyed on `market_report_types.key`. Re-running the migration
 * is a no-op when the row already exists. If the row is soft-deletable
 * (current schema has no deleted_at on this table — check before assuming),
 * the existence check covers that too.
 *
 * Mirrors the seed_mic_permissions / seed_mic_restore_reports_permission
 * pattern — deploy backstop, not a replacement for the seeder.
 */
return new class extends Migration {
    public function up(): void
    {
        $exists = DB::table('market_report_types')
            ->where('key', 'cma_info_vicinity_sale')
            ->exists();

        if ($exists) {
            if (PHP_SAPI === 'cli') {
                fwrite(STDOUT, "    → cma_info_vicinity_sale type row already present — no-op" . PHP_EOL);
            }
            return;
        }

        $now = now();
        $expectedFields = [
            'subject_property.address', 'subject_property.suburb_scope',
            'subject_property_type', 'radius_meters',
            'sales[].distance_m', 'sales[].erf_number', 'sales[].address',
            'sales[].erf_usage', 'sales[].property_type', 'sales[].extent_m2',
            'sales[].sale_date', 'sales[].sale_price', 'sales[].r_per_m2',
            'summary.lower_range', 'summary.middle_range', 'summary.upper_range',
            'summary.average', 'summary.average_r_per_m2',
        ];

        DB::table('market_report_types')->insert([
            'key'                  => 'cma_info_vicinity_sale',
            'display_name'         => 'CMA Info — Vicinity Sale (Residential / Vacant Land)',
            'parser_class'         => 'App\\Services\\MarketReports\\Parsers\\CmaInfoVicinitySaleParser',
            'auto_approve'         => 1,
            'expected_fields_json' => json_encode($expectedFields),
            'sample_file_path'     => null,
            'created_at'           => $now,
            'updated_at'           => $now,
        ]);

        if (PHP_SAPI === 'cli') {
            fwrite(STDOUT, "    → cma_info_vicinity_sale type row inserted (CmaInfoVicinitySaleParser)" . PHP_EOL);
        }
    }

    public function down(): void
    {
        // Only remove the row this migration inserted. Don't disturb a
        // manually-curated row if one was already present.
        DB::table('market_report_types')->where('key', 'cma_info_vicinity_sale')->delete();
    }
};
