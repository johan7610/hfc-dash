<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3b — per-presentation override of the agency comp-scope default.
 *
 * NULL means "inherit from agency setting" — resolved at generate time by
 * PresentationGeneratorService. This way the agent can override one
 * presentation without changing the agency default.
 *
 * Spec: Phase 3b build prompt §1.2.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('presentations', function (Blueprint $table) {
            if (!Schema::hasColumn('presentations', 'comp_scope')) {
                $table->enum('comp_scope', ['radius_all', 'suburb_only'])
                    ->nullable()
                    ->after('vicinity_selected_range');
            }
            if (!Schema::hasColumn('presentations', 'comp_radius_m')) {
                $table->unsignedSmallInteger('comp_radius_m')
                    ->nullable()
                    ->after('comp_scope');
            }
        });
    }

    public function down(): void
    {
        Schema::table('presentations', function (Blueprint $table) {
            foreach (['comp_radius_m', 'comp_scope'] as $col) {
                if (Schema::hasColumn('presentations', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
