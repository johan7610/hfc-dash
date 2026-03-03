<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('agent_photo_path')->nullable()->after('agency_id');
            $table->string('ffc_certificate_path')->nullable()->after('agent_photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['agent_photo_path', 'ffc_certificate_path']);
        });
    }
};
