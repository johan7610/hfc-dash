<?php

declare(strict_types=1);

namespace Tests\Feature\Presentation;

use App\Models\HoldingCostDataPoint;
use App\Models\Presentation;
use App\Models\Property;
use App\Models\User;
use App\Services\Presentations\HoldingCostEstimator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Holding Cost — Tier 0/1/2 priority chain.
 *
 * Replaces the pre-fix floor_area_m2 × R/m² levy formula. Pins:
 *   - Tier 0 wins when properties.levy / rates_taxes set.
 *   - Tier 1 (learned average from data points) wins when no Tier 0
 *     and ≥ MIN_N matching data points exist.
 *   - Tier 2 (agency default) wins when no Tier 0 / Tier 1.
 *   - Levy has NO Tier 2 fallback by design — returns null when
 *     neither Tier 0 nor Tier 1 produces a value (the pre-fix bug
 *     was the R/m² fallback; we deliberately don't replace it).
 *   - Title_type drives the component set (sectional vs freehold).
 */
final class HoldingCostTieringTest extends TestCase
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

    // ── Tier 0 — per-property captured value wins ──────────────────────

    public function test_tier0_levy_from_properties_levy_column(): void
    {
        [$pres, $agencyId, $prop] = $this->seedSectionalSubject(levy: 3_500);
        $estimator = app(HoldingCostEstimator::class);
        $result = $estimator->resolveOne('levy', $pres);
        $this->assertNotNull($result);
        $this->assertSame('tier0', $result['tier']);
        $this->assertSame(3_500, $result['value']);
    }

    public function test_tier0_levy_includes_special_levy(): void
    {
        [$pres, $agencyId, $prop] = $this->seedSectionalSubject(levy: 3_000);
        $prop->update(['special_levy' => 500]);
        $estimator = app(HoldingCostEstimator::class);
        $result = $estimator->resolveOne('levy', $pres);
        $this->assertSame(3_500, $result['value']);
        $this->assertSame('tier0', $result['tier']);
    }

    public function test_tier0_rates_from_properties_rates_taxes(): void
    {
        [$pres, $agencyId] = $this->seedFreeholdSubject(rates: 2_800);
        $estimator = app(HoldingCostEstimator::class);
        $result = $estimator->resolveOne('rates', $pres);
        $this->assertSame('tier0', $result['tier']);
        $this->assertSame(2_800, $result['value']);
    }

    // ── Tier 1 — learned average when ≥ MIN_N matching data points ─────

    public function test_tier1_levy_learns_per_scheme(): void
    {
        [$pres, $agencyId] = $this->seedSectionalSubject(levy: null, schemeName: 'Madeira Gardens');
        // Seed 3 levy data points for the same scheme — average should
        // win Tier 1 (TIER1_MIN_N = 3).
        foreach ([3_000, 3_400, 3_800] as $v) {
            $this->seedDataPoint($agencyId, 'levy', $v, ['scheme_name' => 'Madeira Gardens']);
        }
        $estimator = app(HoldingCostEstimator::class);
        $result = $estimator->resolveOne('levy', $pres);
        $this->assertNotNull($result);
        $this->assertSame('tier1', $result['tier']);
        $this->assertSame(3_400, $result['value']); // (3000+3400+3800)/3 = 3400
    }

    public function test_tier1_skips_below_min_n(): void
    {
        // Only 2 data points — below TIER1_MIN_N of 3 → falls through.
        [$pres, $agencyId] = $this->seedFreeholdSubject(rates: null, suburb: 'Testville', valueBand: '1_3M');
        $this->seedDataPoint($agencyId, 'rates', 2_500, [
            'suburb_normalised' => 'testville', 'property_value_band' => '1_3M',
        ]);
        $this->seedDataPoint($agencyId, 'rates', 2_700, [
            'suburb_normalised' => 'testville', 'property_value_band' => '1_3M',
        ]);

        $estimator = app(HoldingCostEstimator::class);
        $result = $estimator->resolveOne('rates', $pres);
        // Falls through to Tier 2 (rates per-million × asking).
        $this->assertSame('tier2', $result['tier']);
    }

    public function test_tier1_excluded_rows_dont_count(): void
    {
        [$pres, $agencyId] = $this->seedSectionalSubject(levy: null, schemeName: 'Seeskulp');
        // 3 rows total but 1 is excluded — n=2 effective, below min — fall through.
        $this->seedDataPoint($agencyId, 'levy', 3_000, ['scheme_name' => 'Seeskulp']);
        $this->seedDataPoint($agencyId, 'levy', 3_400, ['scheme_name' => 'Seeskulp']);
        $this->seedDataPoint($agencyId, 'levy', 8_000, ['scheme_name' => 'Seeskulp', 'is_excluded' => true]);
        $estimator = app(HoldingCostEstimator::class);
        $result = $estimator->resolveOne('levy', $pres);
        // Only 2 included → below TIER1_MIN_N → fall through. Levy has
        // no Tier 2, so result is null.
        $this->assertNull($result);
    }

    public function test_tier1_garden_learns_by_type_and_suburb(): void
    {
        [$pres, $agencyId] = $this->seedFreeholdSubject(
            rates: 9_999, suburb: 'Margate', valueBand: '3_5M', propertyType: 'House',
        );
        foreach ([1_000, 1_200, 1_400, 1_500] as $v) {
            $this->seedDataPoint($agencyId, 'garden', $v, [
                'suburb_normalised' => 'margate', 'property_type' => 'House',
            ]);
        }
        $estimator = app(HoldingCostEstimator::class);
        $result = $estimator->resolveOne('garden', $pres);
        $this->assertSame('tier1', $result['tier']);
        $this->assertSame(1_275, $result['value']); // (1000+1200+1400+1500)/4 = 1275
    }

    // ── Tier 2 — agency default fallback ──────────────────────────────

    public function test_tier2_utilities_flat_default(): void
    {
        [$pres, $agencyId] = $this->seedFreeholdSubject(rates: 9_999, suburb: 'Testville', valueBand: '1_3M');
        $estimator = app(HoldingCostEstimator::class);
        $result = $estimator->resolveOne('utilities', $pres);
        $this->assertSame('tier2', $result['tier']);
        $this->assertSame(1_200, $result['value']); // default
    }

    public function test_tier2_rates_multiplies_per_million_by_asking(): void
    {
        [$pres, $agencyId] = $this->seedFreeholdSubject(rates: null, suburb: 'Testville', valueBand: '1_3M');
        // asking_price_inc = 2_000_000 (from seed), default per-million = 800
        // → 800 × 2.0 = 1_600
        $estimator = app(HoldingCostEstimator::class);
        $result = $estimator->resolveOne('rates', $pres);
        $this->assertSame('tier2', $result['tier']);
        $this->assertSame(1_600, $result['value']);
    }

    public function test_tier2_levy_returns_null_by_design(): void
    {
        // Sectional subject with NO Tier 0 levy + NO Tier 1 data points →
        // levy must be null (the pre-fix R/m² fallback is deliberately
        // gone — the Seeskulp R30,000 bug source).
        [$pres, $agencyId] = $this->seedSectionalSubject(levy: null, schemeName: 'NewScheme');
        $estimator = app(HoldingCostEstimator::class);
        $result = $estimator->resolveOne('levy', $pres);
        $this->assertNull($result);
    }

    // ── Component set by title_type ───────────────────────────────────

    public function test_sectional_components_include_levy_not_garden(): void
    {
        $estimator = app(HoldingCostEstimator::class);
        $comps = $estimator->componentsFor('sectional_title');
        $this->assertContains('levy', $comps);
        $this->assertNotContains('garden', $comps);
        $this->assertNotContains('pool', $comps);
        $this->assertNotContains('security', $comps);
    }

    public function test_freehold_components_include_garden_pool_security_not_levy(): void
    {
        $estimator = app(HoldingCostEstimator::class);
        $comps = $estimator->componentsFor('full_title');
        $this->assertNotContains('levy', $comps);
        $this->assertContains('garden', $comps);
        $this->assertContains('pool', $comps);
        $this->assertContains('security', $comps);
    }

    public function test_estimate_and_persist_writes_to_columns(): void
    {
        [$pres, $agencyId, $prop] = $this->seedSectionalSubject(
            levy: 3_500, schemeName: 'Madeira Gardens',
        );
        $estimator = app(HoldingCostEstimator::class);
        $result = $estimator->estimateAndPersist($pres);

        $this->assertArrayHasKey('levy', $result['wrote']);
        $this->assertSame(3_500, $result['wrote']['levy']['value']);
        $this->assertSame('tier0', $result['wrote']['levy']['tier']);

        $fresh = $pres->fresh();
        $this->assertSame(3500.0, (float) $fresh->monthly_levies);
    }

    // ── Override endpoint ─────────────────────────────────────────────

    public function test_override_writes_to_column_and_data_point(): void
    {
        [$pres, $agencyId, $prop] = $this->seedSectionalSubject(levy: 3_500);
        $version = $this->seedVersion($pres);

        $admin = User::factory()->create(['agency_id' => $agencyId, 'role' => 'super_admin']);
        $this->actingAs($admin);

        $response = $this->postJson(
            route('presentations.review.holding-cost-component', ['version' => $version->id]),
            ['component' => 'levy', 'monthly_value_zar' => 4_200],
        );

        $response->assertOk();
        $this->assertSame(4_200, $response->json('value'));

        // Persisted to column.
        $this->assertSame(4200.0, (float) $pres->fresh()->monthly_levies);

        // Data point recorded.
        $dp = HoldingCostDataPoint::where('presentation_version_id', $version->id)
            ->where('component', 'levy')
            ->where('source', HoldingCostDataPoint::SOURCE_AGENT_OVERRIDE)
            ->first();
        $this->assertNotNull($dp);
        $this->assertSame(4_200, $dp->monthly_value_zar);
        $this->assertSame($admin->id, $dp->entered_by_user_id);
    }

    // ── Helpers ────────────────────────────────────────────────────────

    /** @return array{0:Presentation, 1:int, 2:Property} */
    private function seedSectionalSubject(?int $levy, ?string $schemeName = null): array
    {
        $agencyId = (int) DB::table('agencies')->insertGetId([
            'name' => 'HC ' . Str::random(4), 'slug' => 'hc-' . Str::random(6),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('branches')->insert([
            'id' => $agencyId, 'agency_id' => $agencyId, 'name' => 'Default',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $user = User::factory()->create([
            'agency_id' => $agencyId, 'branch_id' => $agencyId, 'role' => 'super_admin',
        ]);
        $property = Property::create([
            'agency_id'     => $agencyId, 'branch_id' => $agencyId, 'agent_id' => $user->id,
            'title'         => 'Subject', 'property_type' => 'Sectional Title',
            'category'      => 'Residential', 'suburb' => 'Testville',
            'suburb_normalised' => 'testville',
            'price'         => 1_500_000, 'address' => '1 Subject Way',
            'status'        => 'active', 'listing_type' => 'sale',
            'levy'          => $levy,
            'title_type'    => 'sectional_title',
        ]);
        // Optional scheme via tracked_property promotion bridge.
        if ($schemeName) {
            DB::table('tracked_properties')->insert([
                'agency_id' => $agencyId, 'external_id' => Str::uuid()->toString(),
                'complex_name' => $schemeName,
                'promoted_to_property_id' => $property->id,
                'status' => 'promoted',
                'first_seen_at' => now(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $pres = Presentation::create([
            'agency_id' => $agencyId, 'branch_id' => $agencyId, 'property_id' => $property->id,
            'created_by_user_id' => $user->id, 'title' => 'Test',
            'property_address' => '1 Subject Way', 'suburb' => 'Testville',
            'property_type' => 'sectional', 'asking_price_inc' => 1_500_000,
            'status' => 'draft', 'currency' => 'ZAR',
        ]);
        return [$pres, $agencyId, $property];
    }

    /** @return array{0:Presentation, 1:int, 2:Property} */
    private function seedFreeholdSubject(
        ?int $rates,
        string $suburb = 'Testville',
        string $valueBand = '1_3M',
        string $propertyType = 'House',
    ): array {
        $agencyId = (int) DB::table('agencies')->insertGetId([
            'name' => 'HC ' . Str::random(4), 'slug' => 'hc-' . Str::random(6),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('branches')->insert([
            'id' => $agencyId, 'agency_id' => $agencyId, 'name' => 'Default',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $user = User::factory()->create([
            'agency_id' => $agencyId, 'branch_id' => $agencyId, 'role' => 'super_admin',
        ]);
        $price = match ($valueBand) {
            '0_1M' => 750_000,
            '1_3M' => 2_000_000,
            '3_5M' => 4_000_000,
            default => 6_000_000,
        };
        $property = Property::create([
            'agency_id'     => $agencyId, 'branch_id' => $agencyId, 'agent_id' => $user->id,
            'title'         => 'Subject', 'property_type' => $propertyType,
            'category'      => 'Residential', 'suburb' => $suburb,
            'suburb_normalised' => strtolower($suburb),
            'price'         => $price, 'address' => '1 Subject Way',
            'status'        => 'active', 'listing_type' => 'sale',
            'rates_taxes'   => $rates,
            'title_type'    => 'full_title',
        ]);
        $pres = Presentation::create([
            'agency_id' => $agencyId, 'branch_id' => $agencyId, 'property_id' => $property->id,
            'created_by_user_id' => $user->id, 'title' => 'Test',
            'property_address' => '1 Subject Way', 'suburb' => $suburb,
            'property_type' => 'house', 'asking_price_inc' => $price,
            'status' => 'draft', 'currency' => 'ZAR',
        ]);
        return [$pres, $agencyId, $property];
    }

    private function seedDataPoint(int $agencyId, string $component, int $value, array $context): HoldingCostDataPoint
    {
        return HoldingCostDataPoint::create(array_merge([
            'agency_id'         => $agencyId,
            'component'         => $component,
            'monthly_value_zar' => $value,
            'source'            => HoldingCostDataPoint::SOURCE_MANUAL_CAPTURE,
        ], $context));
    }

    private function seedVersion(Presentation $pres): \App\Models\PresentationVersion
    {
        return \App\Models\PresentationVersion::create([
            'agency_id'          => $pres->agency_id,
            'presentation_id'    => $pres->id,
            'blueprint_version'  => 'test',
            'data_snapshot_json' => json_encode(['note' => 'hc-test']),
            'compiled_at'        => now(),
        ]);
    }

    // ── Stale-vs-override Tier 0 wins regression ─────────────────────
    //
    // The "frozen R30,000 levy" bug: pre-Tier-fix estimator wrote a
    // R/m² levy into presentations.monthly_levies. After the Tier fix
    // shipped, the stale value sat on the row forever — the gate
    // (then: `if (current !== null) skip`) treated it as an agent
    // override and never re-resolved from Tier 0. Captured property
    // values (property.levy / rates_taxes) must overwrite stale
    // auto-fill; a real agent override must survive Tier 0.

    public function test_tier0_overwrites_stale_monthly_levies_when_no_override(): void
    {
        [$pres, $agencyId, $prop] = $this->seedSectionalSubject(levy: 959);

        // Simulate the pre-fix bug: stale R30,000 frozen on the
        // presentation column, no agent_override audit row.
        $pres->monthly_levies = 30000;
        $pres->saveQuietly();
        $this->assertSame(30000.0, (float) $pres->fresh()->monthly_levies);

        $estimator = app(HoldingCostEstimator::class);
        $result = $estimator->estimateAndPersist($pres->fresh(['property']));

        // Tier 0 must have run + overwritten the stale value.
        $this->assertArrayHasKey('levy', $result['wrote']);
        $this->assertSame('tier0', $result['wrote']['levy']['tier']);
        $this->assertSame(959, $result['wrote']['levy']['value']);
        $this->assertSame(959.0, (float) $pres->fresh()->monthly_levies);
    }

    public function test_tier0_overwrites_stale_monthly_rates_when_no_override(): void
    {
        [$pres, $agencyId] = $this->seedFreeholdSubject(rates: 848);

        // Stale Tier 2 value frozen from a prior pass.
        $pres->monthly_rates = 960;
        $pres->saveQuietly();

        $estimator = app(HoldingCostEstimator::class);
        $result = $estimator->estimateAndPersist($pres->fresh(['property']));

        $this->assertArrayHasKey('rates', $result['wrote']);
        $this->assertSame('tier0', $result['wrote']['rates']['tier']);
        $this->assertSame(848, $result['wrote']['rates']['value']);
        $this->assertSame(848.0, (float) $pres->fresh()->monthly_rates);
    }

    public function test_genuine_agent_override_survives_tier0(): void
    {
        // Property carries captured levy (would give Tier 0 = 959) BUT
        // the agent has explicitly overridden to 1_200. The override
        // must not be clobbered on re-estimate.
        [$pres, $agencyId, $prop] = $this->seedSectionalSubject(levy: 959);
        $version = $this->seedVersion($pres);

        // Agent override audit row + persisted column value.
        $pres->monthly_levies = 1_200;
        $pres->saveQuietly();
        HoldingCostDataPoint::create([
            'agency_id'               => $agencyId,
            'presentation_version_id' => $version->id,
            'property_id'             => $prop->id,
            'component'               => 'levy',
            'monthly_value_zar'       => 1_200,
            'source'                  => HoldingCostDataPoint::SOURCE_AGENT_OVERRIDE,
            'source_ref'              => 'presentation_version:' . $version->id . ':monthly_levies',
        ]);

        $estimator = app(HoldingCostEstimator::class);
        $result = $estimator->estimateAndPersist($pres->fresh(['property']));

        // Override must be respected — `skipped` records it; the column
        // keeps 1_200 not Tier 0's 959.
        $this->assertArrayHasKey('levy', $result['skipped']);
        $this->assertSame('agent override present', $result['skipped']['levy']);
        $this->assertSame(1200.0, (float) $pres->fresh()->monthly_levies);
    }

    public function test_tier0_levy_with_special_levy_uses_bcmath_sum(): void
    {
        // Pin: levy + special_levy added via bcadd. At these magnitudes
        // it's the same answer as plain int addition; the test exists to
        // catch a future refactor that drops bcmath inadvertently.
        [$pres, $agencyId, $prop] = $this->seedSectionalSubject(levy: 959);
        $prop->update(['special_levy' => 41]);

        $estimator = app(HoldingCostEstimator::class);
        $result = $estimator->resolveOne('levy', $pres);
        $this->assertSame('tier0', $result['tier']);
        $this->assertSame(1_000, $result['value']);
    }
}
