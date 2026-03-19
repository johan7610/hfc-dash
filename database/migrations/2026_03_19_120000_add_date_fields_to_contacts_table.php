<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->timestamp('loaded_at')->nullable()->after('address');
            $table->timestamp('modified_at')->nullable()->after('loaded_at');
            $table->timestamp('last_contacted_at')->nullable()->after('modified_at');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn(['loaded_at', 'modified_at', 'last_contacted_at']);
        });
    }
};
