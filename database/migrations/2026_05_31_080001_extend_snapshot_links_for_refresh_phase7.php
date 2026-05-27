<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 7 Part B1 — extend presentation_snapshot_links with refresh + supersede.
 *
 * The Phase 4 migration already added refresh_requested_at/_by_name/_message
 * as one-shot single-fire columns. Phase 7 layers on top:
 *
 *   refresh_request_count           — total requests on this link (per-row counter)
 *   refresh_acknowledged_at         — agent's acknowledgement timestamp
 *   refresh_acknowledged_by_user_id — who acknowledged
 *   refresh_resulted_in_link_id     — the NEW link issued in response
 *   superseded_by_link_id           — when this link is replaced by a refresh
 *   superseded_at                   — timestamp of the supersede
 *
 * superseded_at + superseded_by_link_id together implement the "auto-revoke
 * when refresh delivered" behaviour without losing the audit trail. The
 * PublicPresentationController treats superseded_at as an effective
 * revoked_at for resolution purposes.
 *
 * Note: explicit short index names are required — MySQL caps identifiers at
 * 64 chars and the auto-generated names on this long table name overflow.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('presentation_snapshot_links', function (Blueprint $table) {
            if (!Schema::hasColumn('presentation_snapshot_links', 'refresh_request_count')) {
                $table->unsignedInteger('refresh_request_count')->default(0)->after('refresh_requested_message');
            }
            if (!Schema::hasColumn('presentation_snapshot_links', 'refresh_acknowledged_at')) {
                $table->timestamp('refresh_acknowledged_at')->nullable()->after('refresh_request_count');
            }
            if (!Schema::hasColumn('presentation_snapshot_links', 'refresh_acknowledged_by_user_id')) {
                $table->foreignId('refresh_acknowledged_by_user_id')->nullable()
                    ->after('refresh_acknowledged_at')
                    ->constrained('users', 'id', 'psl_refresh_ack_user_fk')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('presentation_snapshot_links', 'refresh_resulted_in_link_id')) {
                $table->foreignId('refresh_resulted_in_link_id')->nullable()
                    ->after('refresh_acknowledged_by_user_id')
                    ->constrained('presentation_snapshot_links', 'id', 'psl_refresh_result_link_fk')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('presentation_snapshot_links', 'superseded_by_link_id')) {
                $table->foreignId('superseded_by_link_id')->nullable()
                    ->after('refresh_resulted_in_link_id')
                    ->constrained('presentation_snapshot_links', 'id', 'psl_superseded_by_link_fk')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('presentation_snapshot_links', 'superseded_at')) {
                $table->timestamp('superseded_at')->nullable()->after('superseded_by_link_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('presentation_snapshot_links', function (Blueprint $table) {
            foreach ([
                'psl_superseded_by_link_fk',
                'psl_refresh_result_link_fk',
                'psl_refresh_ack_user_fk',
            ] as $fkName) {
                try { $table->dropForeign($fkName); } catch (\Throwable $e) { /* tolerant */ }
            }
            foreach ([
                'superseded_at', 'superseded_by_link_id',
                'refresh_resulted_in_link_id', 'refresh_acknowledged_by_user_id',
                'refresh_acknowledged_at', 'refresh_request_count',
            ] as $col) {
                if (Schema::hasColumn('presentation_snapshot_links', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
