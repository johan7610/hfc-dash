<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('docuperfect_templates', function (Blueprint $table) {
            $table->json('cds_json')->nullable()->after('fields_json');
            $table->json('field_mappings')->nullable()->after('cds_json');
            $table->string('allowed_delivery_modes', 100)
                ->default('esign,wet_ink,download')->after('field_mappings');
            $table->string('security_tier', 20)
                ->default('enhanced')->after('allowed_delivery_modes');
        });
    }

    public function down(): void
    {
        Schema::table('docuperfect_templates', function (Blueprint $table) {
            $table->dropColumn(['cds_json', 'field_mappings', 'allowed_delivery_modes', 'security_tier']);
        });
    }
};
