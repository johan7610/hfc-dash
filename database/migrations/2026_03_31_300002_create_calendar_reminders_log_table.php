<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_reminders_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->string('channel', 20)->comment('app, email, sms');
            $table->integer('offset_minutes');
            $table->dateTime('sent_at');
            $table->dateTime('read_at')->nullable();
            $table->dateTime('actioned_at')->nullable();
            $table->boolean('escalated')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_reminders_log');
    }
};
