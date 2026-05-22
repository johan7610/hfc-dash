<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 Part B2 — AI summary fields on the locked version snapshot.
 *
 * ai_summary_text   — the final text rendered in the PDF (may differ from
 *                     ai_summary_raw_text if the agent edited).
 * ai_summary_raw_text — preserves the original AI output for audit.
 * input_facts_json  — the EXACT facts dict passed to AI (reproducibility).
 * prompt_hash       — SHA-256 of the full rendered prompt (cache + audit).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('presentation_versions', function (Blueprint $table) {
            if (!Schema::hasColumn('presentation_versions', 'ai_variant_id')) {
                $table->unsignedSmallInteger('ai_variant_id')->nullable()->after('hydration_summary_json');
                $table->foreign('ai_variant_id')->references('id')->on('presentation_ai_variants')->nullOnDelete();
            }
            if (!Schema::hasColumn('presentation_versions', 'ai_summary_text')) {
                $table->text('ai_summary_text')->nullable()->after('ai_variant_id');
            }
            if (!Schema::hasColumn('presentation_versions', 'ai_summary_raw_text')) {
                $table->text('ai_summary_raw_text')->nullable()->after('ai_summary_text');
            }
            if (!Schema::hasColumn('presentation_versions', 'ai_summary_edited_by_agent')) {
                $table->boolean('ai_summary_edited_by_agent')->default(false)->after('ai_summary_raw_text');
            }
            if (!Schema::hasColumn('presentation_versions', 'ai_summary_generated_at')) {
                $table->timestamp('ai_summary_generated_at')->nullable()->after('ai_summary_edited_by_agent');
            }
            if (!Schema::hasColumn('presentation_versions', 'ai_summary_edited_at')) {
                $table->timestamp('ai_summary_edited_at')->nullable()->after('ai_summary_generated_at');
            }
            if (!Schema::hasColumn('presentation_versions', 'ai_summary_model')) {
                $table->string('ai_summary_model', 100)->nullable()->after('ai_summary_edited_at');
            }
            if (!Schema::hasColumn('presentation_versions', 'ai_summary_prompt_hash')) {
                $table->string('ai_summary_prompt_hash', 64)->nullable()->after('ai_summary_model');
            }
            if (!Schema::hasColumn('presentation_versions', 'ai_summary_input_facts_json')) {
                $table->json('ai_summary_input_facts_json')->nullable()->after('ai_summary_prompt_hash');
            }
        });
    }

    public function down(): void
    {
        Schema::table('presentation_versions', function (Blueprint $table) {
            foreach ([
                'ai_summary_input_facts_json', 'ai_summary_prompt_hash', 'ai_summary_model',
                'ai_summary_edited_at', 'ai_summary_generated_at', 'ai_summary_edited_by_agent',
                'ai_summary_raw_text', 'ai_summary_text',
            ] as $col) {
                if (Schema::hasColumn('presentation_versions', $col)) {
                    $table->dropColumn($col);
                }
            }
            if (Schema::hasColumn('presentation_versions', 'ai_variant_id')) {
                $table->dropForeign(['ai_variant_id']);
                $table->dropColumn('ai_variant_id');
            }
        });
    }
};
