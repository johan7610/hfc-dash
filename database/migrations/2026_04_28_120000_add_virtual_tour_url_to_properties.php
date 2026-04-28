<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            // Generic external virtual-tour URL (iPanorama, Kuula, custom 360 host, etc.).
            // Distinct from matterport_id (Matterport-specific) and youtube_video_id (YouTube-specific).
            // Surfaced on Live Preview + custom websites only — not pushed to portal feeds.
            $table->string('virtual_tour_url', 1000)->nullable()->after('matterport_id');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('virtual_tour_url');
        });
    }
};
