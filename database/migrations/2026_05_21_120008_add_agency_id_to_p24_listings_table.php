<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MIC Phase A1 — add `agency_id` to `p24_listings` (spec §3.3.1).
 *
 * The table predates multi-tenancy; cross-tenant leakage risk on agency #2
 * onboarding. This migration adds the column as nullable; backfill in
 * migration #9 (2026_05_21_120009), NOT NULL in #10 (2026_05_21_120010).
 *
 * No cascade on the FK — losing an agency record must NOT wipe its historical
 * market data. nullOnDelete is the correct semantic for audit-grade rows.
 *
 * AgencyScope is NOT added to the model in this phase — Phase A3+ when the
 * shared-pool semantics for p24-derived market data are finalised.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('p24_listings', function (Blueprint $table) {
            $table->unsignedBigInteger('agency_id')->nullable()->after('id');
            $table->foreign('agency_id')
                  ->references('id')->on('agencies')
                  ->nullOnDelete();
            $table->index('agency_id', 'idx_p24_listings_agency_id');
        });
    }

    public function down(): void
    {
        Schema::table('p24_listings', function (Blueprint $table) {
            $table->dropForeign(['agency_id']);
            $table->dropIndex('idx_p24_listings_agency_id');
            $table->dropColumn('agency_id');
        });
    }
};
