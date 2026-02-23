<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presentations', function (Blueprint $table) {
            $table->unsignedSmallInteger('bathrooms')->nullable()->after('bedrooms');
            $table->unsignedSmallInteger('garages_parking')->nullable()->after('bathrooms');
            $table->unsignedInteger('erf_size_m2')->nullable()->after('garages_parking');
        });
    }

    public function down(): void
    {
        Schema::table('presentations', function (Blueprint $table) {
            $table->dropColumn(['bathrooms', 'garages_parking', 'erf_size_m2']);
        });
    }
};
