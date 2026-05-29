<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Build 1 — title_type discipline on property categories.
 *
 * Adds `title_type` to property_setting_items so when the comp-selection
 * service picks comparables for a presentation, it can enforce that
 * houses don't compete against apartments. The new column is on rows
 * with group='category'; rows for property_type / mandate_type /
 * property_status carry the default value but it's ignored.
 *
 * Backfill — see .ai/specs/presentation-data-lineage.md §3-A:
 *   Residential → full_title  (most agencies; agencies that mix
 *                              full + sectional under Residential
 *                              will need to split into two categories
 *                              and re-tag each — Johan's per-agency
 *                              decision, NOT auto-migrated here)
 *   Commercial  → other
 *   Industrial  → other
 *   Retirement  → full_title  (agency-overridable)
 *   Holiday     → full_title  (agency-overridable)
 *   Project     → other
 *
 * Custom (non-default) categories already created by agencies get
 * 'other' — they require manual review.
 *
 * The column is named title_type to mirror SA conveyancing language
 * (full title vs sectional title; vacant land is its own discipline;
 * other catches commercial / industrial / leasehold etc.).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('property_setting_items', function (Blueprint $table) {
            $table->enum('title_type', ['full_title', 'sectional_title', 'vacant_land', 'other'])
                  ->default('other')
                  ->after('name')
                  ->comment('Comp-selection discipline: houses do not compare to apartments. See .ai/specs/presentation-data-lineage.md §3-A.');
        });

        // Backfill default categories per the mapping above.
        $defaults = [
            'Residential' => 'full_title',
            'Commercial'  => 'other',
            'Industrial'  => 'other',
            'Retirement'  => 'full_title',
            'Holiday'     => 'full_title',
            'Project'     => 'other',
        ];

        foreach ($defaults as $name => $titleType) {
            DB::table('property_setting_items')
                ->where('group', 'category')
                ->where('name', $name)
                ->where('is_default', true)
                ->update(['title_type' => $titleType]);
        }

        // Custom categories (is_default=false) stay on the column default
        // 'other' — surfaces in the settings UI as "needs review".
    }

    public function down(): void
    {
        Schema::table('property_setting_items', function (Blueprint $table) {
            $table->dropColumn('title_type');
        });
    }
};
