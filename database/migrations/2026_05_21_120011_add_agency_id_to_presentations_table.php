<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MIC Phase A1 — add `agency_id` to `presentations` (spec §3.3.2).
 *
 * Presentations currently carry `branch_id` only — multi-tenancy is enforced
 * indirectly through branches. Adding agency_id directly enables AgencyScope
 * and unblocks every cross-pillar surface that wants to filter by agency
 * without joining.
 *
 * Backfill via `branches.agency_id` in migration #12. NOT NULL in #13.
 */
return new class extends Migration {
    public function up(): void
    {
        // Idempotency: the column may already exist on some environments —
        // older partial migrations added agency_id to presentations without
        // an FK or index. Add only what's missing.
        if (!Schema::hasColumn('presentations', 'agency_id')) {
            Schema::table('presentations', function (Blueprint $table) {
                $table->unsignedBigInteger('agency_id')->nullable()->after('id');
            });
        }

        $hasFk = !empty(\Illuminate\Support\Facades\DB::select(
            "SELECT CONSTRAINT_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS "
            . "WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'presentations' "
            . "AND REFERENCED_TABLE_NAME = 'agencies'"
        ));
        if (!$hasFk) {
            Schema::table('presentations', function (Blueprint $table) {
                $table->foreign('agency_id')
                      ->references('id')->on('agencies')
                      ->nullOnDelete();
            });
        }

        $hasIndex = !empty(\Illuminate\Support\Facades\DB::select(
            "SHOW INDEX FROM presentations WHERE Key_name = 'idx_presentations_agency_id'"
        ));
        if (!$hasIndex) {
            Schema::table('presentations', function (Blueprint $table) {
                $table->index('agency_id', 'idx_presentations_agency_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('presentations', function (Blueprint $table) {
            $table->dropForeign(['agency_id']);
            $table->dropIndex('idx_presentations_agency_id');
            $table->dropColumn('agency_id');
        });
    }
};
