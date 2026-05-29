<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Build 3 — condition-driven valuation.
 *
 * Three pieces:
 *   (1) property_setting_items.adjustment_pct — only relevant for the new
 *       condition_level group. Null for every existing row.
 *   (2) properties.condition_level_id — the property's recorded condition
 *       (seeds the dropdown on the review screen, used for auto-selection
 *       later). Nullable; no cascade — agency-deleted condition_level
 *       must not nuke the property.
 *   (3) presentation_versions.condition_level_id + condition_adjustment_pct
 *       + condition_label — per-version snapshot. Stored at publish so
 *       historic presentations reflect the condition AT THAT TIME, even
 *       if the property's condition or the agency's adjustment_pct
 *       changes later. This is the source of truth for the PDF.
 *
 * Seed: 7 condition levels per existing agency (industry CMA convention).
 *   To Remodel    -30%
 *   To Renovate   -15%
 *   Average         0%  (baseline — cannot be deleted)
 *   Good           +3%
 *   Very Good     +12%
 *   Excellent     +20%
 *   Exceptional   +38%
 *
 * Spec: Build 3 prompt §B (1).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('property_setting_items', function (Blueprint $table) {
            if (!Schema::hasColumn('property_setting_items', 'adjustment_pct')) {
                $table->decimal('adjustment_pct', 5, 2)->nullable()->after('title_type')
                      ->comment('Build 3 — % adjustment applied to CMA Middle band when this condition_level is selected. Null for non-condition rows.');
            }
        });

        Schema::table('properties', function (Blueprint $table) {
            if (!Schema::hasColumn('properties', 'condition_level_id')) {
                $table->unsignedBigInteger('condition_level_id')->nullable()->after('property_type')
                      ->comment('Build 3 — FK to property_setting_items where group=condition_level. Nullable: property may have no recorded condition.');
            }
        });

        Schema::table('properties', function (Blueprint $table) {
            try {
                $table->foreign('condition_level_id', 'properties_condition_level_fk')
                      ->references('id')->on('property_setting_items')
                      ->nullOnDelete();
            } catch (\Throwable $e) { /* FK exists */ }
            try { $table->index('condition_level_id', 'properties_condition_level_idx'); }
            catch (\Throwable $e) { /* index exists */ }
        });

        Schema::table('presentation_versions', function (Blueprint $table) {
            if (!Schema::hasColumn('presentation_versions', 'condition_level_id')) {
                $table->unsignedBigInteger('condition_level_id')->nullable()->after('included_comp_ids_json');
            }
            if (!Schema::hasColumn('presentation_versions', 'condition_adjustment_pct')) {
                $table->decimal('condition_adjustment_pct', 5, 2)->nullable()->after('condition_level_id')
                      ->comment('Snapshot at review/publish — defends historic PDF against later setting drift.');
            }
            if (!Schema::hasColumn('presentation_versions', 'condition_label')) {
                $table->string('condition_label', 64)->nullable()->after('condition_adjustment_pct');
            }
        });

        Schema::table('presentation_versions', function (Blueprint $table) {
            try {
                $table->foreign('condition_level_id', 'pv_condition_level_fk')
                      ->references('id')->on('property_setting_items')
                      ->nullOnDelete();
            } catch (\Throwable $e) { /* FK exists */ }
        });

        // Seed: 7 condition levels per agency, marked is_default so the
        // settings UI lists them under "Defaults". 'Average' is the
        // baseline (0%) — the UI flags it; controller refuses delete.
        $defaults = [
            ['name' => 'To Remodel',  'pct' => -30.00, 'sort' => 0],
            ['name' => 'To Renovate', 'pct' => -15.00, 'sort' => 1],
            ['name' => 'Average',     'pct' =>   0.00, 'sort' => 2],
            ['name' => 'Good',        'pct' =>   3.00, 'sort' => 3],
            ['name' => 'Very Good',   'pct' =>  12.00, 'sort' => 4],
            ['name' => 'Excellent',   'pct' =>  20.00, 'sort' => 5],
            ['name' => 'Exceptional', 'pct' =>  38.00, 'sort' => 6],
        ];

        $agencyIds = DB::table('agencies')->pluck('id');
        foreach ($agencyIds as $agencyId) {
            foreach ($defaults as $row) {
                $exists = DB::table('property_setting_items')
                    ->where('agency_id', $agencyId)
                    ->where('group', 'condition_level')
                    ->where('name', $row['name'])
                    ->exists();
                if ($exists) continue;
                DB::table('property_setting_items')->insert([
                    'agency_id'      => $agencyId,
                    'group'          => 'condition_level',
                    'name'           => $row['name'],
                    'sort_order'     => $row['sort'],
                    'is_default'     => 1,
                    'active'         => 1,
                    // title_type is meaningless for condition_level rows
                    // but the column is NOT NULL with default 'other' so
                    // we let the column default fire.
                    'adjustment_pct' => $row['pct'],
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Strip seeded rows first so the FK on presentation_versions
        // doesn't trip.
        DB::table('property_setting_items')->where('group', 'condition_level')->delete();

        Schema::table('presentation_versions', function (Blueprint $table) {
            try { $table->dropForeign('pv_condition_level_fk'); } catch (\Throwable $e) {}
            $table->dropColumn(['condition_level_id', 'condition_adjustment_pct', 'condition_label']);
        });

        Schema::table('properties', function (Blueprint $table) {
            try { $table->dropForeign('properties_condition_level_fk'); } catch (\Throwable $e) {}
            try { $table->dropIndex('properties_condition_level_idx'); } catch (\Throwable $e) {}
            $table->dropColumn('condition_level_id');
        });

        Schema::table('property_setting_items', function (Blueprint $table) {
            $table->dropColumn('adjustment_pct');
        });
    }
};
