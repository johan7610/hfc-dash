<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3f B4 — track WHERE a property's GPS came from + WHEN it was resolved.
 *
 * Lets the UI show "GPS sourced from a CMA dated 2024-08" vs "Google geocoder
 * suburb-level approximation"; lets the backfill command know which rows
 * deserve a retry from a richer source.
 *
 * geo_source values:
 *   mic_subject         — pulled from market_reports.subject_latitude
 *   imported_listing    — from imported_listings (only when that table exists)
 *   portal_capture      — from portal_captures.extracted_fields_json
 *   p24                 — from a P24 portal capture
 *   google              — Google Geocoding API
 *   nominatim           — OSM Nominatim fallback
 *   manual              — agent entered manually
 *   unresolved          — resolution attempted and failed
 */
return new class extends Migration {
    public function up(): void
    {
        foreach (['properties', 'tracked_properties'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                if (!Schema::hasColumn($table->getTable(), 'geo_source')) {
                    $table->string('geo_source', 30)->nullable()->after('longitude');
                }
                if (!Schema::hasColumn($table->getTable(), 'geo_confidence')) {
                    $table->string('geo_confidence', 20)->nullable()->after('geo_source');
                }
                if (!Schema::hasColumn($table->getTable(), 'geo_resolved_at')) {
                    $table->timestamp('geo_resolved_at')->nullable()->after('geo_confidence');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['properties', 'tracked_properties'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                foreach (['geo_source', 'geo_confidence', 'geo_resolved_at'] as $col) {
                    if (Schema::hasColumn($table->getTable(), $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
