<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->comment('Owner/assigned agent');
            $table->foreignId('created_by_id')->nullable()->constrained('users')->comment('System or user');
            $table->string('event_type', 50)->index()->comment('deal, lease, compliance, document, prospecting, portal, property, manual');
            $table->string('category', 80)->nullable()->index()->comment('Sub-type: bond_deadline, lease_expiry, ffc_expiry, viewing, etc.');
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->dateTime('event_date')->index();
            $table->dateTime('end_date')->nullable();
            $table->boolean('all_day')->default(true);
            $table->string('priority', 20)->default('normal')->comment('low, normal, high, critical');
            $table->string('status', 20)->default('pending')->index()->comment('pending, completed, overdue, dismissed');
            $table->string('colour', 7)->nullable()->comment('Hex colour, auto-set from event_type if null');

            // Polymorphic link to source record
            $table->nullableMorphs('source');

            // Pillar links
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('agency_id')->nullable()->constrained()->nullOnDelete();

            // Reminder config
            $table->json('reminder_offsets')->nullable()->comment('Array of offsets in minutes');
            $table->json('reminders_sent')->nullable()->comment('Tracks which offsets have been sent');

            // Recurrence
            $table->boolean('is_recurring')->default(false);
            $table->string('recurrence_rule', 255)->nullable()->comment('RRULE format');
            $table->foreignId('parent_event_id')->nullable()->constrained('calendar_events')->nullOnDelete();

            // Metadata
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'event_date']);
            $table->index(['status', 'event_date']);
            $table->index(['property_id', 'event_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
    }
};
