<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 7 Part B3 — per-request audit log for refresh asks.
 *
 * A snapshot link can receive multiple refresh requests over its lifetime
 * (seller asks → agent declines → seller asks again later). Each ask gets
 * its own row here so the audit chain stays complete even after the link
 * itself is superseded.
 *
 * Resolution states:
 *   - pending        : seller submitted, agent has not acted
 *   - acknowledged   : agent saw it, hasn't issued a refresh yet
 *   - resolved       : agent issued a new link (resulting_link_id set)
 *   - declined       : agent explicitly declined (with note)
 *   - cancelled      : seller withdrew (reserved — no UI for this in v1)
 *
 * fingerprint_hash + ip_masked match the Phase 4 view tracking pattern so the
 * same anti-spam / flagging logic applies. Indexes match the read patterns
 * documented in the spec: by link (history view), by presentation (agent
 * inbox), by status (filter unread).
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('presentation_refresh_requests')) {
            return;
        }

        Schema::create('presentation_refresh_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $table->foreignId('presentation_id')->constrained('presentations')->cascadeOnDelete();
            $table->foreignId('snapshot_link_id')->constrained('presentation_snapshot_links')->cascadeOnDelete();
            $table->foreignId('recipient_contact_id')->nullable()->constrained('contacts')->nullOnDelete();

            // Who asked (free-text — sellers are not necessarily users)
            $table->string('requester_name', 120);
            $table->string('requester_email', 160)->nullable();
            $table->string('requester_phone', 40)->nullable();
            $table->text('message')->nullable();

            // Provenance (mirrors snapshot_views shape)
            $table->string('fingerprint_hash', 64)->nullable();
            $table->string('ip_masked', 64)->nullable();
            $table->string('user_agent', 500)->nullable();

            // Workflow
            $table->enum('status', ['pending', 'acknowledged', 'resolved', 'declined', 'cancelled'])
                ->default('pending');

            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->foreignId('resulting_link_id')->nullable()
                ->constrained('presentation_snapshot_links')->nullOnDelete();
            $table->text('resolution_note')->nullable();

            $table->timestamp('declined_at')->nullable();
            $table->foreignId('declined_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->text('decline_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Read patterns
            $table->index(['snapshot_link_id', 'created_at'], 'prr_link_created_idx');
            $table->index(['presentation_id', 'status'], 'prr_pres_status_idx');
            $table->index(['agency_id', 'status', 'created_at'], 'prr_agency_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presentation_refresh_requests');
    }
};
