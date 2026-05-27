<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * MIC Phase A1 — backfill `p24_listings.agency_id`.
 *
 * Resolves HFC's agency_id at migration time (NOT a hardcoded 1) so the
 * backfill is correct on any environment (local, demo, prod). Resolution
 * priority: slug='hfc-coastal' → name LIKE 'HFC%' / 'Home Finders%' → lowest id.
 *
 * Safe to re-run: only updates rows where agency_id IS NULL.
 *
 * down(): NULLs back out. (Reversing the backfill before dropping the
 * column in migration #8.down() is the safe order; if migration #10 ran
 * first, this down() runs first too.)
 */
return new class extends Migration {
    public function up(): void
    {
        $agencyId = $this->resolveHfcAgencyId();

        if ($agencyId === null) {
            // No HFC agency found — bail loudly. Refuse to assign rows to a
            // wrong agency by accident.
            throw new \RuntimeException(
                'p24_listings backfill: could not resolve HFC agency_id by slug, name, or id. '
                . 'Inspect the agencies table and reseed before re-running this migration.'
            );
        }

        $affected = DB::table('p24_listings')
            ->whereNull('agency_id')
            ->update(['agency_id' => $agencyId]);

        // Echo result to the migration runner so the operator sees the count.
        if (PHP_SAPI === 'cli') {
            fwrite(STDOUT, "    → p24_listings backfill: set agency_id={$agencyId} on {$affected} rows" . PHP_EOL);
        }
    }

    public function down(): void
    {
        // Reverse only the rows this migration would have touched (everything
        // that was backfilled to a non-null agency_id). We can't precisely
        // distinguish post-backfill writes here; the right reverse is "null
        // them all" because migration #10 hasn't enforced NOT NULL yet from
        // this migration's perspective (migrate:rollback runs in reverse order).
        DB::table('p24_listings')->update(['agency_id' => null]);
    }

    /**
     * Resolve HFC's agency_id without hardcoding. Order:
     *   1. agencies.slug = 'hfc-coastal'
     *   2. agencies.name LIKE 'HFC%' or 'Home Finders%'
     *   3. Lowest agency_id (single-tenant fallback)
     */
    private function resolveHfcAgencyId(): ?int
    {
        $bySlug = DB::table('agencies')->where('slug', 'hfc-coastal')->value('id');
        if ($bySlug) return (int) $bySlug;

        $byName = DB::table('agencies')
            ->where(function ($q) {
                $q->where('name', 'like', 'HFC%')->orWhere('name', 'like', 'Home Finders%');
            })
            ->orderBy('id')
            ->value('id');
        if ($byName) return (int) $byName;

        // Single-tenant fallback — only safe if exactly ONE agency exists.
        $count = DB::table('agencies')->count();
        if ($count === 1) {
            return (int) DB::table('agencies')->orderBy('id')->value('id');
        }

        return null;
    }
};
