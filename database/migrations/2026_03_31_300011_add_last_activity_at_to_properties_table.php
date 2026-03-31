<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dateTime('last_activity_at')->nullable()->after('updated_at');
        });

        // Backfill: set last_activity_at to updated_at for existing properties
        \Illuminate\Support\Facades\DB::statement('UPDATE properties SET last_activity_at = updated_at WHERE last_activity_at IS NULL');
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('last_activity_at');
        });
    }
};
