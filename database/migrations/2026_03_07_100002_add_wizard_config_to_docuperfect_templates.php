<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('docuperfect_templates', function (Blueprint $table) {
            $table->json('wizard_config')->nullable()->after('fields_json');
            $table->boolean('is_esign')->default(false)->after('is_global');
        });
    }

    public function down(): void
    {
        Schema::table('docuperfect_templates', function (Blueprint $table) {
            $table->dropColumn(['wizard_config', 'is_esign']);
        });
    }
};
