<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('command_reminder_defaults', function (Blueprint $table) {
            $table->id();
            $table->string('event_category', 80);
            $table->json('reminder_offsets')->comment('Array of offsets in minutes');
            $table->boolean('escalation_enabled')->default(true);
            $table->unsignedInteger('escalation_delay')->default(1440)->comment('Minutes before escalating');
            $table->string('escalation_to', 20)->default('bm')->comment('bm, admin, both');
            $table->foreignId('agency_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('command_reminder_defaults');
    }
};
