<?php

declare(strict_types=1);

namespace Tests\Feature\ActivityPoints;

use App\Models\ActivityDefinition;
use App\Models\ActivityDefinitionCalendarClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Module 6 (M6.2) — mapping model + resolveForEvent helper.
 *
 * Controller-level HTTP coverage deferred to M6.3+ tests; the lifecycle
 * + resolveForEvent semantics are what M6.3's observer depends on.
 */
final class ActivityCalendarMappingTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_mapping(): void
    {
        [$agencyId, $def] = $this->seedAgencyAndDef();

        $m = ActivityDefinitionCalendarClass::create([
            'agency_id'              => $agencyId,
            'event_class'            => 'viewing',
            'activity_definition_id' => $def->id,
            'value_per_event'        => 1,
            'requires_feedback'      => true,
            'auto_revoke_after_hours'=> 24,
            'daily_cap'              => 5,
            'back_date_limit_hours'  => 48,
            'is_active'              => true,
        ]);
        $this->assertNotNull($m->id);
        $this->assertTrue($m->fresh()->is_active);
    }

    public function test_resolve_for_event_returns_active_mapping(): void
    {
        [$agencyId, $def] = $this->seedAgencyAndDef();
        ActivityDefinitionCalendarClass::create([
            'agency_id' => $agencyId,
            'event_class' => 'viewing',
            'activity_definition_id' => $def->id,
            'value_per_event' => 1,
            'is_active' => true,
        ]);

        // Fake CalendarEvent-shape with only the columns resolveForEvent reads.
        $event = new \App\Models\CommandCenter\CalendarEvent();
        $event->agency_id = $agencyId;
        $event->category  = 'viewing';

        $resolved = ActivityDefinitionCalendarClass::resolveForEvent($event);
        $this->assertNotNull($resolved);
        $this->assertSame($def->id, $resolved->activity_definition_id);
    }

    public function test_resolve_for_event_returns_null_when_inactive(): void
    {
        [$agencyId, $def] = $this->seedAgencyAndDef();
        ActivityDefinitionCalendarClass::create([
            'agency_id' => $agencyId,
            'event_class' => 'viewing',
            'activity_definition_id' => $def->id,
            'value_per_event' => 1,
            'is_active' => false,
        ]);

        $event = new \App\Models\CommandCenter\CalendarEvent();
        $event->agency_id = $agencyId;
        $event->category  = 'viewing';

        $this->assertNull(ActivityDefinitionCalendarClass::resolveForEvent($event));
    }

    public function test_resolve_for_event_returns_null_when_no_mapping(): void
    {
        $agencyId = $this->makeAgency();
        $event = new \App\Models\CommandCenter\CalendarEvent();
        $event->agency_id = $agencyId;
        $event->category  = 'meeting';
        $this->assertNull(ActivityDefinitionCalendarClass::resolveForEvent($event));
    }

    public function test_soft_delete_excludes_from_resolve(): void
    {
        [$agencyId, $def] = $this->seedAgencyAndDef();
        $m = ActivityDefinitionCalendarClass::create([
            'agency_id' => $agencyId,
            'event_class' => 'viewing',
            'activity_definition_id' => $def->id,
            'value_per_event' => 1,
            'is_active' => true,
        ]);
        $m->delete();

        $event = new \App\Models\CommandCenter\CalendarEvent();
        $event->agency_id = $agencyId;
        $event->category  = 'viewing';

        $this->assertNull(ActivityDefinitionCalendarClass::resolveForEvent($event));
    }

    public function test_mapping_is_per_agency(): void
    {
        [$agencyA, $defA] = $this->seedAgencyAndDef();
        $agencyB = $this->makeAgency();

        ActivityDefinitionCalendarClass::create([
            'agency_id' => $agencyA,
            'event_class' => 'viewing',
            'activity_definition_id' => $defA->id,
            'value_per_event' => 1,
            'is_active' => true,
        ]);

        // Same event_class, different agency → no resolve.
        $event = new \App\Models\CommandCenter\CalendarEvent();
        $event->agency_id = $agencyB;
        $event->category  = 'viewing';
        $this->assertNull(ActivityDefinitionCalendarClass::resolveForEvent($event));
    }

    // ── Helpers ──

    /** @return array{0:int,1:ActivityDefinition} */
    private function seedAgencyAndDef(): array
    {
        $agencyId = $this->makeAgency();
        $def = ActivityDefinition::create([
            'name'       => 'Test ' . Str::random(5),
            'weight'     => 10,
            'sort_order' => 1,
            'scope'      => ActivityDefinition::SCOPE_SYSTEM,
            'is_enabled' => true,
        ]);
        return [$agencyId, $def];
    }

    private function makeAgency(): int
    {
        $id = (int) DB::table('agencies')->insertGetId([
            'name' => 'Test ' . Str::random(6),
            'slug' => 'test-' . Str::random(8),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('branches')->insert([
            'id' => $id, 'agency_id' => $id, 'name' => 'Default',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return $id;
    }
}
