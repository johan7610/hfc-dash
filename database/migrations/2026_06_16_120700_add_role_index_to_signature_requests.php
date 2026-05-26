<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Recipient Loop Engine — B1 schema foundation.
 *
 * Adds a real persisted `role_index` to the live recipient table
 * (`signature_requests` — B0's audit had it as `signature_template_recipients`
 * which doesn't exist). Pre-B1 the wizard suffixed `party_role` itself
 * (`seller`, `seller_2`, `seller_3`) for uniqueness; the live data is
 * already in that shape (88 `seller`, 84 `seller_2`, 1 `lessee`, 1 `lessor`
 * at migration time).
 *
 * Path A backfill: split trailing `_<int>` off existing party_role values
 * into the new column. Tokens that legitimately contain underscores
 * (e.g. `acquiring_party`) stay intact because the regex requires a
 * trailing integer.
 *
 *   seller_2  →  party_role = seller,  role_index = 2
 *   seller    →  party_role = seller,  role_index = 1 (column default)
 *   acquiring_party → unchanged
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('signature_requests', function (Blueprint $t) {
            $t->unsignedSmallInteger('role_index')
                ->default(1)
                ->after('party_role');
            $t->index(
                ['signature_template_id', 'party_role', 'role_index'],
                'sigreq_template_role_index_idx'
            );
        });

        // Backfill via PHP — portable across MySQL + SQLite, doesn't depend
        // on REGEXP_REPLACE which isn't available on SQLite by default.
        // Matches `prefix_<digits>` only; preserves multi-underscore tokens
        // like `acquiring_party` because the regex anchors a trailing int.
        DB::table('signature_requests')->orderBy('id')->chunk(500, function ($rows) {
            foreach ($rows as $row) {
                if (preg_match('/^(.+)_(\d+)$/', (string) $row->party_role, $m)) {
                    DB::table('signature_requests')
                        ->where('id', $row->id)
                        ->update([
                            'party_role' => $m[1],
                            'role_index' => (int) $m[2],
                        ]);
                }
            }
        });
    }

    public function down(): void
    {
        // Reverse backfill: re-suffix party_role from role_index where index > 1.
        // Note: this won't restore original suffix style perfectly (numbers > 9
        // were impossible before), but it gets back to the previous shape.
        DB::table('signature_requests')->where('role_index', '>', 1)->orderBy('id')->chunk(500, function ($rows) {
            foreach ($rows as $row) {
                DB::table('signature_requests')
                    ->where('id', $row->id)
                    ->update(['party_role' => $row->party_role . '_' . $row->role_index]);
            }
        });

        Schema::table('signature_requests', function (Blueprint $t) {
            $t->dropIndex('sigreq_template_role_index_idx');
            $t->dropColumn('role_index');
        });
    }
};
