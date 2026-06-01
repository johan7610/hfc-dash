<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Holding Cost — agency-configurable freehold defaults (the missing three
 * components on full-title properties: garden service, pool service,
 * security/estate fees). Per the agencies-default-everywhere rule.
 *
 * Tier 2 fallback values for the Tier 0/1/2 chain in HoldingCostEstimator.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            $table->unsignedSmallInteger('presentations_default_garden_zar')
                ->default(800)
                ->after('presentations_default_opportunity_cost_pct')
                ->comment('Freehold garden service — Tier 2 default monthly Rands.');
            $table->unsignedSmallInteger('presentations_default_pool_zar')
                ->default(600)
                ->after('presentations_default_garden_zar')
                ->comment('Freehold pool service — Tier 2 default monthly Rands.');
            $table->unsignedSmallInteger('presentations_default_security_zar')
                ->default(1500)
                ->after('presentations_default_pool_zar')
                ->comment('Freehold security/estate fees — Tier 2 default monthly Rands.');
        });
    }

    public function down(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            $table->dropColumn([
                'presentations_default_garden_zar',
                'presentations_default_pool_zar',
                'presentations_default_security_zar',
            ]);
        });
    }
};
