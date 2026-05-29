<?php

declare(strict_types=1);

namespace Tests\Feature\Presentation;

use App\Models\Presentation;
use App\Models\PresentationField;
use App\Models\PresentationSoldComp;
use App\Models\Property;
use App\Models\User;
use App\Services\Presentations\CmaComputeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Build 8a — CoreX independent CMA compute engine.
 *
 * Asserts the bcmath math on known fixtures (exact values), the
 * div-by-zero / empty-pool guards, and the pool_stats distribution.
 * Condition-adjusted variants verified explicitly.
 *
 * All asserted values match the Phase A* probe sample (n=22, prices
 * spanning 65k → 1.25M) so the fixture doubles as a regression anchor
 * against future bcmath edits.
 */
final class CmaComputeServiceTest extends TestCase
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

    // ── Known-fixture pool ────────────────────────────────────────────

    public function test_median_mean_pool_stats_on_known_fixture_pool(): void
    {
        // Five-comp fixture with hand-computable stats.
        //   prices = [400_000, 800_000, 1_000_000, 1_200_000, 1_400_000]
        //   median   = 1_000_000
        //   mean     = 960_000
        //   min      = 400_000
        //   p25      = 800_000   (floor(5*.25)=1 → idx 1)
        //   p75      = 1_200_000 (floor(5*.75)=3 → idx 3)
        //   max      = 1_400_000
        $svc = new CmaComputeService();
        [$presentation] = $this->seed5CompPool();

        $out = $svc->compute(
            $presentation,
            $presentation->fresh('soldComps')->soldComps,
            isSectional: false,
            conditionContext: [],
        );

        $this->assertSame(1_000_000, $out['method_median']['raw']);
        $this->assertSame(5,         $out['method_median']['n']);
        $this->assertSame(960_000,   $out['method_mean']['raw']);
        $this->assertSame(5,         $out['method_mean']['n']);
        // Without a condition pct, condition_adjusted equals raw.
        $this->assertSame(1_000_000, $out['method_median']['condition_adjusted']);
        $this->assertSame(960_000,   $out['method_mean']['condition_adjusted']);

        $ps = $out['pool_stats'];
        $this->assertSame(5,         $ps['n_total']);
        $this->assertSame(5,         $ps['n_with_size']);
        $this->assertSame(400_000,   $ps['min']);
        $this->assertSame(800_000,   $ps['p25']);
        $this->assertSame(1_000_000, $ps['median']);
        $this->assertSame(1_200_000, $ps['p75']);
        $this->assertSame(1_400_000, $ps['max']);
    }

    public function test_rm2_extent_uses_median_rate_x_subject_extent(): void
    {
        // Five comps, all with size_m2 = 100. Prices as above.
        //   r/m² each = price / 100   → [4000, 8000, 10000, 12000, 14000]
        //   median r/m² = 10_000
        //   subject extent_m2 = 150 (full-title path uses erf_size_m2)
        //   raw = 10_000 × 150 = 1_500_000
        $svc = new CmaComputeService();
        [$presentation] = $this->seed5CompPool(extentM2: 150);

        $out = $svc->compute(
            $presentation,
            $presentation->fresh('soldComps')->soldComps,
            isSectional: false,
            conditionContext: [],
        );

        $rm2 = $out['method_rm2_extent'];
        $this->assertSame(10_000,    $rm2['rm2_median']);
        $this->assertSame(150,       $rm2['subject_extent_m2']);
        $this->assertSame(1_500_000, $rm2['raw']);
        $this->assertSame(1_500_000, $rm2['condition_adjusted']);
        $this->assertSame(5,         $rm2['n']);
    }

    // ── Div-by-zero guard ────────────────────────────────────────────

    public function test_div_by_zero_size_excludes_from_rm2_only(): void
    {
        // Mixed pool: 3 comps have size_m2, 2 don't. R/m² method
        // uses only the 3; median/mean see all 5.
        [$presentation, $agencyId, $userId] = $this->seedAgencyPropertyPresentation(extentM2: 100);

        $this->seedComp($presentation->id, $agencyId, 400_000, 100);
        $this->seedComp($presentation->id, $agencyId, 800_000, 80);
        $this->seedComp($presentation->id, $agencyId, 1_000_000, null);   // no size
        $this->seedComp($presentation->id, $agencyId, 1_200_000, 0);      // size 0
        $this->seedComp($presentation->id, $agencyId, 1_400_000, 200);

        $svc = new CmaComputeService();
        $out = $svc->compute(
            $presentation->fresh(),
            $presentation->fresh('soldComps')->soldComps,
            isSectional: false,
            conditionContext: [],
        );

        $this->assertSame(5, $out['method_median']['n']);
        $this->assertSame(5, $out['method_mean']['n']);
        $this->assertSame(5, $out['pool_stats']['n_total']);
        $this->assertSame(3, $out['pool_stats']['n_with_size']);
        $this->assertSame(3, $out['method_rm2_extent']['n']);
        $this->assertNotNull($out['method_rm2_extent']['raw']);
        $this->assertNotNull($out['method_rm2_extent']['rm2_median']);
    }

    // ── Empty-pool guard ─────────────────────────────────────────────

    public function test_empty_pool_returns_nulls_no_exception(): void
    {
        [$presentation] = $this->seedAgencyPropertyPresentation(extentM2: 100);

        $svc = new CmaComputeService();
        $out = $svc->compute(
            $presentation,
            collect(), // empty
            isSectional: false,
            conditionContext: [],
        );

        $this->assertNull($out['method_median']['raw']);
        $this->assertSame(0, $out['method_median']['n']);
        $this->assertNull($out['method_mean']['raw']);
        $this->assertNull($out['method_rm2_extent']['raw']);
        $this->assertNull($out['method_rm2_extent']['rm2_median']);
        $this->assertSame(0, $out['pool_stats']['n_total']);
        $this->assertSame(0, $out['pool_stats']['n_with_size']);
        $this->assertNull($out['pool_stats']['min']);
        $this->assertNull($out['pool_stats']['median']);
        $this->assertNull($out['pool_stats']['max']);
    }

    // ── Condition-adjusted variants ──────────────────────────────────

    public function test_condition_adjusted_variant_applies_bcmath_pct(): void
    {
        // Same 5-comp fixture. -15% on median 1_000_000:
        //   raw = 1_000_000
        //   factor = 1 + (-15/100) = 0.85
        //   adjusted = 1_000_000 × 0.85 = 850_000
        $svc = new CmaComputeService();
        [$presentation] = $this->seed5CompPool();

        $out = $svc->compute(
            $presentation,
            $presentation->fresh('soldComps')->soldComps,
            isSectional: false,
            conditionContext: ['pct' => -15.0, 'label' => 'To Renovate', 'source' => 'version_override'],
        );

        $this->assertSame(1_000_000, $out['method_median']['raw']);
        $this->assertSame(850_000,   $out['method_median']['condition_adjusted']);
        $this->assertSame(-15.0,     $out['method_median']['condition_pct']);

        // Mean: 960_000 × 0.85 = 816_000
        $this->assertSame(960_000, $out['method_mean']['raw']);
        $this->assertSame(816_000, $out['method_mean']['condition_adjusted']);
    }

    public function test_positive_condition_adjustment(): void
    {
        // +20% on median 1_000_000 → 1_200_000.
        $svc = new CmaComputeService();
        [$presentation] = $this->seed5CompPool();

        $out = $svc->compute(
            $presentation,
            $presentation->fresh('soldComps')->soldComps,
            isSectional: false,
            conditionContext: ['pct' => 20.0],
        );

        $this->assertSame(1_000_000, $out['method_median']['raw']);
        $this->assertSame(1_200_000, $out['method_median']['condition_adjusted']);
    }

    // ── Subject extent picker — title_type-aware ─────────────────────

    public function test_extent_picker_sectional_uses_size_m2(): void
    {
        [$presentation, $agencyId, $userId] = $this->seedAgencyPropertyPresentation(
            extentM2: null, // erf null
            sizeM2: 120,    // floor area present
            propertyType: 'Sectional Title', // observer sets title_type
        );
        $this->seedComp($presentation->id, $agencyId, 1_000_000, 100);

        $svc = new CmaComputeService();
        $out = $svc->compute(
            $presentation->fresh(['property', 'soldComps']),
            $presentation->fresh('soldComps')->soldComps,
            isSectional: true,
            conditionContext: [],
        );
        // r/m² median = 10_000; × subject extent (size_m2 = 120) = 1_200_000
        $this->assertSame(120,       $out['method_rm2_extent']['subject_extent_m2']);
        $this->assertSame(10_000,    $out['method_rm2_extent']['rm2_median']);
        $this->assertSame(1_200_000, $out['method_rm2_extent']['raw']);
    }

    public function test_extent_picker_full_title_uses_erf_size_m2(): void
    {
        [$presentation, $agencyId] = $this->seedAgencyPropertyPresentation(
            extentM2: 800,  // erf
            sizeM2: 120,    // floor area (ignored for full title)
            propertyType: 'House',
        );
        $this->seedComp($presentation->id, $agencyId, 1_000_000, 100);

        $svc = new CmaComputeService();
        $out = $svc->compute(
            $presentation->fresh(['property', 'soldComps']),
            $presentation->fresh('soldComps')->soldComps,
            isSectional: false,
            conditionContext: [],
        );
        $this->assertSame(800,       $out['method_rm2_extent']['subject_extent_m2']);
        $this->assertSame(8_000_000, $out['method_rm2_extent']['raw']); // 10_000 × 800
    }

    // ── cma_info_stated_middle copy-through ──────────────────────────

    public function test_cma_info_stated_middle_copied_from_extracted_field(): void
    {
        [$presentation, $agencyId] = $this->seedAgencyPropertyPresentation(extentM2: 100);
        PresentationField::create([
            'agency_id'       => $agencyId,
            'presentation_id' => $presentation->id,
            'field_key'       => 'cma.middle_range',
            'final_value'     => '1459000',
        ]);

        $svc = new CmaComputeService();
        $out = $svc->compute(
            $presentation->fresh(['fields', 'property', 'soldComps']),
            collect(),
            isSectional: false,
            conditionContext: [],
        );
        $this->assertSame(1_459_000, $out['cma_info_stated_middle']);
    }

    // ── Helpers ──────────────────────────────────────────────────────

    /** @return array{0:Presentation, 1:int, 2:int} */
    private function seedAgencyPropertyPresentation(
        ?int $extentM2 = null,
        ?int $sizeM2 = null,
        string $propertyType = 'House',
    ): array {
        $agencyId = (int) DB::table('agencies')->insertGetId([
            'name' => 'CmaCompute ' . Str::random(6),
            'slug' => 'cmac-' . Str::random(8),
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
            'agency_id'     => $agencyId,
            'branch_id'     => $agencyId,
            'agent_id'      => $user->id,
            'title'         => 'Test Property',
            'property_type' => $propertyType,
            'category'      => 'Residential',
            'suburb'        => 'Testville',
            'price'         => 1_900_000,
            'address'       => '1 Test Lane',
            'status'        => 'active',
            'listing_type'  => 'sale',
            'erf_size_m2'   => $extentM2,
            'size_m2'       => $sizeM2,
            'latitude'      => -30.84,
            'longitude'     => 30.39,
        ]);
        $presentation = Presentation::create([
            'agency_id'          => $agencyId,
            'branch_id'          => $agencyId,
            'property_id'        => $property->id,
            'created_by_user_id' => $user->id,
            'title'              => 'CmaComputeServiceTest',
            'property_address'   => '1 Test Lane',
            'suburb'             => 'Testville',
            'property_type'      => 'other',
            'erf_size_m2'        => $extentM2,
            'floor_area_m2'      => $sizeM2,
            'asking_price_inc'   => 1_900_000,
            'status'             => 'draft',
            'currency'           => 'ZAR',
        ]);
        return [$presentation, $agencyId, $user->id];
    }

    private function seedComp(int $presentationId, int $agencyId, int $price, ?int $sizeM2): PresentationSoldComp
    {
        return PresentationSoldComp::create([
            'agency_id'       => $agencyId,
            'presentation_id' => $presentationId,
            'property_type'   => 'House',
            'sold_date'       => now()->subMonths(rand(1, 12))->toDateString(),
            'sold_price_inc'  => $price,
            'suburb'          => 'Testville',
            'size_m2'         => $sizeM2,
            'raw_row_json'    => json_encode(['address' => 'Comp ' . Str::random(4)]),
            'parser_version'  => 'test',
        ]);
    }

    /** @return array{0:Presentation, 1:int, 2:int} */
    private function seed5CompPool(int $extentM2 = 800): array
    {
        $tuple = $this->seedAgencyPropertyPresentation(extentM2: $extentM2);
        [$presentation, $agencyId] = $tuple;
        foreach ([400_000, 800_000, 1_000_000, 1_200_000, 1_400_000] as $price) {
            $this->seedComp($presentation->id, $agencyId, $price, 100);
        }
        return $tuple;
    }
}
