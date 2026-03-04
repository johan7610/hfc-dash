<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_listings', function (Blueprint $table) {
            $table->text('primary_image_url')->nullable()->after('current_fields_json');
        });
    }

    public function down(): void
    {
        Schema::table('portal_listings', function (Blueprint $table) {
            $table->dropColumn('primary_image_url');
        });
    }
};
