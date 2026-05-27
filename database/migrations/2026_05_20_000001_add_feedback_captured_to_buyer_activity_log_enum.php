<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Widen buyer_activity_log.activity_type to include 'feedback_captured'.
 *
 * CalendarController::storeFeedback() writes activity_type='feedback_captured'
 * to fan a captured viewing-feedback row out to the buyer's timeline. The
 * column was defined as an ENUM that did NOT contain that value — under
 * MySQL STRICT mode the write threw SQLSTATE[01000] 1265 "Data truncated",
 * the DB::transaction rolled back, and the JS caller silently saw a 500.
 * Net effect: "Save & Next Property" in the Capture Feedback modal did
 * nothing.
 *
 * SPEC_corex_activity_engine.md §property_activity_log enum (lines 377-381)
 * treats 'feedback_captured' and 'viewing_completed' as distinct activity
 * types, so we WIDEN the enum (A2) rather than collapse the controller to
 * 'viewing_completed' (A1) — preserves the spec's semantic distinction.
 *
 * Idempotent (re-running the migration on a DB that already has
 * 'feedback_captured' is a no-op) and reversible (down() restores the
 * original enum, demoting any rows that used 'feedback_captured' to
 * 'viewing_completed' first so the ALTER cannot fail on existing data).
 */
return new class extends Migration {
    private const OLD_ENUM_VALUES = [
        'viewing_completed', 'presentation', 'contact_access', 'note_added',
        'call_logged', 'email_sent', 'whatsapp_sent', 'manual', 'retention_action',
    ];
    private const NEW_VALUE = 'feedback_captured';

    public function up(): void
    {
        if ($this->enumContains(self::NEW_VALUE)) {
            return; // idempotent no-op
        }

        $values = array_merge(self::OLD_ENUM_VALUES, [self::NEW_VALUE]);
        $enum = "ENUM('" . implode("','", $values) . "')";

        DB::statement("ALTER TABLE buyer_activity_log MODIFY COLUMN activity_type {$enum} NOT NULL");
    }

    public function down(): void
    {
        if (!$this->enumContains(self::NEW_VALUE)) {
            return; // already at the old shape
        }

        // Demote any rows on the new value to the nearest existing one so
        // the narrowing ALTER doesn't truncate.
        DB::table('buyer_activity_log')
            ->where('activity_type', self::NEW_VALUE)
            ->update(['activity_type' => 'viewing_completed']);

        $enum = "ENUM('" . implode("','", self::OLD_ENUM_VALUES) . "')";
        DB::statement("ALTER TABLE buyer_activity_log MODIFY COLUMN activity_type {$enum} NOT NULL");
    }

    private function enumContains(string $value): bool
    {
        $row = DB::selectOne(
            "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'buyer_activity_log'
               AND COLUMN_NAME = 'activity_type'"
        );

        return $row !== null && str_contains($row->COLUMN_TYPE, "'{$value}'");
    }
};
