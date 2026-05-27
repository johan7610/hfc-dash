<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3d — record what MicSnapshotHydrator did during this compile.
 *
 * Stored as JSON so future hydrators can extend without further migrations:
 *   {sold_comps_inserted, active_listings_inserted, suburb_metrics_snapshotted,
 *    cma_metrics_snapshotted, source_reports[]}
 *
 * Spec: Phase 3d build prompt §2.3.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('presentation_versions', function (Blueprint $table) {
            if (!Schema::hasColumn('presentation_versions', 'hydration_summary_json')) {
                $table->json('hydration_summary_json')->nullable()->after('data_snapshot_json');
            }
        });
    }

    public function down(): void
    {
        Schema::table('presentation_versions', function (Blueprint $table) {
            if (Schema::hasColumn('presentation_versions', 'hydration_summary_json')) {
                $table->dropColumn('hydration_summary_json');
            }
        });
    }
};
