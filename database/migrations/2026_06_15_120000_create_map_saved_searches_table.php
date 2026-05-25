<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase A.3.2 — per-user, agency-scoped saved searches for the Map.
 *
 * Storage choice: a dedicated table (not user_settings JSON) because we
 * need named retrieval, ORDER BY name, and a per-user "default" flag.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('map_saved_searches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('name', 120);
            $t->json('filter_payload');
            $t->boolean('is_default')->default(false);
            $t->timestamps();
            $t->softDeletes();

            $t->unique(['agency_id', 'user_id', 'name'], 'map_saved_searches_user_name_unique');
            $t->index(['agency_id', 'user_id'], 'map_saved_searches_owner_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('map_saved_searches');
    }
};
