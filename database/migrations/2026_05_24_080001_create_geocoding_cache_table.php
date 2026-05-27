<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3f A1 — geocoding cache.
 *
 * Single source of truth for "what's at this address?". The waterfall in
 * AddressResolverService writes every attempt (success OR failure) here so
 * we never re-call an expensive geocoder for an address we've seen.
 *
 * Failures ARE cached on purpose — if Google says "no result" for an
 * address, repeating the call burns quota without changing the answer.
 * A periodic re-attempt for old 'failed' rows can be scheduled later if
 * we want to retry.
 *
 * UNIQUE on address_normalised — collisions mean a new resolution attempt
 * overwrites the previous one (the resolver does that explicitly via
 * updateOrCreate). No silent drops.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('geocoding_cache', function (Blueprint $table) {
            $table->id();
            $table->string('address_normalised', 500);
            $table->string('address_raw', 500);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->enum('confidence', ['exact', 'street', 'suburb', 'town', 'failed'])
                ->default('failed');
            $table->enum('source', [
                'market_report', 'portal_capture', 'p24', 'google',
                'nominatim', 'manual', 'cache',
            ])->default('cache');
            $table->string('source_ref', 200)->nullable();
            $table->string('resolved_address', 500)->nullable();
            $table->string('municipality_name', 100)->nullable();
            $table->string('suburb_normalised', 100)->nullable();
            $table->string('failure_reason', 200)->nullable();
            $table->timestamp('last_attempted_at')->useCurrent();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('address_normalised', 'geocoding_cache_addr_unique');
            $table->index(['latitude', 'longitude'], 'geocoding_cache_latlng_idx');
            $table->index('confidence');
            $table->index('source');
            $table->index('suburb_normalised');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geocoding_cache');
    }
};
