<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MIC Phase A1 — `tracked_property_addresses` (spec §3.2.1).
 *
 * Address-with-history table. Solves the silent-killer "P24 publishes wrong
 * address forever" problem. Exactly one row per tracked_property is_primary=true;
 * a model observer (added in Phase A3) keeps the primary cached on the parent
 * `tracked_properties` row.
 *
 * Multi-tenancy: agency_id present, BelongsToAgency on the model when it lands.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tracked_property_addresses', function (Blueprint $table) {
            $table->comment('Per-TP address history; one is_primary=true per tracked_property cached onto tracked_properties via observer (Phase A3).');

            $table->id();

            $table->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $table->foreignId('tracked_property_id')->constrained('tracked_properties')->cascadeOnDelete();

            $table->string('street_number', 50)->nullable();
            $table->string('street_name', 200)->nullable()
                  ->comment('Normalised on write (St→Street, Rd→Road, …) — see TrackedPropertyMatchOrCreateService::normaliseStreetName().');
            $table->string('unit_number', 50)->nullable();
            $table->string('complex_name', 200)->nullable();

            $table->string('suburb', 100)->nullable();
            $table->string('suburb_normalised', 100)->nullable()
                  ->comment('Lowercase + strip punctuation + collapse whitespace; see TrackedProperty::normaliseSuburb().');
            $table->string('town', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('postal_code', 20)->nullable();

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // source_type kept as string (not enum) so additional sources can be added
            // without a schema change. Recognised values listed in the spec §3.2.1.
            $table->string('source_type', 50)
                  ->comment('p24 | pp | chrome_capture | cmainfo | manual_agent | manual_admin | deeds_office');
            $table->string('source_ref', 200)->nullable()
                  ->comment('The originating record ID (portal listing id, presentation id, capture id, etc).');

            $table->enum('confidence', ['low', 'medium', 'high', 'verified'])->default('low')
                  ->comment('verified = agent-confirmed; promotes to primary per spec §3.2.1.');
            $table->boolean('is_primary')->default(false);

            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();

            $table->text('notes')->nullable();

            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['agency_id', 'tracked_property_id', 'is_primary'], 'idx_tpa_agency_tp_primary');
            $table->index(['agency_id', 'suburb_normalised', 'street_name'], 'idx_tpa_agency_suburb_street');
            $table->index(['agency_id', 'latitude', 'longitude'], 'idx_tpa_agency_geo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracked_property_addresses');
    }
};
