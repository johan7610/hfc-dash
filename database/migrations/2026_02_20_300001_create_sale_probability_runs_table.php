<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_probability_runs', function (Blueprint $table) {
            $table->id();

            // Market analytics reference — denormalised for audit stability
            $table->unsignedBigInteger('market_analytics_run_id');
            $table->foreign('market_analytics_run_id')
                  ->references('id')->on('market_analytics_runs')
                  ->cascadeOnDelete();

            $table->string('market_analytics_model_version', 32);
            $table->string('market_analytics_inputs_hash', 64);

            // This model's version + canonical input fingerprint
            $table->string('model_version', 32)->comment('e.g. prob-v1.0.0');
            $table->string('inputs_hash', 64)->comment('SHA-256 of canonical inputs JSON');

            // TEXT columns (SQLite-safe; array cast handles JSON encode/decode)
            $table->text('inputs_json')->comment('Canonical serialised input parameters');
            $table->text('outputs_json')->comment('Flat probabilities + expected_days');
            $table->text('breakdown_json')->comment('Signals, weights, composite score');
            $table->text('data_sources_json')->comment('market_analytics reference + future sources');

            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['model_version', 'inputs_hash'], 'spr_version_hash_idx');
            $table->index('market_analytics_run_id', 'spr_ma_run_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_probability_runs');
    }
};
