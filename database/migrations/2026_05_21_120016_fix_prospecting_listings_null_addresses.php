<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * MIC Phase A1 — replace `prospecting_listings.address = 'Address not available'`
 * with NULL (spec §3.3.4).
 *
 * P24 emails hide the full street address; the legacy ingestion path used the
 * literal string 'Address not available' as a placeholder. That string then
 * propagated through dedup/matching logic as if it were a real address, causing
 * false matches and confusing display. NULL is the correct semantic.
 *
 * No schema change. Data-only. Idempotent (re-running is a no-op once cleared).
 */
return new class extends Migration {
    public function up(): void
    {
        $affected = DB::table('prospecting_listings')
            ->where('address', 'Address not available')
            ->update(['address' => null]);

        if (PHP_SAPI === 'cli') {
            fwrite(STDOUT, "    → prospecting_listings address-NULL fix: {$affected} placeholder rows cleared" . PHP_EOL);
        }
    }

    public function down(): void
    {
        // Best-effort reverse — restore the placeholder for rows that currently
        // have NULL address. Note: this DOES touch rows that were null for
        // reasons other than this migration (newly-inserted null-address rows
        // post-fix), but that's the price of reverting a data fix. The right
        // mitigation in practice is "don't roll this migration back" — it's a
        // data-quality improvement.
        DB::table('prospecting_listings')
            ->whereNull('address')
            ->update(['address' => 'Address not available']);
    }
};
