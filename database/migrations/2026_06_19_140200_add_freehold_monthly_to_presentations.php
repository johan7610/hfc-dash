<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Holding Cost — per-presentation override columns for the three
 * freehold components. Mirrors the existing monthly_bond / monthly_rates
 * / monthly_levies / monthly_insurance / monthly_utilities /
 * monthly_opportunity_cost pattern. Agent edit on Section 6 writes to
 * the column AND to holding_cost_data_points; AnalysisDataService reads
 * the column for the breakdown.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('presentations', function (Blueprint $table) {
            $table->decimal('monthly_garden', 12, 2)->nullable()->after('monthly_utilities');
            $table->decimal('monthly_pool',   12, 2)->nullable()->after('monthly_garden');
            $table->decimal('monthly_security', 12, 2)->nullable()->after('monthly_pool');
        });
    }

    public function down(): void
    {
        Schema::table('presentations', function (Blueprint $table) {
            $table->dropColumn(['monthly_garden', 'monthly_pool', 'monthly_security']);
        });
    }
};
