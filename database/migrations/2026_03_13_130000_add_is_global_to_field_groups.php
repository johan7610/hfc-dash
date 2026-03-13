<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('docuperfect_field_groups', function (Blueprint $table) {
            $table->boolean('is_global')->default(false)->after('sort_order');

            // Make agency_id nullable so global groups can have null agency_id
            $table->foreignId('agency_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('docuperfect_field_groups', function (Blueprint $table) {
            $table->dropColumn('is_global');
            $table->foreignId('agency_id')->nullable(false)->change();
        });
    }
};
