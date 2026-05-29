<?php

declare(strict_types=1);

namespace Tests\Feature\Presentation;

use App\Models\AgentOverride;
use App\Models\Presentation;
use App\Models\PresentationVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Build 4 — report-tab section toggles.
 *
 * Covers the 8 proofs from the build prompt:
 *   1. Agency defaults seeded with all sections ON; migration adds 9
 *      presentations_default_show_* columns.
 *   2. Flipping an agency default propagates to NEW versions at compile.
 *   3. toggleSection endpoint persists + logs + recomputes page estimate.
 *   4. Dependency cascade: turning CMA off forces Pricing Strategy off
 *      and vice versa (turning Pricing Strategy on re-enables CMA).
 *   5. Floor sections coerce to ON regardless of POST payload.
 *   6. Snapshot persistence — version's enabled_sections_json is frozen,
 *      doesn't react to later agency-default changes.
 *   7. Idempotent no-op on same-intent toggles.
 *   8. agent_overrides log captures triggering + cascaded toggles.
 */
final class SectionTogglesTest extends TestCase
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

    // ── 1 — schema + agency defaults seed ────────────────────────────

    public function test_migration_adds_default_show_columns_to_agencies(): void
    {
        [$agencyId] = $this->seedAgencyAndUser();
        $agency = \App\Models\Agency::find($agencyId);
        foreach (array_keys(PresentationVersion::SECTIONS_CATALOGUE) as $key) {
            $col = 'presentations_default_show_' . $key;
            $this->assertTrue(
                array_key_exists($col, $agency->getAttributes()),
                "agencies must have column {$col}",
            );
            $this->assertTrue((bool) $agency->{$col}, "{$col} must default to true");
        }
    }

    public function test_section_defaults_map_returns_all_sections(): void
    {
        [$agencyId] = $this->seedAgencyAndUser();
        $agency = \App\Models\Agency::find($agencyId);
        $defaults = $agency->sectionDefaults();
        foreach (array_keys(PresentationVersion::SECTIONS_CATALOGUE) as $key) {
            $this->assertArrayHasKey($key, $defaults);
            $this->assertTrue($defaults[$key]);
        }
    }

    // ── 2 — isSectionEnabled honours snapshot + floor ────────────────

    public function test_is_section_enabled_returns_true_for_floor_regardless_of_snapshot(): void
    {
        [$agencyId, $user] = $this->seedAgencyAndUser();
        $version = $this->seedVersion($agencyId, $user->id, [
            'enabled_sections_json' => [
                'executive_summary' => false, // attempted to disable floor
                'market_overview'   => false,
                'cma_analysis'      => true,
            ],
        ]);
        $this->assertTrue($version->isSectionEnabled('executive_summary'));
        $this->assertFalse($version->isSectionEnabled('market_overview'));
        $this->assertTrue($version->isSectionEnabled('cma_analysis'));
    }

    public function test_missing_section_key_defaults_to_enabled(): void
    {
        [$agencyId, $user] = $this->seedAgencyAndUser();
        $version = $this->seedVersion($agencyId, $user->id, [
            'enabled_sections_json' => ['cma_analysis' => true],
        ]);
        // Key not present → legacy default = ON.
        $this->assertTrue($version->isSectionEnabled('holding_cost'));
    }

    // ── 3 — toggleSection endpoint ───────────────────────────────────

    public function test_toggle_section_off_persists_and_logs_override(): void
    {
        [$agencyId, $user] = $this->seedAgencyAndUser();
        $version = $this->seedVersion($agencyId, $user->id);

        $resp = $this->actingAs($user)
            ->post(route('presentations.review.sections', $version->id), [
                'section_key' => 'holding_cost',
                'enabled'     => '0',
            ]);

        $resp->assertOk();
        $this->assertFalse($version->fresh()->isSectionEnabled('holding_cost'));

        $this->assertDatabaseHas('agent_overrides', [
            'presentation_version_id' => $version->id,
            'override_type'           => AgentOverride::TYPE_SECTION_TOGGLED,
            'target_id'               => 'holding_cost',
        ]);
    }

    public function test_toggle_section_returns_page_estimate(): void
    {
        [$agencyId, $user] = $this->seedAgencyAndUser();
        $version = $this->seedVersion($agencyId, $user->id);

        $resp = $this->actingAs($user)->post(
            route('presentations.review.sections', $version->id),
            ['section_key' => 'holding_cost', 'enabled' => '0'],
        );

        $json = $resp->json();
        $this->assertArrayHasKey('page_estimate', $json);
        $this->assertGreaterThan(0, $json['page_estimate']);
    }

    public function test_toggle_section_idempotent_no_op_on_same_intent(): void
    {
        [$agencyId, $user] = $this->seedAgencyAndUser();
        $version = $this->seedVersion($agencyId, $user->id);

        $resp = $this->actingAs($user)->post(
            route('presentations.review.sections', $version->id),
            ['section_key' => 'holding_cost', 'enabled' => '1'], // already on
        );

        $resp->assertOk()->assertJson(['ok' => true, 'no_op' => true]);
        $this->assertSame(0, AgentOverride::where('presentation_version_id', $version->id)
            ->where('override_type', AgentOverride::TYPE_SECTION_TOGGLED)
            ->count());
    }

    // ── 4 — dependency cascade ───────────────────────────────────────

    public function test_disabling_cma_cascades_pricing_strategy_off(): void
    {
        [$agencyId, $user] = $this->seedAgencyAndUser();
        $version = $this->seedVersion($agencyId, $user->id);

        $resp = $this->actingAs($user)->post(
            route('presentations.review.sections', $version->id),
            ['section_key' => 'cma_analysis', 'enabled' => '0'],
        );

        $resp->assertOk();
        $json = $resp->json();
        $this->assertArrayHasKey('cascaded', $json);
        $this->assertArrayHasKey('pricing_strategy', $json['cascaded']);
        $this->assertFalse($json['cascaded']['pricing_strategy']);

        $fresh = $version->fresh();
        $this->assertFalse($fresh->isSectionEnabled('cma_analysis'));
        $this->assertFalse($fresh->isSectionEnabled('pricing_strategy'));

        // Both override rows present — triggering + cascade.
        $this->assertSame(2, AgentOverride::where('presentation_version_id', $version->id)
            ->where('override_type', AgentOverride::TYPE_SECTION_TOGGLED)
            ->count());
    }

    public function test_enabling_pricing_strategy_re_enables_cma(): void
    {
        [$agencyId, $user] = $this->seedAgencyAndUser();
        $version = $this->seedVersion($agencyId, $user->id, [
            'enabled_sections_json' => [
                'cma_analysis'      => false,
                'pricing_strategy'  => false,
            ],
        ]);

        $resp = $this->actingAs($user)->post(
            route('presentations.review.sections', $version->id),
            ['section_key' => 'pricing_strategy', 'enabled' => '1'],
        );

        $resp->assertOk();
        $fresh = $version->fresh();
        $this->assertTrue($fresh->isSectionEnabled('pricing_strategy'));
        $this->assertTrue($fresh->isSectionEnabled('cma_analysis'));
    }

    // ── 5 — floor coercion ───────────────────────────────────────────

    public function test_floor_section_coerces_to_on_even_when_request_disables(): void
    {
        [$agencyId, $user] = $this->seedAgencyAndUser();
        $version = $this->seedVersion($agencyId, $user->id);

        $resp = $this->actingAs($user)->post(
            route('presentations.review.sections', $version->id),
            ['section_key' => 'executive_summary', 'enabled' => '0'],
        );

        $resp->assertOk()->assertJson(['ok' => true, 'no_op' => true, 'reason' => 'floor_section']);
        $this->assertTrue($version->fresh()->isSectionEnabled('executive_summary'));
    }

    // ── 6 — snapshot persistence ─────────────────────────────────────

    public function test_version_snapshot_does_not_drift_when_agency_default_changes(): void
    {
        [$agencyId, $user] = $this->seedAgencyAndUser();
        $version = $this->seedVersion($agencyId, $user->id, [
            'enabled_sections_json' => array_fill_keys(
                array_keys(PresentationVersion::SECTIONS_CATALOGUE),
                true,
            ),
        ]);

        // Agency later flips holding_cost OFF as default.
        \App\Models\Agency::where('id', $agencyId)
            ->update(['presentations_default_show_holding_cost' => false]);

        // Existing version stays as-is — snapshot is the source of truth.
        $this->assertTrue($version->fresh()->isSectionEnabled('holding_cost'));
    }

    // ── 7 — settings UI defaults update ──────────────────────────────

    public function test_settings_endpoint_persists_agency_default_changes(): void
    {
        [$agencyId, $user] = $this->seedAgencyAndUser();

        $this->actingAs($user)->post(
            route('corex.settings.presentations.sections.update'),
            [
                // POST sends "0" hidden, "1" if checked. Mimic a user
                // unchecking inflow_absorption.
                'presentations_default_show_inflow_absorption' => 0,
                'presentations_default_show_holding_cost'      => 1,
            ],
        )->assertRedirect();

        $agency = \App\Models\Agency::find($agencyId);
        $this->assertFalse((bool) $agency->presentations_default_show_inflow_absorption);
        $this->assertTrue((bool) $agency->presentations_default_show_holding_cost);
        // Floor coerces on regardless.
        $this->assertTrue((bool) $agency->presentations_default_show_executive_summary);
    }

    // ── 8 — auth + multi-tenancy ─────────────────────────────────────

    public function test_toggle_section_blocked_for_cross_agency_user(): void
    {
        // BelongsToAgency global scope filters the version out for userB
        // before route-model-binding reaches the controller — so 404 is
        // the information-hiding default. Matches Build 2's gate posture.
        [$agencyA, $userA] = $this->seedAgencyAndUser();
        [, $userB]         = $this->seedAgencyAndUser();
        $version = $this->seedVersion($agencyA, $userA->id);

        $this->actingAs($userB)->post(
            route('presentations.review.sections', $version->id),
            ['section_key' => 'holding_cost', 'enabled' => '0'],
        )->assertNotFound();
    }

    public function test_estimated_page_count_drops_when_sections_turned_off(): void
    {
        [$agencyId, $user] = $this->seedAgencyAndUser();
        $version = $this->seedVersion($agencyId, $user->id);
        $allOn = $version->estimatedPageCount();

        $version->applySectionToggle('market_overview', false);
        $version->applySectionToggle('holding_cost',    false);
        $afterOff = $version->fresh()->estimatedPageCount();

        $this->assertLessThan($allOn, $afterOff);
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
        $user = User::factory()->create([
            'agency_id' => $agencyId, 'branch_id' => $agencyId, 'role' => 'super_admin',
        ]);
        return [$agencyId, $user];
    }

    private function seedVersion(int $agencyId, int $userId, array $overrides = []): PresentationVersion
    {
        $presentation = Presentation::create([
            'agency_id'          => $agencyId,
            'branch_id'          => $agencyId,
            'created_by_user_id' => $userId,
            'title'              => 'Section Toggle Test',
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
        ], $overrides));
    }
}
