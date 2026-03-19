<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->unsignedInteger('whatsapp_count')->default(0)->after('last_contacted_at');
            $table->unsignedInteger('email_count')->default(0)->after('whatsapp_count');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_count', 'email_count']);
        });
    }
};
