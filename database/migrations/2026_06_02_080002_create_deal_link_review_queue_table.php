<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3i B3 — admin queue for ambiguous deal→property matches.
 *
 * When DealPropertyLinkService finds multiple plausible properties for one
 * deal address (e.g. "12 Beach Road, Margate" matches two units in a complex)
 * the row lands here. Admin resolves via the review UI:
 *   pending          — awaiting review
 *   resolved_linked  — admin picked a property
 *   resolved_unlinked — admin said "none of these are right" (no link)
 *   resolved_skip    — admin deferred (low priority, e.g. waiting on data)
 *
 * candidates_json captures the candidate set + scores at match time so
 * the review UI can re-render the original decision context even if the
 * properties table changes between match and review.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('deal_link_review_queue')) {
            return;
        }

        Schema::create('deal_link_review_queue', function (Blueprint $table) {
            $table->id();

            $table->foreignId('deal_id')
                ->constrained('deals', 'id', 'dlrq_deal_fk')
                ->cascadeOnDelete();
            $table->foreignId('agency_id')
                ->constrained('agencies', 'id', 'dlrq_agency_fk')
                ->cascadeOnDelete();

            $table->timestamp('matched_at')->useCurrent();

            $table->enum('match_status', [
                'pending', 'resolved_linked', 'resolved_unlinked', 'resolved_skip',
            ])->default('pending');

            $table->json('candidates_json');

            $table->foreignId('chosen_property_id')->nullable()
                ->constrained('properties', 'id', 'dlrq_chosen_property_fk')
                ->nullOnDelete();

            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()
                ->constrained('users', 'id', 'dlrq_reviewer_fk')
                ->nullOnDelete();
            $table->text('review_note')->nullable();

            $table->timestamps();

            $table->index(['agency_id', 'match_status'], 'dlrq_agency_status_idx');
            $table->index(['deal_id', 'match_status'], 'dlrq_deal_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deal_link_review_queue');
    }
};
