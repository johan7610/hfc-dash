<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module 6 (M6.1) — schema foundation for the Activity Points Engine.
 *
 * Two tables touched in one migration so the columns deploy together
 * and M6.2-M6.5 can reference them cleanly.
 *
 * activity_definitions
 *   + scope       (string) — 'system' | 'agency'. Existing rows were 'global'
 *                            and become 'system' (universal). Agency-scoped
 *                            definitions added later via Tinker as needed.
 *   + agency_id   (FK)     — required when scope='agency', NULL when 'system'.
 *                            Constraint enforced at the model layer (creating
 *                            hook) — see app/Models/ActivityDefinition.php.
 *
 * daily_activity_entries
 *   + point_state         enum-style varchar: provisional | confirmed | revoked | overridden
 *   + source              enum-style varchar: manual | auto_calendar | auto_other
 *   + calendar_event_id   FK calendar_events (auto-credited rows)
 *   + confirmed_at        nullable
 *   + revoked_at          nullable
 *   + revoke_reason       text nullable
 *   + overridden_by_user_id FK users nullable
 *   + override_reason     text nullable
 *   + override_audit_json JSON nullable (before/after capture)
 *
 * Backfill: every existing daily_activity_entries row is treated as a
 * manual confirmed entry — point_state='confirmed', source='manual',
 * confirmed_at=updated_at (closest existing timestamp to the moment of
 * legitimacy). 1,492 rows on live; backfilled in a single UPDATE.
 */
return new class extends Migration {
    public function up(): void
    {
        // ── activity_definitions ────────────────────────────────────────
        // Data migration first: 'global' → 'system'. Then add new column +
        // index. scope stays varchar(20) — same shape as today, just with
        // a different set of allowed values enforced at the model layer.
        DB::table('activity_definitions')->where('scope', 'global')->update(['scope' => 'system']);

        Schema::table('activity_definitions', function (Blueprint $t) {
            // agency_id nullable — required only when scope='agency'.
            $t->foreignId('agency_id')->nullable()->after('scope')
              ->constrained('agencies')->nullOnDelete();
            $t->index(['scope', 'agency_id'], 'activity_definitions_scope_agency_idx');
        });

        // ── daily_activity_entries ──────────────────────────────────────
        Schema::table('daily_activity_entries', function (Blueprint $t) {
            $t->string('point_state', 20)->default('confirmed')->after('value');
            $t->string('source', 20)->default('manual')->after('point_state');
            $t->foreignId('calendar_event_id')->nullable()->after('source')
              ->constrained('calendar_events')->nullOnDelete();
            $t->timestamp('confirmed_at')->nullable()->after('calendar_event_id');
            $t->timestamp('revoked_at')->nullable()->after('confirmed_at');
            $t->text('revoke_reason')->nullable()->after('revoked_at');
            $t->foreignId('overridden_by_user_id')->nullable()->after('revoke_reason')
              ->constrained('users')->nullOnDelete();
            $t->text('override_reason')->nullable()->after('overridden_by_user_id');
            $t->json('override_audit_json')->nullable()->after('override_reason');

            $t->index(['point_state', 'activity_date'], 'dae_state_date_idx');
            $t->index('source', 'dae_source_idx');
            $t->index('calendar_event_id', 'dae_calendar_event_idx');
        });

        // Backfill — every existing row is a legitimate manual confirmed entry.
        // confirmed_at = updated_at gives us the best-available "confirmed when"
        // marker without changing semantic meaning. updated_at is closer than
        // created_at for rows that have been edited (the most recent admin action).
        DB::table('daily_activity_entries')
            ->whereNull('confirmed_at')
            ->update([
                'point_state'  => 'confirmed',
                'source'       => 'manual',
                'confirmed_at' => DB::raw('updated_at'),
            ]);
    }

    public function down(): void
    {
        Schema::table('daily_activity_entries', function (Blueprint $t) {
            $t->dropIndex('dae_state_date_idx');
            $t->dropIndex('dae_source_idx');
            $t->dropIndex('dae_calendar_event_idx');
            $t->dropConstrainedForeignId('calendar_event_id');
            $t->dropConstrainedForeignId('overridden_by_user_id');
            $t->dropColumn([
                'point_state', 'source', 'confirmed_at', 'revoked_at', 'revoke_reason',
                'override_reason', 'override_audit_json',
            ]);
        });

        Schema::table('activity_definitions', function (Blueprint $t) {
            $t->dropIndex('activity_definitions_scope_agency_idx');
            $t->dropConstrainedForeignId('agency_id');
        });

        // Restore the old 'global' label for any rows we touched.
        DB::table('activity_definitions')->where('scope', 'system')->update(['scope' => 'global']);
    }
};
