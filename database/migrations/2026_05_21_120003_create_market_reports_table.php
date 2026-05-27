<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MIC Phase A1 — `market_reports` (spec §3.2.2).
 *
 * Upload record for any CMA / market report file. The parsed normalised values
 * live in `market_data_points`; this table is the per-file audit record.
 *
 * Dedup is per-agency (compound unique on agency_id + file_hash), not global —
 * two different agencies legitimately upload identical files; we keep both
 * records but the parser reuses the cached extraction.
 */
return new class extends Migration {
    public function up(): void
    {
        // Idempotency / self-clean: a prior run of this migration (when it was
        // filename-ordered BEFORE market_report_types) created the table but
        // failed on the FK ALTER. MySQL DDL is auto-commit so the partial
        // table remained. Drop here so this run is clean.
        Schema::dropIfExists('market_reports');

        Schema::create('market_reports', function (Blueprint $table) {
            $table->comment('Per-file upload record for CMA / market reports. Normalised values live in market_data_points.');

            $table->id();

            $table->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $table->foreignId('uploaded_by_user_id')->constrained('users')->cascadeOnDelete();
            // market_report_types.id is smallIncrements (per spec §3.2.3 "smallint PK"),
            // so this FK must be unsignedSmallInteger to match — foreignId() defaults
            // to BIGINT and trips MySQL's strict FK type-compatibility check.
            $table->unsignedSmallInteger('report_type_id');
            $table->foreign('report_type_id')
                  ->references('id')->on('market_report_types');

            $table->string('file_path')->comment('Storage path under storage/app/');
            $table->string('file_name')->comment('Original filename as uploaded');
            $table->string('file_hash', 64)->comment('sha256 hex; dedup within agency');

            $table->string('source_suburb')->nullable()
                  ->comment('Auto-detected from filename / first-page OCR, or agent-supplied at upload');
            $table->string('source_town')->nullable();

            $table->date('report_date')
                  ->comment('Date the report was generated (per the document), NOT uploaded_at');

            $table->enum('parse_status', ['pending', 'parsing', 'parsed', 'failed', 'manual_review'])
                  ->default('pending');
            $table->timestamp('parse_started_at')->nullable();
            $table->timestamp('parse_completed_at')->nullable();
            $table->string('parser_version')->nullable()
                  ->comment('Track parser revisions for accuracy metrics');

            $table->json('raw_extracted_json')->nullable()
                  ->comment('Everything the parser pulled, before normalisation into market_data_points');

            $table->unsignedSmallInteger('data_points_count')->default(0)
                  ->comment('Cached count of extracted market_data_points');

            $table->enum('spot_check_status', ['pending', 'running', 'passed', 'flagged', 'manual'])
                  ->default('pending');
            $table->json('spot_check_results')->nullable()
                  ->comment('AI audit re-extraction results (see market_data_discrepancies for diffs)');

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Per-agency dedup; not a global unique (two agencies can legitimately upload the same file).
            $table->unique(['agency_id', 'file_hash'], 'uq_market_reports_agency_hash');

            $table->index(['agency_id', 'parse_status'], 'idx_market_reports_agency_parse');
            $table->index(['agency_id', 'report_date'], 'idx_market_reports_agency_date');
            $table->index(['report_type_id'], 'idx_market_reports_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_reports');
    }
};
