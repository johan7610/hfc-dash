<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('docuperfect_templates', 'is_esign')) {
            Schema::table('docuperfect_templates', function (Blueprint $table) {
                $table->boolean('is_esign')->default(true)->after('is_global');
            });
        }
    }

    public function down(): void
    {
        Schema::table('docuperfect_templates', function (Blueprint $table) {
            $table->dropColumn('is_esign');
        });
    }
};
