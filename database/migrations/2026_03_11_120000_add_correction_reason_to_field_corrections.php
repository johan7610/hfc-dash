<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('docuperfect_field_corrections', function (Blueprint $table) {
            $table->text('correction_reason')->nullable()->after('user_corrected_label');
        });
    }

    public function down(): void
    {
        Schema::table('docuperfect_field_corrections', function (Blueprint $table) {
            $table->dropColumn('correction_reason');
        });
    }
};
