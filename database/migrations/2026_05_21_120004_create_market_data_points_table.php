<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MIC Phase A1 — `market_data_points` (spec §3.2.4).
 *
 * The normalised data warehouse. One row per extracted metric.
 *
 * **Shared-pool design (spec §13):** agency_id is on every row for audit, but
 * default read scopes against this table do NOT filter by agency — every
 * CoreX agency sees the union. Phase A2+ model intentionally omits AgencyScope.
 *
 * Metric triplet (numeric / date / string): exactly one populated per row,
 * enforced at the model level (no DB CHECK to keep the schema portable).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('market_data_points', function (Blueprint $table) {
            $table->comment('Normalised market data warehouse. SHARED-POOL: agency_id is audit-only, default reads union across agencies (spec §13).');

            $table->id();

            // Audit-only — see "shared-pool" comment above. No AgencyScope on the model.
            $table->foreignId('agency_id')->constrained('agencies');

            // Nullable because data can also come from direct API integrations (Lightstone direct, future).
            $table->foreignId('report_id')->nullable()->constrained('market_reports')->nullOnDelete();

            // Nullable because not every data point pertains to a specific tracked property
            // (suburb medians / sales counts attach to suburb_normalised + town instead).
            $table->foreignId('tracked_property_id')->nullable()->constrained('tracked_properties')->nullOnDelete();

            $table->string('suburb_normalised', 100)->nullable()
                  ->comment('Lowercase + strip punctuation; used for suburb-level data points.');
            $table->string('town', 100)->nullable();

            $table->string('metric_key', 100)
                  ->comment('e.g. median_price_3bed_house, total_sales_yoy, municipal_valuation, last_sale_price');

            // Metric value triplet — exactly one populated per row (model-level validation).
            $table->decimal('metric_value_numeric', 15, 2)->nullable();
            $table->date('metric_value_date')->nullable();
            $table->text('metric_value_string')->nullable();

            $table->date('metric_date')
                  ->comment('The date the metric applies to (e.g. "Q1 2026" → 2026-01-01).');

            $table->enum('confidence', ['low', 'medium', 'high', 'verified'])->default('medium');

            $table->string('source_type', 50)
                  ->comment('Mirrors market_reports.source_type but allows API origins (lightstone_api, deeds_api, …).');
            $table->string('source_ref', 200)->nullable();

            $table->boolean('is_superseded')->default(false)
                  ->comment('Newer report invalidates this point.');
            $table->foreignId('superseded_by_id')->nullable()->constrained('market_data_points')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Per-TP metric history (drill-down on a single property)
            $table->index(['agency_id', 'tracked_property_id', 'metric_key', 'metric_date'], 'idx_mdp_agency_tp_metric');

            // Per-suburb metric history (suburb dashboards)
            $table->index(['agency_id', 'suburb_normalised', 'metric_key', 'metric_date'], 'idx_mdp_agency_suburb_metric');

            // Global queries across the shared pool (e.g. "what's the median R1.5–2M Margate house price across CoreX?")
            $table->index(['metric_key', 'metric_date'], 'idx_mdp_global_metric');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_data_points');
    }
};
