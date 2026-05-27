<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MIC Phase A1 — `market_report_types` (spec §3.2.3).
 *
 * Seeded enum of supported report types (CMA Info Market Analysis, Lightstone
 * AVM, Deeds Office prints, etc). Each row points at its parser class FQCN.
 *
 * Seed data lands in Phase A2 (this migration only creates the structure).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('market_report_types', function (Blueprint $table) {
            $table->comment('Lookup of supported report types. Seeded in Phase A2.');

            $table->smallIncrements('id');

            $table->string('key')->unique()
                  ->comment('Stable identifier, e.g. cma_info_market_analysis');
            $table->string('display_name')
                  ->comment('Human-readable, e.g. "CMA Info Market Analysis"');

            $table->string('parser_class')
                  ->comment('FQCN of the parser, e.g. App\\Services\\MarketReports\\Parsers\\CmaInfoMarketAnalysisParser');

            $table->json('expected_fields_json')
                  ->comment('What the parser yields — used for validation + spot-check.');

            $table->boolean('auto_approve')->default(false)
                  ->comment('If true, skip manual review when spot-check passes.');

            $table->string('sample_file_path')->nullable()
                  ->comment('Path to a representative sample for parser regression tests.');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_report_types');
    }
};
