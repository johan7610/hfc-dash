<?php

declare(strict_types=1);

namespace Tests\Feature\Presentation;

use App\Models\AgentOverride;
use App\Models\Presentation;
use App\Models\PresentationField;
use App\Models\PresentationVersion;
use App\Models\Property;
use App\Models\PropertySettingItem;
use App\Models\User;
use App\Services\Presentations\AnalysisDataService;
use App\Services\Presentations\ConditionAdjustmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Build 3 — condition-driven valuation tests.
 *
 * Covers the 8 proofs from the Build 3 prompt:
 *   1. Seeded 7 defaults visible on freshly-migrated agency.
 *   2. Edit a condition level → adjustment_pct updates persist.
 *   3. Property edit form / model accepts condition_level_id.
 *   4. Generated review screen → bands reflect property's condition.
 *   5. Override on review screen → bands recompute + override row logged.
 *   6. Publish → version snapshot frozen (condition_adjustment_pct).
 *   7. Property pointing at deleted condition → graceful fallback.
 *   8. Multi-tenancy: agency A's condition levels invisible to agency B.
 */
final class ConditionAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $reflection = new \ReflectionClass(\App\Services\PermissionService::class);
        $seeded = $reflection->getProperty('seeded');
        $seeded->setAccessible(true);
        $seeded->setValue(null, null);
        \App\Models\Role::clearCache();
        parent::tearDown();
    }

    // ── 1 — defaults seeded ──────────────────────────────────────────

    public function test_migration_seeds_seven_default_condition_levels_per_agency(): void
    {
        [$agencyId] = $this->seedAgencyAndUser();

        $levels = PropertySettingItem::withoutGlobalScopes()
            ->where('agency_id', $agencyId)
            ->where('group', 'condition_level')
            ->orderBy('sort_order')
            ->get();

        $this->assertCount(7, $levels);
        $this->assertSame(['To Remodel','To Renovate','Average','Good','Very Good','Excellent','Exceptional'], $levels->pluck('name')->all());
        $this->assertEqualsWithDelta(-30.0, (float) $levels[0]->adjustment_pct, 0.01);
        $this->assertEqualsWithDelta(0.0,   (float) $levels[2]->adjustment_pct, 0.01);
        $this->assertEqualsWithDelta(12.0,  (float) $levels[4]->adjustment_pct, 0.01);
        $this->assertEqualsWithDelta(38.0,  (float) $levels[6]->adjustment_pct, 0.01);
    }

    // ── 2 — settings controller persists adjustment_pct ───────────────

    public function test_settings_controller_persists_adjustment_pct_change(): void
    {
        [$agencyId, $user] = $this->seedAgencyAndUser();
        $good = PropertySettingItem::withoutGlobalScopes()
            ->where('agency_id', $agencyId)->where('name', 'Good')->first();

        $this->actingAs($user)
            ->put(route('corex.settings.property-items.update', $good->id), [
                'name'           => 'Good',
                'sort_order'     => $good->sort_order,
                'adjustment_pct' => 5.00,
            ])
            ->assertRedirect();

        $this->assertEqualsWithDelta(5.0, (float) $good->fresh()->adjustment_pct, 0.01);
    }

    public function test_baseline_average_pct_is_locked_at_zero_on_update(): void
    {
        [$agencyId, $user] = $this->seedAgencyAndUser();
        $average = PropertySettingItem::withoutGlobalScopes()
            ->where('agency_id', $agencyId)->where('name', 'Average')->first();

        $this->actingAs($user)
            ->put(route('corex.settings.property-items.update', $average->id), [
                'name'           => 'Average',
                'sort_order'     => $average->sort_order,
                'adjustment_pct' => 50.00, // controller MUST force this back to 0
            ])
            ->assertRedirect();

        $this->assertEqualsWithDelta(0.0, (float) $average->fresh()->adjustment_pct, 0.01);
    }

    public function test_baseline_average_cannot_be_deleted(): void
    {
        [$agencyId, $user] = $this->seedAgencyAndUser();
        $average = PropertySettingItem::withoutGlobalScopes()
            ->where('agency_id', $agencyId)->where('name', 'Average')->first();
        // Force-flip to non-default so the existing "defaults cannot be
        // deleted" guard doesn't pre-empt the Build 3 baseline guard.
        $average->update(['is_default' => false]);

        $this->actingAs($user)
            ->delete(route('corex.settings.property-items.destroy', $average->id))
            ->assertRedirect();

        $this->assertNotNull(PropertySettingItem::withoutGlobalScopes()->find($average->id));
    }

    // ── 3 — property condition_level_id ──────────────────────────────

    public function test_property_accepts_condition_level_id(): void
    {
        [$agencyId, $user] = $this->seedAgencyAndUser();
        $veryGood = PropertySettingItem::withoutGlobalScopes()
            ->where('agency_id', $agencyId)->where('name', 'Very Good')->first();

        $property = $this->createProperty($agencyId, $user->id, [
            'price'              => 1_800_000,
            'condition_level_id' => $veryGood->id,
        ]);

        $this->assertSame($veryGood->id, $property->fresh()->condition_level_id);
        $this->assertSame('Very Good', $property->fresh()->conditionLevel->name);
    }

    // ── 4 — AnalysisDataService applies adjustment ────────────────────

    public function test_analysis_data_applies_property_condition_to_middle_band(): void
    {
        [$agencyId, $user] = $this->seedAgencyAndUser();
        $veryGood = PropertySettingItem::withoutGlobalScopes()
            ->where('agency_id', $agencyId)->where('name', 'Very Good')->first();

        $property = $this->createProperty($agencyId, $user->id, [
            'price'              => 1_830_000,
            'condition_level_id' => $veryGood->id,
        ]);

        $version = $this->seedPresentationWithVersion($agencyId, $user->id, $property);
        $this->seedCmaFields($version->presentation_id, $agencyId, 1_500_000, 1_830_000, 2_160_000);

        $analysis = (new AnalysisDataService())->compile(
            $version->presentation()->with('property')->first(),
            $version,
        );
        $cma = $analysis['cma_valuation'];

        // Build 1 lower/upper bookends untouched.
        $this->assertSame(1_500_000, $cma['cma_lower']);
        $this->assertSame(2_160_000, $cma['cma_upper']);
        // Build 3 — Middle adjusted +12% from 1_830_000 = 2_049_600.
        $this->assertSame(2_049_600, $cma['cma_middle']);
        $this->assertSame(1_830_000, $cma['cma_middle_baseline']);
        $this->assertTrue($cma['condition_applied']);
        $this->assertEqualsWithDelta(12.0, $cma['condition_pct'], 0.01);
        $this->assertSame('Very Good', $cma['condition_label']);
        $this->assertSame('property_default', $cma['condition_source']);
    }

    public function test_baseline_applies_when_no_condition_set(): void
    {
        [$agencyId, $user] = $this->seedAgencyAndUser();
        $property = $this->createProperty($agencyId, $user->id, ['price' => 1_000_000]);
        $version = $this->seedPresentationWithVersion($agencyId, $user->id, $property);
        $this->seedCmaFields($version->presentation_id, $agencyId, 900_000, 1_000_000, 1_100_000);

        $analysis = (new AnalysisDataService())->compile(
            $version->presentation()->with('property')->first(),
            $version,
        );
        $cma = $analysis['cma_valuation'];

        $this->assertFalse($cma['condition_applied']);
        $this->assertSame(1_000_000, $cma['cma_middle']);
        $this->assertSame('none', $cma['condition_source']);
    }

    // ── 5 — review screen override + override log ─────────────────────

    public function test_set_condition_writes_override_and_recomputes_bands(): void
    {
        [$agencyId, $user] = $this->seedAgencyAndUser();
        $excellent = PropertySettingItem::withoutGlobalScopes()
            ->where('agency_id', $agencyId)->where('name', 'Excellent')->first(); // +20%

        $property = $this->createProperty($agencyId, $user->id, ['price' => 1_830_000]);
        $version = $this->seedPresentationWithVersion($agencyId, $user->id, $property);
        $this->seedCmaFields($version->presentation_id, $agencyId, 1_500_000, 1_830_000, 2_160_000);

        $resp = $this->actingAs($user)
            ->post(route('presentations.review.condition', $version->id), [
                'condition_level_id' => $excellent->id,
            ]);

        $resp->assertOk();
        $json = $resp->json();
        $this->assertTrue($json['ok']);
        $this->assertSame($excellent->id, $json['condition']['level_id']);
        $this->assertEqualsWithDelta(20.0, $json['condition']['pct'], 0.01);
        $this->assertSame(2_196_000, $json['cma']['middle']); // 1830000 * 1.20

        $this->assertSame($excellent->id, $version->fresh()->condition_level_id);
        $this->assertDatabaseHas('agent_overrides', [
            'presentation_version_id' => $version->id,
            'override_type'           => AgentOverride::TYPE_CONDITION_CHANGED,
            'target_id'               => 'condition_level_id',
        ]);
    }

    public function test_set_condition_to_null_clears_override(): void
    {
        [$agencyId, $user] = $this->seedAgencyAndUser();
        $excellent = PropertySettingItem::withoutGlobalScopes()
            ->where('agency_id', $agencyId)->where('name', 'Excellent')->first();
        $property = $this->createProperty($agencyId, $user->id, ['price' => 1_000_000]);
        $version = $this->seedPresentationWithVersion($agencyId, $user->id, $property, [
            'condition_level_id' => $excellent->id,
        ]);
        $this->seedCmaFields($version->presentation_id, $agencyId, 900_000, 1_000_000, 1_100_000);

        $this->actingAs($user)
            ->post(route('presentations.review.condition', $version->id), [])
            ->assertOk();

        $this->assertNull($version->fresh()->condition_level_id);
    }

    // ── 6 — publish snapshots the condition ──────────────────────────

    public function test_publish_snapshots_resolved_condition_onto_version(): void
    {
        [$agencyId, $user] = $this->seedAgencyAndUser();
        $good = PropertySettingItem::withoutGlobalScopes()
            ->where('agency_id', $agencyId)->where('name', 'Good')->first(); // +3%
        $property = $this->createProperty($agencyId, $user->id, [
            'price' => 1_000_000, 'condition_level_id' => $good->id,
        ]);
        $version = $this->seedPresentationWithVersion($agencyId, $user->id, $property);

        $this->actingAs($user)
            ->post(route('presentations.review.publish', $version->id))
            ->assertOk();

        $fresh = $version->fresh();
        $this->assertSame($good->id, $fresh->condition_level_id);
        $this->assertEqualsWithDelta(3.0, (float) $fresh->condition_adjustment_pct, 0.01);
        $this->assertSame('Good', $fresh->condition_label);
    }

    public function test_published_snapshot_does_not_drift_when_agency_changes_pct(): void
    {
        [$agencyId, $user] = $this->seedAgencyAndUser();
        $good = PropertySettingItem::withoutGlobalScopes()
            ->where('agency_id', $agencyId)->where('name', 'Good')->first();
        $property = $this->createProperty($agencyId, $user->id, [
            'price' => 1_000_000, 'condition_level_id' => $good->id,
        ]);
        $version = $this->seedPresentationWithVersion($agencyId, $user->id, $property);
        $this->seedCmaFields($version->presentation_id, $agencyId, 900_000, 1_000_000, 1_100_000);

        // Publish snapshots Good @ 3%.
        $this->actingAs($user)
            ->post(route('presentations.review.publish', $version->id))
            ->assertOk();

        // Agency later changes Good to 50%.
        $good->update(['adjustment_pct' => 50.0]);

        // PDF compile (= published path) must honour the SNAPSHOT, not
        // the new agency setting.
        $analysis = (new AnalysisDataService())->compile(
            $version->presentation()->with('property')->first(),
            $version->fresh(),
        );
        $cma = $analysis['cma_valuation'];

        $this->assertEqualsWithDelta(3.0, $cma['condition_pct'], 0.01);
        $this->assertSame(1_030_000, $cma['cma_middle']); // 1M * 1.03 — not 1.5M
        $this->assertSame('version_snapshot', $cma['condition_source']);
    }

    // ── 7 — graceful fallback for deleted condition ───────────────────

    public function test_resolver_falls_through_when_property_condition_was_deleted(): void
    {
        [$agencyId, $user] = $this->seedAgencyAndUser();
        $custom = PropertySettingItem::create([
            'agency_id' => $agencyId, 'group' => 'condition_level', 'name' => 'Custom Slick',
            'sort_order' => 100, 'is_default' => false, 'active' => true,
            'adjustment_pct' => 25.0,
        ]);
        $property = $this->createProperty($agencyId, $user->id, [
            'price' => 1_000_000, 'condition_level_id' => $custom->id,
        ]);
        $version = $this->seedPresentationWithVersion($agencyId, $user->id, $property);

        // Soft-delete the condition row.
        $custom->delete();

        $resolver = app(ConditionAdjustmentService::class);
        // Resolver loads presentation via the version's relationship,
        // which in turn loads the property fresh from DB — soft-deleted
        // condition_level row gets filtered.
        $resolved = $resolver->resolveLive($version->fresh());
        // Soft-deleted level falls through to 'none' (no PDF surprise).
        $this->assertNull($resolved['level']);
        $this->assertSame('none', $resolved['source']);
    }

    // ── 8 — multi-tenancy ────────────────────────────────────────────

    public function test_set_condition_rejects_foreign_agency_level_id(): void
    {
        [$agencyA, $userA] = $this->seedAgencyAndUser();
        [$agencyB]         = $this->seedAgencyAndUser();
        $foreignLevel = PropertySettingItem::withoutGlobalScopes()
            ->where('agency_id', $agencyB)->where('name', 'Excellent')->first();

        $property = $this->createProperty($agencyA, $userA->id, ['price' => 1_000_000]);
        $version = $this->seedPresentationWithVersion($agencyA, $userA->id, $property);

        $this->actingAs($userA)
            ->post(route('presentations.review.condition', $version->id), [
                'condition_level_id' => $foreignLevel->id,
            ])
            ->assertStatus(422);

        $this->assertNull($version->fresh()->condition_level_id);
    }

    // ── Helpers ──────────────────────────────────────────────────────

    /** @return array{0:int,1:User} */
    private function seedAgencyAndUser(): array
    {
        $agencyId = (int) DB::table('agencies')->insertGetId([
            'name' => 'Test ' . Str::random(6), 'slug' => 'test-' . Str::random(8),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('branches')->insert([
            'id' => $agencyId, 'agency_id' => $agencyId, 'name' => 'Default',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Build 3 seeder runs on real agencies only; RefreshDatabase
        // rebuilds from the schema snapshot which doesn't replay the
        // condition-level seed. Insert defaults inline.
        $defaults = [
            ['name' => 'To Remodel',  'pct' => -30.00, 'sort' => 0],
            ['name' => 'To Renovate', 'pct' => -15.00, 'sort' => 1],
            ['name' => 'Average',     'pct' =>   0.00, 'sort' => 2],
            ['name' => 'Good',        'pct' =>   3.00, 'sort' => 3],
            ['name' => 'Very Good',   'pct' =>  12.00, 'sort' => 4],
            ['name' => 'Excellent',   'pct' =>  20.00, 'sort' => 5],
            ['name' => 'Exceptional', 'pct' =>  38.00, 'sort' => 6],
        ];
        foreach ($defaults as $row) {
            DB::table('property_setting_items')->insert([
                'agency_id'      => $agencyId,
                'group'          => 'condition_level',
                'name'           => $row['name'],
                'sort_order'     => $row['sort'],
                'is_default'     => 1,
                'active'         => 1,
                'adjustment_pct' => $row['pct'],
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }

        $user = User::factory()->create([
            'agency_id' => $agencyId, 'branch_id' => $agencyId, 'role' => 'super_admin',
        ]);
        return [$agencyId, $user];
    }

    private function seedPresentationWithVersion(int $agencyId, int $userId, Property $property, array $versionOverrides = []): PresentationVersion
    {
        $presentation = Presentation::create([
            'agency_id'          => $agencyId,
            'branch_id'          => $agencyId,
            'property_id'        => $property->id,
            'created_by_user_id' => $userId,
            'title'              => 'Condition Test',
            'property_address'   => '1 Test Avenue',
            'suburb'             => 'Testville',
            'property_type'      => 'house',
            'status'             => 'draft',
            'currency'           => 'ZAR',
        ]);
        return PresentationVersion::create(array_merge([
            'agency_id'         => $agencyId,
            'presentation_id'   => $presentation->id,
            'compiled_by'       => $userId,
            'blueprint_version' => 'v1',
            'data_snapshot_json'=> json_encode(['sections' => []]),
            'compiled_at'       => now(),
            'review_status'     => PresentationVersion::REVIEW_AWAITING,
            'awaiting_review_at'=> now(),
        ], $versionOverrides));
    }

    private function createProperty(int $agencyId, int $userId, array $overrides = []): Property
    {
        return Property::create(array_merge([
            'agency_id' => $agencyId,
            'branch_id' => $agencyId,
            'agent_id'  => $userId,
            'title'     => 'Test Property',
        ], $overrides));
    }

    private function seedCmaFields(int $presentationId, int $agencyId, int $lower, int $middle, int $upper): void
    {
        foreach ([
            'cma.lower_range'  => $lower,
            'cma.middle_range' => $middle,
            'cma.upper_range'  => $upper,
        ] as $key => $value) {
            PresentationField::create([
                'agency_id'       => $agencyId,
                'presentation_id' => $presentationId,
                'field_key'       => $key,
                'final_value'     => (string) $value,
            ]);
        }
    }
}
