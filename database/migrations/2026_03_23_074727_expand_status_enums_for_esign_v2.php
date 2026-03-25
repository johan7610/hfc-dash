<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Expand signature_requests.status enum — add 'deferred'
        // Current values: waiting, pending, viewed, partially_signed, completed, expired, declined
        DB::statement("ALTER TABLE signature_requests MODIFY COLUMN status ENUM(
            'waiting',
            'pending',
            'viewed',
            'partially_signed',
            'completed',
            'expired',
            'declined',
            'deferred'
        ) NOT NULL DEFAULT 'waiting'");

        // Expand signature_templates.status enum — add partial, awaiting_deferred, amendment_review
        // Current values: draft, ready, signing, awaiting_tenant, awaiting_landlord,
        //   awaiting_buyer, awaiting_seller, awaiting_supervisor, awaiting_supervisor_final,
        //   pending_agent_approval, returned_to_candidate, completed, expired, declined, rejected
        DB::statement("ALTER TABLE signature_templates MODIFY COLUMN status ENUM(
            'draft',
            'ready',
            'signing',
            'awaiting_tenant',
            'awaiting_landlord',
            'awaiting_buyer',
            'awaiting_seller',
            'awaiting_supervisor',
            'awaiting_supervisor_final',
            'pending_agent_approval',
            'returned_to_candidate',
            'completed',
            'expired',
            'declined',
            'rejected',
            'partial',
            'awaiting_deferred',
            'amendment_review'
        ) NOT NULL DEFAULT 'draft'");
    }

    public function down(): void
    {
        // Revert signature_requests.status
        DB::statement("ALTER TABLE signature_requests MODIFY COLUMN status ENUM(
            'waiting',
            'pending',
            'viewed',
            'partially_signed',
            'completed',
            'expired',
            'declined'
        ) NOT NULL DEFAULT 'waiting'");

        // Revert signature_templates.status
        DB::statement("ALTER TABLE signature_templates MODIFY COLUMN status ENUM(
            'draft',
            'ready',
            'signing',
            'awaiting_tenant',
            'awaiting_landlord',
            'awaiting_buyer',
            'awaiting_seller',
            'awaiting_supervisor',
            'awaiting_supervisor_final',
            'pending_agent_approval',
            'returned_to_candidate',
            'completed',
            'expired',
            'declined',
            'rejected'
        ) NOT NULL DEFAULT 'draft'");
    }
};
