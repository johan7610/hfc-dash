<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 Part B3 — generation audit log.
 *
 * One row per AI call (success or failure). was_saved=true on the row the
 * agent actually accepted into the version snapshot. Lets us A/B variants
 * post-hoc + debug "why did this say X" questions.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('presentation_ai_summary_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presentation_id')->constrained('presentations')->cascadeOnDelete();
            $table->foreignId('presentation_version_id')->nullable()->constrained('presentation_versions')->cascadeOnDelete();
            $table->unsignedSmallInteger('ai_variant_id');
            $table->foreign('ai_variant_id')->references('id')->on('presentation_ai_variants');
            $table->text('generated_text')->nullable();
            $table->timestamp('generated_at')->useCurrent();
            $table->foreignId('generated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('was_saved')->default(false);
            $table->unsignedInteger('tokens_used')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->text('failure_reason')->nullable();
            $table->string('prompt_hash', 64)->nullable();
            $table->string('model', 100)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['presentation_id', 'generated_at'], 'pash_pres_gen_idx');
            $table->index('prompt_hash', 'pash_phash_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presentation_ai_summary_history');
    }
};
