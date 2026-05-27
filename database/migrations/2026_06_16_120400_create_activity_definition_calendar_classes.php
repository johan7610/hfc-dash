<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module 6 (M6.2) — per-agency mapping linking a calendar event_class
 * (slug stored on calendar_events.category) to an activity_definition
 * that should auto-credit when an event of that class lands.
 *
 * Schema note: the M6.2 prompt anticipated a `calendar_event_classes`
 * lookup table; the actual data model is `calendar_event_class_settings`
 * keyed by (agency_id, event_class slug). Mapping therefore stores the
 * event_class string directly — same pattern the rest of the calendar
 * system uses. The mapping is per-agency by design (Module 6 spec §3.6).
 *
 * Anti-gaming knobs live here (per the build prompt):
 *   - requires_feedback      → true ⇒ provisional until feedback captured
 *   - auto_revoke_after_hours→ stale provisional → revoked after N hours
 *   - daily_cap              → max credits per agent per day for this map
 *   - back_date_limit_hours  → events back-dated > N hours earn nothing
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('activity_definition_calendar_classes', function (Blueprint $t) {
            // MySQL has a 64-char limit on identifier names; the auto-generated
            // FK + unique names hit that ceiling fast with this table name.
            // All constraints below use short explicit names — `adcc_…`.
            $t->id();
            $t->foreignId('agency_id')->constrained('agencies', 'id', 'adcc_agency_fk')->cascadeOnDelete();
            $t->string('event_class', 64); // calendar_events.category slug
            $t->foreignId('activity_definition_id')
                ->constrained('activity_definitions', 'id', 'adcc_def_fk')
                ->cascadeOnDelete();
            $t->integer('value_per_event')->default(1);
            $t->boolean('requires_feedback')->default(true);
            $t->integer('auto_revoke_after_hours')->nullable()->default(24);
            $t->integer('daily_cap')->nullable(); // NULL = no cap
            $t->integer('back_date_limit_hours')->default(48);
            $t->boolean('is_active')->default(true);
            $t->foreignId('created_by')->nullable()
                ->constrained('users', 'id', 'adcc_created_by_fk')->nullOnDelete();
            $t->foreignId('updated_by')->nullable()
                ->constrained('users', 'id', 'adcc_updated_by_fk')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();

            $t->unique(
                ['agency_id', 'event_class', 'activity_definition_id', 'deleted_at'],
                'adcc_agency_class_def_unique'
            );
            $t->index(['agency_id', 'event_class', 'is_active'], 'adcc_agency_class_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_definition_calendar_classes');
    }
};
