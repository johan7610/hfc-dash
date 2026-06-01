<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Holding Cost — capture-everything dataset.
 *
 * One row per agent-entered, parser-captured, or property-record-sourced
 * monthly cost value, tagged with the keys that component AVERAGES by:
 *   - levy        → scheme_name
 *   - rates       → municipality / suburb + value_band
 *   - insurance   → value_band
 *   - utilities   → property_type + suburb
 *   - garden/pool → property_type + suburb (freehold only)
 *   - security    → suburb (freehold only)
 *
 * Tier 1 (learned average) reads from this table with is_excluded=0.
 * Tier 0 reads properties.levy / rates_taxes / special_levy directly.
 * Tier 2 reads agency defaults.
 *
 * is_excluded + excluded_* columns are the schema-ready foundation for
 * the agency exclude-grid fast-follow — no UI this build.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('holding_cost_data_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();

            // Provenance — at most one of these is set per row.
            $table->foreignId('presentation_version_id')->nullable()
                ->constrained('presentation_versions')->nullOnDelete();
            $table->unsignedBigInteger('property_id')->nullable()->index();
            $table->unsignedBigInteger('tracked_property_id')->nullable()->index();

            $table->enum('component', [
                'rates', 'levy', 'insurance', 'utilities',
                'garden', 'pool', 'security',
                'bond', 'opportunity_cost',
            ])->index();

            // Captured monthly value in whole Rands.
            $table->unsignedBigInteger('monthly_value_zar');

            // Averaging keys (each nullable — only set for the relevant components).
            $table->string('scheme_name', 255)->nullable()->index();
            $table->string('suburb_normalised', 100)->nullable()->index();
            $table->string('municipality', 100)->nullable()->index();
            $table->string('property_type', 64)->nullable();
            $table->enum('title_type', ['full_title', 'sectional_title', 'vacant_land', 'other'])
                ->nullable();
            // Coarse property-value band (e.g. "0_1M", "1_3M", "3_5M", "5M_PLUS").
            // Lets insurance + rates average against value-similar properties.
            $table->string('property_value_band', 16)->nullable()->index();

            $table->enum('source', [
                'agent_override',
                'cma_import',
                'manual_capture',
                'property_record',
            ])->index();
            $table->string('source_ref', 200)->nullable()
                ->comment('e.g. property_id, market_report_id, presentation_id');

            $table->foreignId('entered_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            // Exclude-grid foundation (no UI this build).
            $table->boolean('is_excluded')->default(false)->index();
            $table->foreignId('excluded_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('excluded_at')->nullable();
            $table->string('exclusion_reason', 200)->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Composite indexes for the averaging lookups.
            $table->index(['agency_id', 'component', 'scheme_name'], 'idx_hcdp_levy_lookup');
            $table->index(['agency_id', 'component', 'suburb_normalised', 'property_type'], 'idx_hcdp_suburb_type_lookup');
            $table->index(['agency_id', 'component', 'municipality'], 'idx_hcdp_muni_lookup');
            $table->index(['agency_id', 'presentation_version_id', 'component', 'source'], 'idx_hcdp_override_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holding_cost_data_points');
    }
};
