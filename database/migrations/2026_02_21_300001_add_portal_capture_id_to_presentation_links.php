<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presentation_links', function (Blueprint $table) {
            $table->unsignedBigInteger('portal_capture_id')->nullable()->after('override_at');
            $table->index('portal_capture_id');
        });
    }

    public function down(): void
    {
        Schema::table('presentation_links', function (Blueprint $table) {
            $table->dropIndex(['portal_capture_id']);
            $table->dropColumn('portal_capture_id');
        });
    }
};
