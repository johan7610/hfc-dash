<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MIC Phase A1 — make `p24_listings.agency_id` NOT NULL.
 *
 * Runs after the backfill (#9). Guards against rows still NULL so the ALTER
 * doesn't fail mid-deploy: if any row is still NULL, throw with a useful
 * diagnostic so the operator can investigate rather than letting MySQL
 * dump a generic "Cannot add NOT NULL" error.
 */
return new class extends Migration {
    public function up(): void
    {
        $stillNull = DB::table('p24_listings')->whereNull('agency_id')->count();
        if ($stillNull > 0) {
            throw new \RuntimeException(
                "p24_listings still has {$stillNull} row(s) with NULL agency_id. "
                . 'Re-run migration 2026_05_21_120009_backfill_agency_id_on_p24_listings or investigate before re-attempting this migration.'
            );
        }

        // MySQL refuses NOT NULL on a column whose FK action is SET NULL —
        // SET NULL can't write into a NOT NULL column. Drop the existing
        // nullOnDelete FK from #8, change the column, re-add the FK with
        // RESTRICT (default, no-action) which matches the spec's intent of
        // "never wipe market data on agency delete".
        Schema::table('p24_listings', function (Blueprint $table) {
            $table->dropForeign(['agency_id']);
        });

        Schema::table('p24_listings', function (Blueprint $table) {
            $table->unsignedBigInteger('agency_id')->nullable(false)->change();
        });

        Schema::table('p24_listings', function (Blueprint $table) {
            $table->foreign('agency_id')
                  ->references('id')->on('agencies')
                  ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        // Reverse: drop the RESTRICT FK, relax to nullable, re-add the
        // nullOnDelete FK from #8.
        Schema::table('p24_listings', function (Blueprint $table) {
            $table->dropForeign(['agency_id']);
        });

        Schema::table('p24_listings', function (Blueprint $table) {
            $table->unsignedBigInteger('agency_id')->nullable()->change();
        });

        Schema::table('p24_listings', function (Blueprint $table) {
            $table->foreign('agency_id')
                  ->references('id')->on('agencies')
                  ->nullOnDelete();
        });
    }
};
