<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 9a hardening — two fixes from the pre-staging audit:
 *
 * 1. presentation_outcomes.recorded_by_user_id was RESTRICT (Laravel default
 *    for foreignId(...)->constrained() without ->nullOnDelete()). That blocks
 *    soft+hard deletion of any user who has ever recorded an outcome —
 *    exactly the kind of constraint that bites in production when an agent
 *    leaves and HR tries to retire their user. Fix by dropping the FK and
 *    re-adding it with nullOnDelete().
 *
 * 2. presentation_deliveries 60-second idempotency check (Phase 6) hashes
 *    (sent_by_user_id, presentation_id, recipient_email) and looks for a
 *    recent row. Currently scans via two single-column indexes — adding
 *    the composite gives the query a covering index.
 */
return new class extends Migration {
    public function up(): void
    {
        // (1) presentation_outcomes.recorded_by_user_id → nullable + nullOnDelete.
        //     Original schema had this NOT NULL with default RESTRICT FK.
        //     MySQL won't accept ON DELETE SET NULL on a NOT NULL column.
        //     Tolerant of partial state — drop FK only when present, drop
        //     residual index, relax NOT NULL, then re-add the FK.
        if (Schema::hasTable('presentation_outcomes')) {
            $fkExists = \DB::selectOne(
                "SELECT COUNT(*) AS n FROM information_schema.referential_constraints
                 WHERE constraint_schema = DATABASE() AND constraint_name = 'po_recorder_fk'"
            )->n ?? 0;
            if ($fkExists) {
                Schema::table('presentation_outcomes', function (Blueprint $table) {
                    $table->dropForeign('po_recorder_fk');
                });
            }
            // Drop the residual index that shares the FK name (left over from
            // a half-applied earlier run of this same migration).
            $indexExists = \DB::selectOne(
                "SELECT COUNT(*) AS n FROM information_schema.statistics
                 WHERE table_schema = DATABASE() AND table_name = 'presentation_outcomes'
                 AND index_name = 'po_recorder_fk'"
            )->n ?? 0;
            if ($indexExists) {
                \DB::statement('ALTER TABLE presentation_outcomes DROP INDEX po_recorder_fk');
            }
            // Relax NOT NULL via raw SQL — ->change() needs DBAL.
            \DB::statement('ALTER TABLE presentation_outcomes MODIFY recorded_by_user_id BIGINT UNSIGNED NULL');
            Schema::table('presentation_outcomes', function (Blueprint $table) {
                $table->foreign('recorded_by_user_id', 'po_recorder_fk')
                    ->references('id')->on('users')
                    ->nullOnDelete();
            });
        }

        // (2) presentation_deliveries composite for 60s idempotency check.
        if (Schema::hasTable('presentation_deliveries')) {
            $existing = collect(Schema::getIndexes('presentation_deliveries'))
                ->pluck('name')->all();
            if (!in_array('pd_idempotency_idx', $existing, true)) {
                Schema::table('presentation_deliveries', function (Blueprint $table) {
                    $table->index(
                        ['sent_by_user_id', 'presentation_id', 'recipient_email'],
                        'pd_idempotency_idx',
                    );
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('presentation_deliveries')) {
            Schema::table('presentation_deliveries', function (Blueprint $table) {
                try { $table->dropIndex('pd_idempotency_idx'); } catch (\Throwable $e) { /* tolerant */ }
            });
        }
        // Reverting the FK to RESTRICT would re-introduce the bug — skip.
    }
};
