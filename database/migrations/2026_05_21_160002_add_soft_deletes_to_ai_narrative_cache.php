<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MIC Phase B2 — restore SoftDeletes on `ai_narrative_cache`.
 *
 * Phase A1 created the table without `deleted_at` (cache is ephemeral; the
 * model originally claimed expires_at alone was sufficient). Phase B2's sweep
 * pattern requires the standard two-phase cleanup: SweepExpiredNarrativeCacheJob
 * soft-deletes expired rows daily; PurgeOldSoftDeletedCacheJob hard-deletes
 * rows that have been soft-deleted >90 days.
 *
 * To keep `updateOrCreate(['cache_key' => …])` working alongside soft-deleted
 * rows, the unique index on `cache_key` is replaced with a composite unique on
 * `(cache_key, deleted_at)`. MySQL treats NULL as distinct in unique indexes,
 * so this allows one active row per cache_key plus any number of soft-deleted
 * historical rows under the same key.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('ai_narrative_cache', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('ai_narrative_cache', function (Blueprint $table) {
            $table->dropUnique('uq_anc_cache_key');
        });

        Schema::table('ai_narrative_cache', function (Blueprint $table) {
            $table->unique(['cache_key', 'deleted_at'], 'uq_anc_cache_key_deleted_at');
        });
    }

    public function down(): void
    {
        // Refuse to roll back if soft-deleted duplicates exist that would
        // violate the simpler unique constraint — they must be purged first.
        $duplicates = \DB::table('ai_narrative_cache')
            ->select('cache_key')
            ->groupBy('cache_key')
            ->havingRaw('COUNT(*) > 1')
            ->count();
        if ($duplicates > 0) {
            throw new \RuntimeException(
                "Cannot roll back: {$duplicates} cache_key(s) have soft-deleted duplicates. "
                . 'Run PurgeOldSoftDeletedCacheJob (or hard-delete soft-deleted rows manually) before rolling back.'
            );
        }

        Schema::table('ai_narrative_cache', function (Blueprint $table) {
            $table->dropUnique('uq_anc_cache_key_deleted_at');
        });

        Schema::table('ai_narrative_cache', function (Blueprint $table) {
            $table->unique('cache_key', 'uq_anc_cache_key');
        });

        Schema::table('ai_narrative_cache', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
