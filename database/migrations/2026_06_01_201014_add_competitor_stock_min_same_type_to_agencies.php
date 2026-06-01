<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Competitor Stock — agency-configurable floor for the Level-2 step-up
 * fallback. When exact-property-type matches within the Level-1 family
 * (Freehold vs Sectional Scheme) are below this floor, the result set
 * widens to include same-family-other-type stock so the section isn't
 * empty. Default 5 — enough to surface meaningful exact-type
 * competition before stepping up; low enough that thin pools don't
 * leave the section blank.
 *
 * Bounded set: Level 1 is a HARD GATE and is never crossed. The
 * step-up only widens within the family (e.g. apartment subject
 * surfaces townhouses when apartments are sparse, NEVER houses).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            $table->unsignedTinyInteger('competitor_stock_min_same_type')
                ->default(5)
                ->after('competitor_stock_min_score')
                ->comment('Competitor Stock — minimum exact-property-type matches before stepping up to same-family-other-type. Level 1 (FH/SS) is never crossed.');
        });
    }

    public function down(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            $table->dropColumn('competitor_stock_min_same_type');
        });
    }
};
