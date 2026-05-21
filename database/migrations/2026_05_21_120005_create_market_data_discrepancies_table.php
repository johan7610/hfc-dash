<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MIC Phase A1 — `market_data_discrepancies` (spec §3.2.5).
 *
 * AI spot-check output. When the post-parse audit re-extracts the same fields
 * via the AI path and disagrees with the deterministic parser, a discrepancy
 * row lands here. Severity ≥ medium fires a super-admin notification.
 *
 * No softDeletes — discrepancies are resolved (resolved=true) rather than
 * archived.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('market_data_discrepancies', function (Blueprint $table) {
            $table->comment('AI spot-check diffs vs deterministic parser output. ≥medium severity notifies super-admin.');

            $table->id();

            $table->foreignId('report_id')->constrained('market_reports')->cascadeOnDelete();
            $table->foreignId('data_point_id')->constrained('market_data_points')->cascadeOnDelete();

            $table->text('parsed_value')
                  ->comment('What the deterministic parser said.');
            $table->text('audit_value')
                  ->comment('What the AI re-extraction said.');

            $table->enum('discrepancy_type', [
                'value_mismatch',
                'date_mismatch',
                'address_mismatch',
                'missing',
                'extra',
            ]);

            $table->enum('severity', ['low', 'medium', 'high'])->default('low');

            $table->boolean('resolved')->default(false);
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();

            $table->timestamps();

            $table->index(['report_id', 'resolved'], 'idx_mdd_report_resolved');
            $table->index(['severity', 'resolved'], 'idx_mdd_severity_resolved');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_data_discrepancies');
    }
};
