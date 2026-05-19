<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bug 2(b): the CDS template builder offered no source that pulls the
 * Step-4 "Document Details" Commission % or Mandate dates into a document
 * field. resolveDealFromKey() already resolves deal.commission /
 * deal.mandate_start / deal.mandate_expiry from $details — only the
 * docuperfect_named_fields catalogue rows were missing (these are wizard
 * $details keys, not real `deals` columns, so docuperfect:sync-fields
 * never creates them). Seed them idempotently so they appear as
 * selectable sources in the builder. 'deal' is a valid source_type enum
 * value on MySQL (added by an earlier MODIFY migration).
 */
return new class extends Migration
{
    private const ROWS = [
        ['name' => 'Mandate Commission % (Step 4)', 'source_column' => 'commission'],
        ['name' => 'Mandate Start Date (Step 4)',   'source_column' => 'mandate_start'],
        ['name' => 'Mandate Expiry Date (Step 4)',  'source_column' => 'mandate_expiry'],
    ];

    public function up(): void
    {
        foreach (self::ROWS as $row) {
            $exists = DB::table('docuperfect_named_fields')
                ->where('source_type', 'deal')
                ->where('source_column', $row['source_column'])
                ->whereNull('source_contact_type')
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('docuperfect_named_fields')->insert([
                'name'                => $row['name'],
                'field_type'          => 'text',
                'source_type'         => 'deal',
                'source_column'       => $row['source_column'],
                'source_contact_type' => null,
                'sort_order'          => 900,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);
        }
    }

    public function down(): void
    {
        // No-op: these are reference catalogue rows. Removing them would
        // break any template field already mapped to them. Reversal is
        // intentionally not destructive.
    }
};
