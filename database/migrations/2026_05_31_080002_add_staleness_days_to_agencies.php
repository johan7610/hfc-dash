<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 7 Part B2 — agency-level staleness window for presentation links.
 *
 * presentation_staleness_days is the cutoff at which the public viewer starts
 * showing the "data may be dated — request refresh" banner. It is independent
 * from snapshot_link_default_expiry_days (Phase 4): staleness is about the
 * underlying market data ageing, expiry is about the link's lifecycle.
 *
 * Default 21 matches the established snapshot expiry default. The valid range
 * is 7-90 days (enforced in the settings form / service layer, not at DB
 * level — keeping the column tolerant lets ops tune without a migration).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            if (!Schema::hasColumn('agencies', 'presentation_staleness_days')) {
                $table->unsignedSmallInteger('presentation_staleness_days')
                    ->default(21)
                    ->after('snapshot_link_ip_masking')
                    ->comment('Days after issue before public viewer shows the data-may-be-dated banner. Range 7-90 enforced in app layer.');
            }
        });
    }

    public function down(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            if (Schema::hasColumn('agencies', 'presentation_staleness_days')) {
                $table->dropColumn('presentation_staleness_days');
            }
        });
    }
};
