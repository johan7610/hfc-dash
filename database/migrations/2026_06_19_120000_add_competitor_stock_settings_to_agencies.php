<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Competitor Stock build — agency-configurable thresholds for the
 * presentation Active Competition matcher. Per CLAUDE.md NN#6 + the
 * standing rule "all thresholds are agency settings", these defaults
 * NEVER live in code constants.
 *
 *   competitor_stock_default_beds_tolerance      ± beds for the synthetic
 *                                                ContactMatch range
 *                                                (default 1)
 *   competitor_stock_default_price_tolerance_pct ± percent of subject price
 *                                                for the synthetic match's
 *                                                price band (default 20)
 *   competitor_stock_min_score                   minimum match score to
 *                                                include in the section
 *                                                (default 50 — Approximate
 *                                                tier floor per Core Matches)
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            $table->unsignedTinyInteger('competitor_stock_default_beds_tolerance')
                ->default(1)
                ->after('cma_compute_iqr_multiplier')
                ->comment('Competitor Stock — ± beds window for synthetic ContactMatch (Core Matches scorer).');
            $table->unsignedTinyInteger('competitor_stock_default_price_tolerance_pct')
                ->default(20)
                ->after('competitor_stock_default_beds_tolerance')
                ->comment('Competitor Stock — ± percent price band for synthetic match (e.g. 20 = ±20%).');
            $table->unsignedTinyInteger('competitor_stock_min_score')
                ->default(50)
                ->after('competitor_stock_default_price_tolerance_pct')
                ->comment('Competitor Stock — minimum match score (Core Matches 0-100) to include in section. 50 = Approximate tier floor.');
        });
    }

    public function down(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            $table->dropColumn([
                'competitor_stock_default_beds_tolerance',
                'competitor_stock_default_price_tolerance_pct',
                'competitor_stock_min_score',
            ]);
        });
    }
};
