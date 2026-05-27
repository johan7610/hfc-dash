<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3a — `market_report_comp_rows`: one row per comparable / subject /
 * active-listing / scheme-owner entry extracted from a market report.
 *
 * Complements `market_data_points` (the key/value warehouse). Where MDP holds
 * suburb-level aggregates and individual metrics, this table holds the full
 * row context (price + extent + date + distance + raw JSON) needed for the
 * presentation pack to render side-by-side comparable tables.
 *
 * Spec: Phase 3a build prompt §1.2.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('market_report_comp_rows', function (Blueprint $table) {
            $table->id();

            $table->foreignId('market_report_id')->constrained('market_reports')->cascadeOnDelete();
            $table->foreignId('agency_id')->constrained('agencies');

            $table->unsignedSmallInteger('row_index')->default(0)
                  ->comment('0-based order within the report; subject row is typically 0.');

            $table->enum('row_type', ['subject', 'comp', 'listing', 'owner'])
                  ->comment('subject = the property being valued; comp = sold comparable; listing = active for-sale; owner = scheme owner entry.');

            // Identity
            $table->string('scheme_name')->nullable();
            $table->string('section_number', 32)->nullable();
            $table->string('flat_number', 32)->nullable();
            $table->string('ss_number', 32)->nullable()
                  ->comment('Scheme Sectional Title (SS) registration number.');
            $table->unsignedSmallInteger('ss_year')->nullable();
            $table->string('address')->nullable();
            $table->string('suburb_normalised', 100)->nullable();
            $table->string('property_type', 64)->nullable();

            // Physical
            $table->unsignedInteger('extent_m2')->nullable();

            // Transaction
            $table->date('sale_date')->nullable();
            $table->unsignedBigInteger('sale_price')->nullable()
                  ->comment('Rands (whole), matches presentation_sold_comps.sold_price_inc convention.');
            $table->unsignedBigInteger('estimated_value')->nullable();
            $table->unsignedInteger('r_per_m2')->nullable();

            // Active-listing fields
            $table->unsignedBigInteger('list_price')->nullable();
            $table->unsignedSmallInteger('days_on_market')->nullable();

            // Municipal
            $table->unsignedBigInteger('municipal_valuation')->nullable();
            $table->unsignedSmallInteger('municipal_valuation_year')->nullable();

            // Condition + geo
            $table->string('condition', 64)->nullable();
            $table->unsignedSmallInteger('distance_to_subject_m')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->json('raw_row_json')->nullable()
                  ->comment('Full extracted row payload for audit + future re-parse.');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['market_report_id', 'row_type'], 'idx_mrcr_report_type');
            $table->index(['suburb_normalised', 'sale_date'], 'idx_mrcr_suburb_date');
            $table->index(['latitude', 'longitude'], 'idx_mrcr_geo');
            $table->index('scheme_name', 'idx_mrcr_scheme');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_report_comp_rows');
    }
};
