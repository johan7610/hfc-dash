<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 8 — close-the-loop outcome capture per presentation.
 *
 * One row per presentation (UNIQUE(presentation_id)). When a presentation
 * has no row, it's "not yet recorded" and shows the prompt panel; the
 * scheduled job nudges the agent after 30 days. After 90 days the outcome
 * is auto-locked to preserve analytics integrity.
 *
 * cancellation_reason is only populated for the lost_* outcomes (and 'other').
 *
 * resulted_in_deal_id links to deals(id) — set by the agent when they
 * captured the deal AND outcome together, or auto-set by the deal-registered
 * listener if a deal lands without a manual outcome having been recorded.
 *
 * Explicit short FK names (po_*) — Laravel's auto-generated names overflow
 * MySQL's 64-char identifier cap on this combination of long table + long
 * column names.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('presentation_outcomes')) {
            return;
        }

        Schema::create('presentation_outcomes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('presentation_id')->unique()
                ->constrained('presentations', 'id', 'po_pres_fk')
                ->cascadeOnDelete();
            $table->foreignId('agency_id')
                ->constrained('agencies', 'id', 'po_agency_fk')
                ->cascadeOnDelete();

            $table->enum('outcome', [
                'won_mandate',
                'won_sale',
                'lost_to_competitor',
                'lost_to_no_decision',
                'lost_to_price_dispute',
                'lost_to_no_response',
                'still_pending',
                'other',
            ])->index();

            $table->enum('cancellation_reason', [
                'price_too_high_seller',
                'price_too_low_seller',
                'commission_concerns',
                'sole_mandate_concerns',
                'family_pressure',
                'existing_relationship',
                'agency_reputation',
                'agent_personality',
                'timing_change',
                'property_issues_discovered',
                'price_match_with_other',
                'other',
            ])->nullable();

            $table->string('cancellation_competitor_agency', 200)->nullable();
            $table->unsignedBigInteger('cancellation_competitor_price')->nullable();

            $table->date('decision_at')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('resulted_in_deal_id')->nullable()
                ->constrained('deals', 'id', 'po_deal_fk')
                ->nullOnDelete();

            $table->foreignId('recorded_by_user_id')
                ->constrained('users', 'id', 'po_recorder_fk');
            $table->timestamp('recorded_at')->useCurrent();

            $table->boolean('locked')->default(false);
            $table->timestamp('locked_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['agency_id', 'outcome', 'decision_at'], 'po_agency_out_decision_idx');
            $table->index('resulted_in_deal_id', 'po_deal_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presentation_outcomes');
    }
};
