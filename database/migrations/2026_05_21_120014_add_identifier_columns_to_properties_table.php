<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MIC Phase A1 — add deeds-office identifier columns to `properties` (spec §3.3.3).
 *
 * The Tracked Property graph has erf_number, title_deed_number,
 * municipal_valuation, municipal_valuation_year. The Property pillar (Agency
 * Stock) doesn't. This migration closes that gap so promoted-to-stock
 * properties carry the identifiers on the operational record (not just on
 * the audit-trail TP).
 *
 * Backfill happens in migration #15. NOT enforced NOT NULL — most properties
 * legitimately have no deeds-office identifiers (they were entered before
 * the data was available).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            // Order in the table matters for human inspection; place near existing
            // identifier columns. Idempotency guard: skip if column already exists.
            if (!Schema::hasColumn('properties', 'erf_number')) {
                $table->string('erf_number', 100)->nullable()->after('stand_number');
            }
            if (!Schema::hasColumn('properties', 'title_deed_number')) {
                $table->string('title_deed_number', 100)->nullable()->after('erf_number');
            }
            if (!Schema::hasColumn('properties', 'municipal_valuation')) {
                $table->decimal('municipal_valuation', 15, 2)->nullable()->after('title_deed_number');
            }
            if (!Schema::hasColumn('properties', 'municipal_valuation_year')) {
                $table->unsignedSmallInteger('municipal_valuation_year')->nullable()->after('municipal_valuation');
            }
        });

        // Indexes — idempotent. Some indexes were created by the prior
        // migration 2026_05_14_140000_add_market_intelligence_columns_to_properties.
        $existingIndexes = collect(
            \Illuminate\Support\Facades\DB::select("SHOW INDEX FROM properties WHERE Key_name LIKE 'idx_properties_%'")
        )->pluck('Key_name')->unique()->all();

        if (!in_array('idx_properties_erf_number', $existingIndexes, true)) {
            Schema::table('properties', function (Blueprint $table) {
                $table->index('erf_number', 'idx_properties_erf_number');
            });
        }
        if (!in_array('idx_properties_title_deed_number', $existingIndexes, true)) {
            Schema::table('properties', function (Blueprint $table) {
                $table->index('title_deed_number', 'idx_properties_title_deed_number');
            });
        }
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropIndex('idx_properties_erf_number');
            $table->dropIndex('idx_properties_title_deed_number');
            $table->dropColumn([
                'erf_number',
                'title_deed_number',
                'municipal_valuation',
                'municipal_valuation_year',
            ]);
        });
    }
};
