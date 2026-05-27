<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MIC Phase A1 — attempt to convert `idx_tracked_props_geo` to a SPATIAL index
 * (spec §3.3.5).
 *
 * **Constraints we have to respect on this real schema:**
 *
 * 1. MySQL 8.0+ supports SPATIAL on InnoDB only for `POINT` / geometry columns
 *    that are explicitly NOT NULL with a declared SRID. The existing columns
 *    `tracked_properties.latitude` and `tracked_properties.longitude` are
 *    `decimal(10,7) NULLABLE` — they CANNOT receive a SPATIAL index directly.
 *
 * 2. Converting those columns to POINT geometry is a destructive schema
 *    change that touches indexes, FK references, the model layer, and every
 *    read path that selects `latitude` / `longitude` numerically. Out of
 *    scope for Phase A1 (the spec defers it: "Verify MySQL version on
 *    Hetzner during build" — verification done, finding recorded).
 *
 * 3. Spec mandate: "Don't fail the migration." This migration therefore
 *    records the diagnostic and leaves the existing btree composite index
 *    in place — same query plan, no change.
 *
 * Phase B (or whichever phase the spec assigns to the geo upgrade) should:
 *   (a) Add a generated POINT column from lat/lng with SRID 4326.
 *   (b) Add a SPATIAL index on it.
 *   (c) Migrate proximity queries to use ST_Distance_Sphere.
 *   (d) Keep btree as a fallback during the migration window.
 *
 * This migration intentionally does NO schema change. The diagnostic is the
 * artefact.
 */
return new class extends Migration {
    public function up(): void
    {
        $diag = $this->inspectGeoColumns();

        if (PHP_SAPI === 'cli') {
            fwrite(STDOUT, '    → tracked_properties spatial-index attempt:' . PHP_EOL);
            foreach ($diag as $k => $v) {
                fwrite(STDOUT, "        {$k}: {$v}" . PHP_EOL);
            }
            fwrite(STDOUT, '        decision: leaving idx_tracked_props_geo as composite btree (lat/lng are nullable decimal — direct SPATIAL not viable). Phase B will add a generated POINT column + SPATIAL index.' . PHP_EOL);
        }

        // Belt-and-braces: ensure the existing btree composite still exists.
        // If a previous experimental migration dropped it, recreate it here so
        // the proximity-search query plan doesn't degrade silently.
        $hasIndex = collect(DB::select("SHOW INDEX FROM tracked_properties WHERE Key_name = 'idx_tracked_props_geo'"))->isNotEmpty();
        if (!$hasIndex && Schema::hasColumn('tracked_properties', 'latitude') && Schema::hasColumn('tracked_properties', 'longitude')) {
            Schema::table('tracked_properties', function ($table) {
                $table->index(['latitude', 'longitude'], 'idx_tracked_props_geo');
            });
            if (PHP_SAPI === 'cli') {
                fwrite(STDOUT, '        → restored missing idx_tracked_props_geo btree composite.' . PHP_EOL);
            }
        }
    }

    public function down(): void
    {
        // No-op: this migration only ever logs a notice (and conditionally
        // restores the existing btree index). There is nothing to reverse.
    }

    /**
     * @return array<string, string>
     */
    private function inspectGeoColumns(): array
    {
        $version = (string) (DB::selectOne('SELECT VERSION() AS v')->v ?? 'unknown');

        $latCol = DB::selectOne("
            SELECT IS_NULLABLE, DATA_TYPE, COLUMN_TYPE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'tracked_properties'
              AND COLUMN_NAME = 'latitude'
        ");
        $lngCol = DB::selectOne("
            SELECT IS_NULLABLE, DATA_TYPE, COLUMN_TYPE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'tracked_properties'
              AND COLUMN_NAME = 'longitude'
        ");

        return [
            'mysql_version' => $version,
            'latitude'      => $latCol ? sprintf('%s nullable=%s', $latCol->COLUMN_TYPE, $latCol->IS_NULLABLE) : '(not present)',
            'longitude'     => $lngCol ? sprintf('%s nullable=%s', $lngCol->COLUMN_TYPE, $lngCol->IS_NULLABLE) : '(not present)',
            'spatial_viable_directly' => 'NO — SPATIAL requires NOT NULL POINT/geometry columns',
        ];
    }
};
