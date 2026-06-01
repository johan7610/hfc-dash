<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Competitor Stock build — per-version whitelist mirroring
 * included_comp_ids_json. Semantics:
 *   null  → no opinion yet — show all scored competitors (default).
 *   []    → agent has explicitly unticked everything — empty section.
 *   [ids] → only the listed prospecting_listing IDs render in the
 *           Active Competition section.
 *
 * Held as `array` cast on PresentationVersion (matches included_comp_ids_json).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('presentation_versions', function (Blueprint $table) {
            $table->json('included_competitor_ids_json')
                ->nullable()
                ->after('included_comp_ids_json');
        });
    }

    public function down(): void
    {
        Schema::table('presentation_versions', function (Blueprint $table) {
            $table->dropColumn('included_competitor_ids_json');
        });
    }
};
