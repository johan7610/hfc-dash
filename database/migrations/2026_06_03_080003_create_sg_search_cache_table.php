<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3j B3 — server-side cache of SG search responses.
 *
 * Polite-citizen layer: 24h TTL by default so the SG site never sees
 * back-to-back requests for the same parcel. Keyed by SHA-256 of the
 * normalised query so any spelling variation that normalises the same
 * hits the same row.
 *
 * parsed_documents_json holds the extracted document list — the canonical
 * read shape for the controller. response_body holds the raw HTML for
 * diagnosis when the parser later breaks (SG HTML is hand-rolled JSP, it
 * WILL drift).
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('sg_search_cache')) {
            return;
        }

        Schema::create('sg_search_cache', function (Blueprint $table) {
            $table->id();
            $table->string('query_hash', 64)->unique();

            $table->string('province', 30);
            $table->string('rural_urban', 10);
            $table->string('town', 200);
            $table->string('parcel_number', 50);
            $table->string('portion', 20);
            $table->string('farm_name', 200)->nullable();

            $table->longText('response_body')->nullable();
            $table->json('parsed_documents_json');

            $table->timestamp('fetched_at')->useCurrent();
            $table->timestamp('expires_at')->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sg_search_cache');
    }
};
