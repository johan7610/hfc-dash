<?php

declare(strict_types=1);

namespace Tests\Feature\ActivityPoints;

use App\Models\ActivityDefinition;
use App\Models\DailyActivityEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Module 6 (M6.1) — schema + model invariants.
 */
final class ActivityDefinitionScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_scope_creation_round_trip(): void
    {
        // RefreshDatabase doesn't run the production definitions seeder,
        // so we exercise the round-trip directly: create a system-scoped
        // definition, confirm scope + null agency_id persist correctly.
        $def = ActivityDefinition::create([
            'name'       => 'System def round-trip',
            'weight'     => 10,
            'sort_order' => 999,
            'scope'      => ActivityDefinition::SCOPE_SYSTEM,
            'agency_id'  => null,
            'is_enabled' => true,
        ]);
        $this->assertSame('system', $def->fresh()->scope);
        $this->assertNull($def->fresh()->agency_id);
        $this->assertTrue($def->isSystem());
    }

    public function test_can_create_agency_scoped_definition(): void
    {
        $agencyId = $this->makeAgency();
        $def = ActivityDefinition::create([
            'name'         => 'Agency-private activity',
            'weight'       => 25,
            'sort_order'   => 999,
            'scope'        => ActivityDefinition::SCOPE_AGENCY,
            'agency_id'    => $agencyId,
            'is_enabled'   => true,
        ]);

        $this->assertTrue($def->isAgencyScoped());
        $this->assertSame($agencyId, $def->agency_id);
    }

    public function test_cannot_create_system_definition_with_agency_id(): void
    {
        $agencyId = $this->makeAgency();
        $this->expectException(\DomainException::class);
        ActivityDefinition::create([
            'name'         => 'Bad system',
            'weight'       => 1,
            'sort_order'   => 1,
            'scope'        => ActivityDefinition::SCOPE_SYSTEM,
            'agency_id'    => $agencyId,
            'is_enabled'   => true,
        ]);
    }

    public function test_cannot_create_agency_definition_without_agency_id(): void
    {
        $this->expectException(\DomainException::class);
        ActivityDefinition::create([
            'name'         => 'Bad agency',
            'weight'       => 1,
            'sort_order'   => 1,
            'scope'        => ActivityDefinition::SCOPE_AGENCY,
            'agency_id'    => null,
            'is_enabled'   => true,
        ]);
    }

    public function test_available_to_scope_returns_system_plus_own_agency_only(): void
    {
        $agencyA = $this->makeAgency();
        $agencyB = $this->makeAgency();

        // Seed a system definition + one agency-private per agency so we
        // can assert the per-agency cross-leak invariant deterministically
        // without depending on the production definitions seeder.
        $this->seedSystemDefinition();

        ActivityDefinition::create([
            'name'      => 'A private',  'weight' => 1, 'sort_order' => 1,
            'scope'     => 'agency',     'agency_id' => $agencyA, 'is_enabled' => true,
        ]);
        ActivityDefinition::create([
            'name'      => 'B private',  'weight' => 1, 'sort_order' => 2,
            'scope'     => 'agency',     'agency_id' => $agencyB, 'is_enabled' => true,
        ]);

        // 1 system + 1 own private = 2 each. Cross-agency private must NOT leak.
        $this->assertSame(2, ActivityDefinition::query()->availableTo($agencyA)->count(),
            'agency A sees system + its own private only');
        $this->assertSame(2, ActivityDefinition::query()->availableTo($agencyB)->count());
        $aRows = ActivityDefinition::query()->availableTo($agencyA)->pluck('name')->all();
        $this->assertNotContains('B private', $aRows);
    }

    public function test_new_entries_default_to_confirmed_manual(): void
    {
        // The migration's column defaults are confirmed/manual so any row
        // created without explicit point_state/source picks up the right
        // values automatically — i.e. existing manual capture paths stay
        // semantically correct without code changes downstream.
        $agencyId = $this->makeAgency();
        $user = \App\Models\User::factory()->create([
            'agency_id' => $agencyId, 'branch_id' => $agencyId, 'role' => 'agent',
        ]);
        $def = $this->seedSystemDefinition();
        $row = DailyActivityEntry::create([
            'activity_date'          => now()->toDateString(),
            'period'                 => now()->format('Y-m'),
            'user_id'                => $user->id,
            'branch_id'              => $agencyId,
            'activity_definition_id' => $def->id,
            'value'                  => 1,
            'created_by'             => $user->id,
            'updated_by'             => $user->id,
        ]);
        // Column defaults from migration kick in.
        $this->assertSame('confirmed', $row->fresh()->point_state);
        $this->assertSame('manual',    $row->fresh()->source);
    }

    public function test_counted_toward_total_scope_excludes_provisional_and_revoked(): void
    {
        $agencyId = $this->makeAgency();
        $user = \App\Models\User::factory()->create([
            'agency_id' => $agencyId, 'branch_id' => $agencyId, 'role' => 'agent',
        ]);
        $def = $this->seedSystemDefinition();

        // Vary activity_date to satisfy any (user, date, definition) uniqueness
        // index on the legacy table — we only care about the state filtering.
        $states = ['confirmed', 'provisional', 'revoked', 'overridden'];
        foreach ($states as $i => $state) {
            DailyActivityEntry::create([
                'activity_date'          => now()->subDays($i)->toDateString(),
                'period'                 => now()->subDays($i)->format('Y-m'),
                'user_id'                => $user->id,
                'branch_id'              => $agencyId,
                'activity_definition_id' => $def->id,
                'value'                  => 1,
                'point_state'            => $state,
                'source'                 => 'manual',
                'created_by'             => $user->id,
                'updated_by'             => $user->id,
            ]);
        }

        $counted = DailyActivityEntry::query()
            ->where('user_id', $user->id)
            ->countedTowardTotal()
            ->count();
        $this->assertSame(2, $counted, 'confirmed + overridden count; provisional + revoked do not');
    }

    private function seedSystemDefinition(): ActivityDefinition
    {
        return ActivityDefinition::create([
            'name'       => 'Test Activity ' . Str::random(6),
            'weight'     => 10,
            'sort_order' => 1,
            'scope'      => ActivityDefinition::SCOPE_SYSTEM,
            'is_enabled' => true,
        ]);
    }

    private function makeAgency(): int
    {
        $agencyId = (int) DB::table('agencies')->insertGetId([
            'name' => 'Test ' . Str::random(6),
            'slug' => 'test-' . Str::random(8),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('branches')->insert([
            'id' => $agencyId, 'agency_id' => $agencyId, 'name' => 'Default',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return $agencyId;
    }
}
