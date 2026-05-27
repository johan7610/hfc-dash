<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * MIC Phase A1 — backfill `presentations.agency_id` via `branches.agency_id`.
 *
 * Every presentation has a branch_id; every branch has an agency_id. Single
 * JOIN-UPDATE resolves the chain. Orphans (branch_id pointing at a missing
 * branch row) are left NULL and reported — migration #13's NOT NULL guard
 * will catch them and refuse to advance.
 */
return new class extends Migration {
    public function up(): void
    {
        // MySQL multi-table UPDATE — safe and atomic.
        $affected = DB::update(
            'UPDATE presentations p '
            . 'INNER JOIN branches b ON b.id = p.branch_id '
            . 'SET p.agency_id = b.agency_id '
            . 'WHERE p.agency_id IS NULL'
        );

        $stillNull = DB::table('presentations')->whereNull('agency_id')->count();

        if (PHP_SAPI === 'cli') {
            fwrite(STDOUT, "    → presentations backfill: set agency_id via branch on {$affected} rows (still-null: {$stillNull})" . PHP_EOL);
        }
    }

    public function down(): void
    {
        // Reverse the backfill so #11's down() can drop the column cleanly.
        DB::table('presentations')->update(['agency_id' => null]);
    }
};
