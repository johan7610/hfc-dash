<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('esign_signing_parties')) {
            Schema::create('esign_signing_parties', function (Blueprint $table) {
                $table->id();
                $table->foreignId('flow_id')->constrained('flows')->cascadeOnDelete();
                $table->foreignId('contact_id')->constrained('contacts');
                $table->string('role', 30);
                $table->string('display_name');
                $table->text('id_number')->nullable();
                $table->string('email')->nullable();
                $table->string('phone', 20)->nullable();
                $table->unsignedSmallInteger('signing_order')->default(1);
                $table->string('status', 20)->default('pending');
                $table->timestamp('consented_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('declined_at')->nullable();
                $table->text('decline_reason')->nullable();
                $table->foreignId('proxy_for_party_id')->nullable()
                    ->constrained('esign_signing_parties')->nullOnDelete();
                $table->string('proxy_poa_reference')->nullable();
                $table->timestamps();

                $table->index(['flow_id', 'status']);
                $table->index(['contact_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('esign_signing_parties');
    }
};
