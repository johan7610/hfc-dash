<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('command_tasks', function (Blueprint $table) {
            $table->boolean('send_reminder')->default(true)->after('priority');
        });

        Schema::table('calendar_events', function (Blueprint $table) {
            $table->boolean('send_reminder')->default(true)->after('priority');
        });
    }

    public function down(): void
    {
        Schema::table('command_tasks', function (Blueprint $table) {
            $table->dropColumn('send_reminder');
        });
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->dropColumn('send_reminder');
        });
    }
};
