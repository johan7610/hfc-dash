<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Competitor Stock — top-N display cap. The review screen renders only
 * the top N competitor cards by match score (descending) by default,
 * auto-ticked into included_competitor_ids_json. The full ranked list
 * lives in the manual-picker modal where the agent can tick additional
 * rows below the cap or untick auto-picks.
 *
 * Solves the 400-property wall: an apartment subject with 30 sectional
 * comps used to render 30 cards on the review screen — a wall of cards
 * the agent had to scroll through. With the cap, 10 cards render by
 * default, 20 stay queryable in the modal.
 *
 * Default 10 — enough to make the section feel "thick" with competition
 * without the wall. Agency-configurable per the standing rule (no
 * hardcoded thresholds in code).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            $table->unsignedTinyInteger('competitor_stock_default_display_count')
                ->default(10)
                ->after('competitor_stock_min_same_type')
                ->comment('Competitor Stock — top-N display cap on the review screen + auto-tick floor. Rest live in the manual-picker modal.');
        });
    }

    public function down(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            $table->dropColumn('competitor_stock_default_display_count');
        });
    }
};
