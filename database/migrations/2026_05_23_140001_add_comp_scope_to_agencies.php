<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3b — agency-level comp-scope toggle + default radius.
 *
 *   radius_all  → match comps by Haversine distance ≤ radius_m (default 1000m)
 *   suburb_only → match comps by suburb name only (legacy behaviour)
 *
 * Radius is the honest valuation default — distance is the strongest single
 * predictor of comparability. Suburb-only is the agent override for cases
 * where scheme character or municipal boundary effects matter more than
 * raw distance.
 *
 * Spec: Phase 3b build prompt §1.1.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            if (!Schema::hasColumn('agencies', 'presentations_default_comp_scope')) {
                $table->enum('presentations_default_comp_scope', ['radius_all', 'suburb_only'])
                    ->default('radius_all')
                    ->after('presentations_default_period_months');
            }
            if (!Schema::hasColumn('agencies', 'presentations_default_radius_m')) {
                $table->unsignedSmallInteger('presentations_default_radius_m')
                    ->default(1000)
                    ->after('presentations_default_comp_scope');
            }
        });
    }

    public function down(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            foreach (['presentations_default_radius_m', 'presentations_default_comp_scope'] as $col) {
                if (Schema::hasColumn('agencies', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
