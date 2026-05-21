<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * MIC Phase A1 — backfill the new property identifier columns from the
 * Tracked Property graph (spec §3.3.3).
 *
 * Schema deviation from spec text:
 *   Spec says "Backfill from tracked_properties WHERE properties.tracked_property_id IS NOT NULL".
 *   But `properties.tracked_property_id` does NOT exist on the current schema.
 *   The inverse FK exists instead: `tracked_properties.promoted_to_property_id` → properties.id.
 *   This migration uses the inverse JOIN, which is semantically identical.
 *
 * Only fills columns that are currently NULL — never overwrites manually-set
 * identifiers. Most properties stay NULL (only promoted-from-TP rows get
 * filled).
 */
return new class extends Migration {
    public function up(): void
    {
        // Multi-table UPDATE via inverse FK: tracked_properties.promoted_to_property_id → properties.id.
        // Each column is only updated when the destination is currently NULL —
        // never overwrites existing values.
        $sql = "
            UPDATE properties p
            INNER JOIN tracked_properties tp ON tp.promoted_to_property_id = p.id
            SET
                p.erf_number               = COALESCE(p.erf_number, tp.erf_number),
                p.title_deed_number        = COALESCE(p.title_deed_number, tp.title_deed_number),
                p.municipal_valuation      = COALESCE(p.municipal_valuation, tp.municipal_valuation),
                p.municipal_valuation_year = COALESCE(p.municipal_valuation_year, tp.municipal_valuation_year)
            WHERE tp.promoted_to_property_id IS NOT NULL
              AND tp.deleted_at IS NULL
        ";

        $affected = DB::update($sql);

        if (PHP_SAPI === 'cli') {
            fwrite(STDOUT, "    → properties identifier backfill from tracked_properties: {$affected} promoted rows touched" . PHP_EOL);
        }
    }

    public function down(): void
    {
        // We only filled values that were NULL pre-backfill. There is no audit
        // trail of which exact columns we wrote, so the safe reverse is a no-op
        // — the column drop in #14.down() removes the data entirely.
        // (Explicitly choosing a no-op rather than `UPDATE … SET col=NULL` to
        //  avoid wiping any post-backfill manual edits.)
    }
};
