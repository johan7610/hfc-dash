<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presentation_snapshots', function (Blueprint $table) {
            $table->text('inputs_json')->nullable()->after('generated_at');
            $table->unsignedBigInteger('market_analytics_run_id')->nullable()->after('inputs_json');
            $table->unsignedBigInteger('sale_probability_run_id')->nullable()->after('market_analytics_run_id');
            $table->text('output_summary_json')->nullable()->after('sale_probability_run_id');
            $table->unsignedBigInteger('created_by_user_id')->nullable()->after('output_summary_json');

            $table->foreign('market_analytics_run_id')
                  ->references('id')->on('market_analytics_runs')
                  ->nullOnDelete();

            $table->foreign('sale_probability_run_id')
                  ->references('id')->on('sale_probability_runs')
                  ->nullOnDelete();

            $table->foreign('created_by_user_id')
                  ->references('id')->on('users')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('presentation_snapshots', function (Blueprint $table) {
            $table->dropColumn([
                'inputs_json',
                'market_analytics_run_id',
                'sale_probability_run_id',
                'output_summary_json',
                'created_by_user_id',
            ]);
        });
    }
};
