<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_user_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained();
            $table->string('default_view', 20)->default('month');
            $table->time('working_hours_start')->default('08:00');
            $table->time('working_hours_end')->default('17:00');
            $table->boolean('weekend_visible')->default(false);
            $table->string('ical_token', 64)->unique()->nullable();
            $table->boolean('email_reminders')->default(true);
            $table->boolean('app_reminders')->default(true);
            $table->string('digest_email', 20)->default('daily')->comment('none, daily, weekly');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_user_preferences');
    }
};
