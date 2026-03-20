<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('docuperfect_templates', 'party_mode')) {
            Schema::table('docuperfect_templates', function (Blueprint $table) {
                $table->string('party_mode', 20)->default('shared')->after('is_esign');
            });
        }
    }

    public function down(): void
    {
        Schema::table('docuperfect_templates', function (Blueprint $table) {
            $table->dropColumn('party_mode');
        });
    }
};
