<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false)->comment('System rules cannot be deleted');

            // Trigger
            $table->string('trigger_model', 120)->comment('Property, Contact, DealV2, User, etc.');
            $table->string('trigger_event', 80)->comment('created, updated, status_changed, date_approaching, idle');
            $table->json('trigger_conditions')->nullable();

            // Action
            $table->string('action_type', 50)->comment('create_event, create_task, send_notification, create_event_and_task');
            $table->json('action_config');

            // Scope
            $table->foreignId('agency_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();

            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_rules');
    }
};
