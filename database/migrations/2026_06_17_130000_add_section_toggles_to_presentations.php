<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Build 4 — agency-default section toggles + per-version snapshot.
 *
 * Two pieces, mirroring Build 3's split:
 *   (1) agencies.presentations_default_show_<section> — 9 booleans, all
 *       default true. Agency edits in settings to set "we never show
 *       inflow_absorption" / "always include holding_cost", etc.
 *   (2) presentation_versions.enabled_sections_json — the per-version
 *       snapshot. Materialised at compile() from the agency defaults.
 *       Updated by the review screen toggles. Frozen at publish so
 *       historic PDFs render with the sections that were enabled
 *       then, even if the agency has since flipped defaults.
 *
 * The 9 sections (per Phase A audit — see Build 4 prompt):
 *   executive_summary    (floor — locked-on, surfaced for transparency)
 *   market_overview
 *   recent_sales
 *   spatial_view
 *   cma_analysis
 *   active_competition
 *   inflow_absorption
 *   holding_cost
 *   pricing_strategy     (hard depends on cma_analysis)
 *
 * Cover + Subject Facts Card are NOT in this list — they're the
 * structural floor of every presentation and not user-toggleable.
 */
return new class extends Migration {
    /**
     * The toggleable sections. Ordering mirrors PDF render order.
     * Kept in the migration so the columns/seeds stay self-documenting.
     */
    private const SECTIONS = [
        'executive_summary',
        'market_overview',
        'recent_sales',
        'spatial_view',
        'cma_analysis',
        'active_competition',
        'inflow_absorption',
        'holding_cost',
        'pricing_strategy',
    ];

    public function up(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            // Anchor the new columns just after the existing presentation
            // settings block from migration 2026_05_23_100001.
            $after = 'presentations_default_period_months';
            foreach (self::SECTIONS as $section) {
                $col = 'presentations_default_show_' . $section;
                if (!Schema::hasColumn('agencies', $col)) {
                    $table->boolean($col)->default(true)->after($after);
                    $after = $col;
                }
            }
        });

        Schema::table('presentation_versions', function (Blueprint $table) {
            if (!Schema::hasColumn('presentation_versions', 'enabled_sections_json')) {
                $table->json('enabled_sections_json')->nullable()->after('condition_label')
                      ->comment('Build 4 — per-version snapshot of which report sections render. Null means "use agency defaults at compile time".');
            }
        });

        // Backfill existing PUBLISHED versions to have every section
        // enabled — they were rendered with the full report before
        // Build 4, so freezing that state is correct.
        $allOn = array_fill_keys(self::SECTIONS, true);
        $allOnJson = json_encode($allOn);
        DB::table('presentation_versions')
            ->whereNull('enabled_sections_json')
            ->update(['enabled_sections_json' => $allOnJson]);
    }

    public function down(): void
    {
        Schema::table('presentation_versions', function (Blueprint $table) {
            if (Schema::hasColumn('presentation_versions', 'enabled_sections_json')) {
                $table->dropColumn('enabled_sections_json');
            }
        });
        Schema::table('agencies', function (Blueprint $table) {
            foreach (self::SECTIONS as $section) {
                $col = 'presentations_default_show_' . $section;
                if (Schema::hasColumn('agencies', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
