<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3h Step 1 — flag synthetic rows across the spatial layer.
 *
 * Every demo-seeded row gets is_demo=true. The flag drives:
 *   - engine adapters / hydrator / coverage queries (Step 9): real subjects
 *     read only real data; demo subjects read only demo data
 *   - Map module include_demo query param (Step 10): demo toggle on the UI
 *   - demo:wipe-spatial command (Step 8): single WHERE clause across tables
 *
 * imported_listings table doesn't exist in this codebase (Phase 3f confirmed)
 * so it's not in the list. Once it lands the flag should be added there too.
 *
 * Indexed on every table so the demo/real isolation filters stay fast.
 */
return new class extends Migration {
    /** @var array<int, string> */
    private array $tables = [
        'properties',
        'tracked_properties',
        'market_reports',
        'market_report_comp_rows',
        'scheme_owners',
        'deals',
        'presentation_sold_comps',
        'presentation_active_listings',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (!Schema::hasTable($tableName)) continue;
            if (Schema::hasColumn($tableName, 'is_demo')) continue;

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->boolean('is_demo')->default(false)->index("idx_{$tableName}_is_demo");
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            if (!Schema::hasTable($tableName)) continue;
            if (!Schema::hasColumn($tableName, 'is_demo')) continue;

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->dropIndex("idx_{$tableName}_is_demo");
                $table->dropColumn('is_demo');
            });
        }
    }
};
