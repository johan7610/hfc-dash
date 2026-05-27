<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MIC Phase A1 — `ai_narrative_cache` (spec §3.2.6).
 *
 * Caches Ellie-generated narratives keyed by a deterministic cache_key + an
 * input_hash that invalidates the cache when the underlying data changes.
 * Tracks tokens + cost in ZAR per generation for budgeting.
 *
 * agency_id is nullable — global narratives (e.g. cross-agency market briefs)
 * are valid.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_narrative_cache', function (Blueprint $table) {
            $table->comment('Ellie narrative cache with token + cost tracking. agency_id nullable for global narratives.');

            $table->id();

            $table->foreignId('agency_id')->nullable()->constrained('agencies')->nullOnDelete();

            $table->string('narrative_type', 50)
                  ->comment('weekly_brief | tile_copy | listing_tooltip | suburb_pocket | audit_finding');

            $table->string('cache_key')
                  ->comment('Composed deterministically, e.g. weekly_brief:agency:1:week:2026-21');

            $table->string('input_hash', 64)
                  ->comment('sha256 of the input data — mismatch forces regeneration.');

            $table->string('prompt_version', 20)
                  ->comment('Track prompt evolution for A/B comparison.');

            $table->string('model', 50)
                  ->comment('e.g. claude-haiku-4-5, claude-sonnet-4-6');

            $table->integer('input_tokens')->default(0);
            $table->integer('output_tokens')->default(0);
            $table->decimal('cost_zar', 10, 4)->default(0);

            $table->text('output_text');
            $table->json('output_json')->nullable()
                  ->comment('When structured output required.');

            $table->timestamp('generated_at');
            $table->timestamp('expires_at');

            $table->timestamps();

            // Composite + unique per spec §3.2.6.
            $table->unique('cache_key', 'uq_anc_cache_key');
            $table->index(['narrative_type', 'expires_at'], 'idx_anc_type_expires');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_narrative_cache');
    }
};
