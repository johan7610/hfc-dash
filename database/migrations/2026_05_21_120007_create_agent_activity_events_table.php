<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MIC Phase A1 — `agent_activity_events` (spec §3.2.7).
 *
 * Append-only event log. Foundation for Phase 5 auto-activity-tracking; lands
 * now so domain-event listeners can start writing, but nothing reads it for
 * points calculation yet.
 *
 * No softDeletes, no updated_at — events are immutable history.
 *
 * Morphable subject (subject_type + subject_id): TrackedProperty, Property,
 * Contact, Deal, ProspectingClaim, etc.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('agent_activity_events', function (Blueprint $table) {
            $table->comment('Append-only agent activity log. Morphable subject. No updated_at.');

            $table->id();

            $table->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('event_type', 100)
                  ->comment('e.g. claim.created, pitch.sent, whatsapp.sent, feedback.recorded, property.created, mandate.signed');

            // Morphable subject — kept as string + bigint pair (Laravel-idiomatic).
            $table->string('subject_type', 100)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            $table->json('payload')->nullable()
                  ->comment('Event-specific data. Schema varies by event_type — interpret per the listener.');

            $table->timestamp('occurred_at');

            // Append-only: only created_at, no updated_at.
            $table->timestamp('created_at')->useCurrent();

            // Per-agent activity timelines (the most common read pattern).
            $table->index(['agency_id', 'user_id', 'occurred_at'], 'idx_aae_agency_user_time');

            // Global event rollups for cross-agency analytics + Phase 5 points engine.
            $table->index(['event_type', 'occurred_at'], 'idx_aae_event_time');

            // Morphable lookups (subject-side joins).
            $table->index(['subject_type', 'subject_id'], 'idx_aae_subject');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_activity_events');
    }
};
