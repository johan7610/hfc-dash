<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->string('listing_type')->default('sale')->after('mandate_type'); // 'sale' | 'rental'
        });

        // Back-fill: if mandate_type contains 'rental', set listing_type to 'rental'
        \DB::table('properties')
            ->where('mandate_type', 'like', '%rental%')
            ->update(['listing_type' => 'rental']);
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('listing_type');
        });
    }
};
