<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E-Sign V3 Phase 1B.9 (FIX 1) — flag removal requests.
 *
 * After a signing party has completed signing (party.completed_at !== null),
 * the flag they raised cannot be removed by anyone unilaterally. The agent
 * may *request* removal; the recipient must consent via an authenticated
 * link before the flag is taken out of the document. Both the original
 * flag and the consent decision live in the audit chain forever.
 *
 * Spec: .ai/specs/esign-v3-complete-spec.md §7.5.8 (Phase 1B.9 addition).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flag_removal_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('signature_template_id');
            $table->unsignedBigInteger('document_amendment_id');
            $table->string('clause_ref', 50);
            $table->unsignedBigInteger('requested_by_user_id');
            $table->timestamp('requested_at')->useCurrent();
            $table->text('reason');
            $table->unsignedBigInteger('recipient_signing_party_id'); // signature_requests.id
            $table->string('consent_token', 64)->unique();
            $table->timestamp('consent_sent_at')->nullable();
            $table->timestamp('consent_received_at')->nullable();
            $table->string('consent_ip_address', 45)->nullable();
            $table->text('consent_user_agent')->nullable();
            $table->text('consent_signature_data')->nullable();
            $table->enum('status', ['pending', 'consented', 'rejected', 'expired', 'cancelled'])
                ->default('pending');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['signature_template_id', 'status'], 'frr_tpl_status_idx');
            $table->index('document_amendment_id', 'frr_amendment_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flag_removal_requests');
    }
};
