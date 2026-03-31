<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_dashboard_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('event_reminder_hours_before')->default(24)->after('task_reminder_hours_before');
        });

        Schema::table('agency_dashboard_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('event_reminder_hours_before')->default(24)->after('task_reminder_hours_before');
        });
    }

    public function down(): void
    {
        Schema::table('user_dashboard_settings', function (Blueprint $table) {
            $table->dropColumn('event_reminder_hours_before');
        });
        Schema::table('agency_dashboard_settings', function (Blueprint $table) {
            $table->dropColumn('event_reminder_hours_before');
        });
    }
};
