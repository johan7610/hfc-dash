<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('esign_consent_log')) {
            Schema::create('esign_consent_log', function (Blueprint $table) {
                $table->id();
                $table->foreignId('flow_id')->constrained('flows');
                $table->foreignId('signing_party_id')
                    ->constrained('esign_signing_parties');
                $table->foreignId('contact_id')->constrained('contacts');
                $table->text('id_number_entered');
                $table->text('consent_text');
                $table->timestamp('consent_accepted_at');
                $table->string('ip_address', 45);
                $table->text('user_agent');
                $table->json('device_info')->nullable();
                $table->string('document_hash', 64);
                $table->timestamp('created_at')->nullable();

                $table->index(['flow_id']);
                $table->index(['contact_id']);
                $table->index(['signing_party_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('esign_consent_log');
    }
};
