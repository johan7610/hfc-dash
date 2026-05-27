<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 8 — log of outcome-capture nudges sent to agents.
 *
 * Used by PromptOutcomeCaptureJob to enforce the "only prompt once per
 * presentation per 30 days" cooldown. Also gives BMs a paper trail when
 * an agent claims "I never got the prompt" — every dispatch lands here.
 *
 * Indexed by (presentation_id, prompted_at DESC) for the cooldown lookup.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('presentation_outcome_prompts')) {
            return;
        }

        Schema::create('presentation_outcome_prompts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('presentation_id')
                ->constrained('presentations', 'id', 'pop_pres_fk')
                ->cascadeOnDelete();
            $table->foreignId('agency_id')
                ->constrained('agencies', 'id', 'pop_agency_fk')
                ->cascadeOnDelete();
            $table->foreignId('prompted_user_id')
                ->constrained('users', 'id', 'pop_user_fk')
                ->cascadeOnDelete();

            $table->timestamp('prompted_at')->useCurrent();
            $table->string('channel', 30)->default('mail');

            $table->timestamps();

            $table->index(['presentation_id', 'prompted_at'], 'pop_pres_at_idx');
            $table->index(['agency_id', 'prompted_at'], 'pop_agency_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presentation_outcome_prompts');
    }
};
