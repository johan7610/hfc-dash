<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            $table->string('phone_secondary')->nullable()->after('phone');
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->string('phone_secondary')->nullable()->after('phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            $table->dropColumn('phone_secondary');
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn('phone_secondary');
        });
    }
};
