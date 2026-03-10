<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            $table->string('phone_label', 100)->nullable()->after('phone');
            $table->string('phone_secondary_label', 100)->nullable()->after('phone_secondary');
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->string('phone_label', 100)->nullable()->after('phone');
            $table->string('phone_secondary_label', 100)->nullable()->after('phone_secondary');
        });
    }

    public function down(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            $table->dropColumn(['phone_label', 'phone_secondary_label']);
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn(['phone_label', 'phone_secondary_label']);
        });
    }
};
