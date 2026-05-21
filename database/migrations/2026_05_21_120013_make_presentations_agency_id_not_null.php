<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MIC Phase A1 — make `presentations.agency_id` NOT NULL.
 *
 * Guarded: refuses to advance if any row is still NULL (orphaned branch_id
 * or a row inserted between #12 and this migration).
 */
return new class extends Migration {
    public function up(): void
    {
        $stillNull = DB::table('presentations')->whereNull('agency_id')->count();
        if ($stillNull > 0) {
            throw new \RuntimeException(
                "presentations still has {$stillNull} row(s) with NULL agency_id. "
                . 'Investigate branch_id orphans (or re-run migration 2026_05_21_120012_backfill_agency_id_on_presentations) before re-attempting this migration.'
            );
        }

        // Same pattern as #10 — drop nullOnDelete FK (if it exists), change
        // column to NOT NULL, re-add FK with RESTRICT. Idempotent because
        // the column may have existed pre-migration without an FK.
        $hasFk = !empty(DB::select(
            "SELECT CONSTRAINT_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS "
            . "WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'presentations' "
            . "AND REFERENCED_TABLE_NAME = 'agencies'"
        ));

        if ($hasFk) {
            Schema::table('presentations', function (Blueprint $table) {
                $table->dropForeign(['agency_id']);
            });
        }

        Schema::table('presentations', function (Blueprint $table) {
            $table->unsignedBigInteger('agency_id')->nullable(false)->change();
        });

        Schema::table('presentations', function (Blueprint $table) {
            $table->foreign('agency_id')
                  ->references('id')->on('agencies')
                  ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('presentations', function (Blueprint $table) {
            $table->dropForeign(['agency_id']);
        });

        Schema::table('presentations', function (Blueprint $table) {
            $table->unsignedBigInteger('agency_id')->nullable()->change();
        });

        Schema::table('presentations', function (Blueprint $table) {
            $table->foreign('agency_id')
                  ->references('id')->on('agencies')
                  ->nullOnDelete();
        });
    }
};
