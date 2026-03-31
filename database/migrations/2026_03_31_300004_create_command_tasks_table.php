<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('command_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('task_type', 50)->index()->comment('document_upload, follow_up, compliance, review, deal_action, custom');
            $table->string('status', 20)->default('todo')->index()->comment('todo, in_progress, awaiting, done, dismissed');
            $table->string('priority', 20)->default('normal')->comment('low, normal, high, critical');

            // Assignment
            $table->foreignId('assigned_to')->constrained('users');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->comment('null = system-generated');

            // Dates
            $table->dateTime('due_date')->nullable()->index();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();

            // Pillar links
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('deal_id')->nullable()->index();

            // Source
            $table->string('source_type', 50)->nullable()->comment('automation_rule, manual, calendar_event');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('calendar_event_id')->nullable()->constrained()->nullOnDelete();

            // Tracking
            $table->json('checklist')->nullable();
            $table->text('notes')->nullable();

            // Metadata
            $table->json('metadata')->nullable();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('agency_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['assigned_to', 'status']);
            $table->index(['assigned_to', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('command_tasks');
    }
};
