<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_dashboard_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // Idle property alerts
            $table->boolean('idle_alerts_enabled')->default(true);
            $table->unsignedSmallInteger('idle_threshold_days')->default(14);
            $table->string('idle_alert_day', 20)->nullable()->comment('monday-sunday or null for daily');
            $table->time('idle_alert_time')->default('08:00');

            // Document reminder preferences
            $table->boolean('doc_reminders_enabled')->default(true);
            $table->unsignedSmallInteger('doc_reminder_hours_before')->default(24);

            // Lease / compliance reminders
            $table->boolean('lease_expiry_reminders')->default(true);
            $table->unsignedSmallInteger('lease_reminder_days_before')->default(90);
            $table->boolean('fica_reminders')->default(true);
            $table->boolean('ffc_reminders')->default(true);

            // Task reminders
            $table->boolean('task_due_reminders')->default(true);
            $table->unsignedSmallInteger('task_reminder_hours_before')->default(4);
            $table->boolean('overdue_daily_digest')->default(true);
            $table->time('digest_time')->default('08:00');

            // Calendar preferences
            $table->string('default_calendar_view', 20)->default('month');
            $table->boolean('weekend_visible')->default(false);
            $table->time('working_hours_start')->default('08:00');
            $table->time('working_hours_end')->default('17:00');

            // Notification channels
            $table->boolean('notify_in_app')->default(true);
            $table->boolean('notify_email')->default(true);

            $table->timestamps();
        });

        // Add dashboard_settings_mode to agencies: 'user' = individual, 'agency' = shared
        Schema::table('agencies', function (Blueprint $table) {
            $table->string('dashboard_settings_mode', 10)->default('user')->after('is_active')
                  ->comment('user = individual settings, agency = shared agency settings');
        });

        // Agency-level dashboard settings (used when mode = agency)
        Schema::create('agency_dashboard_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->unique()->constrained()->cascadeOnDelete();

            // Same fields as user_dashboard_settings
            $table->boolean('idle_alerts_enabled')->default(true);
            $table->unsignedSmallInteger('idle_threshold_days')->default(14);
            $table->string('idle_alert_day', 20)->nullable();
            $table->time('idle_alert_time')->default('08:00');

            $table->boolean('doc_reminders_enabled')->default(true);
            $table->unsignedSmallInteger('doc_reminder_hours_before')->default(24);

            $table->boolean('lease_expiry_reminders')->default(true);
            $table->unsignedSmallInteger('lease_reminder_days_before')->default(90);
            $table->boolean('fica_reminders')->default(true);
            $table->boolean('ffc_reminders')->default(true);

            $table->boolean('task_due_reminders')->default(true);
            $table->unsignedSmallInteger('task_reminder_hours_before')->default(4);
            $table->boolean('overdue_daily_digest')->default(true);
            $table->time('digest_time')->default('08:00');

            $table->string('default_calendar_view', 20)->default('month');
            $table->boolean('weekend_visible')->default(false);
            $table->time('working_hours_start')->default('08:00');
            $table->time('working_hours_end')->default('17:00');

            $table->boolean('notify_in_app')->default(true);
            $table->boolean('notify_email')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agency_dashboard_settings');
        Schema::dropIfExists('user_dashboard_settings');
        Schema::table('agencies', function (Blueprint $table) {
            $table->dropColumn('dashboard_settings_mode');
        });
    }
};
