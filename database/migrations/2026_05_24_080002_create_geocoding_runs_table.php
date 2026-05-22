<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3f C4 — geocoding run log.
 *
 * Cheap insurance: one row per resolve attempt across the whole stack
 * (sync resolver call, bulk backfill iteration, post-import dispatch).
 * Lets us audit "where did this property's GPS come from?" and gives QA
 * a way to spot patterns in failures.
 *
 * Not consulted by the resolver itself — that's what geocoding_cache is
 * for. Purely observational.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('geocoding_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('batch_id')->nullable()->index();
            $table->string('entity_type', 50)->nullable();   // 'property' / 'tracked_property' / 'ad_hoc'
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('address', 500);
            $table->enum('result', ['resolved', 'failed', 'cached'])->default('failed');
            $table->string('source', 30)->nullable();
            $table->string('confidence', 20)->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['entity_type', 'entity_id']);
            $table->index('result');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geocoding_runs');
    }
};
