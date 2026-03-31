<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('command_tasks', function (Blueprint $table) {
            $table->string('resolution', 30)->nullable()->after('status')
                  ->comment('completed, extended, did_not_happen');
            $table->text('resolution_note')->nullable()->after('resolution');
        });

        Schema::table('calendar_events', function (Blueprint $table) {
            $table->string('resolution', 30)->nullable()->after('status')
                  ->comment('completed, extended, did_not_happen');
            $table->text('resolution_note')->nullable()->after('resolution');
        });
    }

    public function down(): void
    {
        Schema::table('command_tasks', function (Blueprint $table) {
            $table->dropColumn(['resolution', 'resolution_note']);
        });
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->dropColumn(['resolution', 'resolution_note']);
        });
    }
};
