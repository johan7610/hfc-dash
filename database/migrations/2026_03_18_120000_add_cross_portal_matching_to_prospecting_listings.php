<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prospecting_listings', function (Blueprint $table) {
            $table->string('normalized_address', 255)->nullable()->index()->after('address');
            $table->unsignedBigInteger('property_group_id')->nullable()->index()->after('normalized_address');

            $table->index(['agency_id', 'normalized_address']);
        });
    }

    public function down(): void
    {
        Schema::table('prospecting_listings', function (Blueprint $table) {
            $table->dropIndex(['agency_id', 'normalized_address']);
            $table->dropColumn(['normalized_address', 'property_group_id']);
        });
    }
};
