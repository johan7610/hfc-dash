<?php

declare(strict_types=1);

namespace Tests\Feature\Presentation;

use App\Models\Presentation;
use App\Models\PresentationSoldComp;
use App\Models\Property;
use App\Models\User;
use App\Services\Presentations\CmaCoverageService;
use App\Services\Presentations\MicSnapshotHydrator;
use App\Support\Presentations\CompFingerprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Build 8d — registered deals feed the engine comp pool with CMA-wins
 * precedence on dedup. Equal weight with CMA comps. Multi-tenant isolated,
 * demo/real isolated, latent badge double-count between CMA and deal
 * sources for the same sale is closed.
 */
final class Build8dDealCompsTest extends TestCase
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

    public function test_linked_deal_is_materialised_with_full_property_attributes(): void
    {
        [$presentation, $agencyId] = $this->seedAgencyPropertyPresentation(propertyType: 'House');
        $linkedProperty = $this->seedComparableProperty($agencyId, 'House', 'sectional_title_OFF', erfSize: 750, sizeM2: 180);
        $this->seedDeal($agencyId, propertyId: $linkedProperty->id, address: $linkedProperty->address, regDate: today()->subMonths(2)->toDateString(), salePrice: 1_250_000);

        (new MicSnapshotHydrator())->hydrateForPresentation($presentation->fresh());

        $comps = PresentationSoldComp::where('presentation_id', $presentation->id)
            ->where('parser_version', MicSnapshotHydrator::SOURCE_TAG_DEAL)
            ->get();

        $this->assertCount(1, $comps);
        $row = $comps->first();
        $this->assertSame(1_250_000, (int) $row->sold_price_inc);
        $this->assertSame($agencyId, (int) $row->agency_id);
        $this->assertSame('House', $row->property_type);
        $this->assertSame(750, (int) $row->size_m2); // erf_size_m2 for non-sectional
        $this->assertSame('Testville', $row->suburb);

        $raw = json_decode($row->raw_row_json, true);
        $this->assertSame('deal_register', $raw['source']);
        $this->assertTrue($raw['trusted_internal_source']);
        $this->assertFalse($raw['subject_match_used']);
    }

    public function test_unlinked_deal_still_in_pool_with_null_type_and_size(): void
    {
        [$presentation, $agencyId] = $this->seedAgencyPropertyPresentation();
        // No property_id — bare-import deal, address LIKE matches suburb.
        $this->seedDeal($agencyId, propertyId: null, address: '17 Mystery Lane, Testville', regDate: today()->subMonths(3)->toDateString(), salePrice: 950_000);

        (new MicSnapshotHydrator())->hydrateForPresentation($presentation->fresh());

        $comps = PresentationSoldComp::where('presentation_id', $presentation->id)
            ->where('parser_version', MicSnapshotHydrator::SOURCE_TAG_DEAL)
            ->get();

        $this->assertCount(1, $comps, 'unlinked deal in suburb must still enter the pool');
        $row = $comps->first();
        $this->assertSame(950_000, (int) $row->sold_price_inc);
        $this->assertNull($row->property_type);
        $this->assertNull($row->size_m2, 'unlinked deal has no size — pool member only by price/date');

        $raw = json_decode($row->raw_row_json, true);
        $this->assertTrue($raw['trusted_internal_source']);
    }

    public function test_cma_wins_dedup_when_same_sale_in_both_sources(): void
    {
        [$presentation, $agencyId] = $this->seedAgencyPropertyPresentation();

        $saleDate = today()->subMonths(4)->toDateString();
        $address  = '42 Shared St';
        $price    = 1_500_000;

        // MIC row
        $this->seedMicCompRow($agencyId, address: $address, saleDate: $saleDate, salePrice: $price, extentM2: 600);
        // Deal for the same sale
        $this->seedDeal($agencyId, propertyId: null, address: $address . ', Testville', regDate: $saleDate, salePrice: $price);

        $result = (new MicSnapshotHydrator())->hydrateForPresentation($presentation->fresh());

        $this->assertSame(1, $result['sold_comps_inserted'], 'MIC row should land');
        $this->assertSame(0, $result['n_deals_added'], 'deal should be skipped by CMA-wins precedence');
        $this->assertSame(1, $result['n_deals_dedup_skipped']);

        // Only ONE comp materialised, and it's the MIC one.
        $comps = PresentationSoldComp::where('presentation_id', $presentation->id)->get();
        $this->assertCount(1, $comps);
        $this->assertSame(MicSnapshotHydrator::SOURCE_TAG, $comps->first()->parser_version);
    }

    public function test_deal_only_sale_is_full_equal_comp(): void
    {
        [$presentation, $agencyId] = $this->seedAgencyPropertyPresentation();
        $this->seedDeal($agencyId, propertyId: null, address: 'Deal-only sale, Testville', regDate: today()->subMonths(1)->toDateString(), salePrice: 800_000);

        $result = (new MicSnapshotHydrator())->hydrateForPresentation($presentation->fresh());

        $this->assertSame(1, $result['n_deals_added']);
        $this->assertSame(0, $result['n_deals_dedup_skipped']);
        $this->assertCount(1, PresentationSoldComp::where('presentation_id', $presentation->id)->get());
    }

    public function test_foreign_agency_deal_is_excluded(): void
    {
        [$presentation, $agencyId] = $this->seedAgencyPropertyPresentation();

        $otherAgencyId = (int) DB::table('agencies')->insertGetId([
            'name' => 'Other ' . Str::random(6),
            'slug' => 'other-' . Str::random(8),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('branches')->insert([
            'id' => $otherAgencyId, 'agency_id' => $otherAgencyId, 'name' => 'Default',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Same suburb, different agency.
        $this->seedDeal($otherAgencyId, propertyId: null, address: 'Foreign deal, Testville', regDate: today()->subMonths(2)->toDateString(), salePrice: 1_100_000);

        (new MicSnapshotHydrator())->hydrateForPresentation($presentation->fresh());

        $this->assertCount(
            0,
            PresentationSoldComp::where('presentation_id', $presentation->id)->get(),
            'foreign-agency deals must NOT enter the pool',
        );
    }

    public function test_demo_real_isolation_on_deal_injection(): void
    {
        [$presentation, $agencyId] = $this->seedAgencyPropertyPresentation();
        // Subject property is real (is_demo=0 from seedAgencyPropertyPresentation).
        // Seed a demo deal in the same suburb.
        $this->seedDeal($agencyId, propertyId: null, address: 'Demo deal, Testville', regDate: today()->subMonths(2)->toDateString(), salePrice: 900_000, isDemo: true);

        (new MicSnapshotHydrator())->hydrateForPresentation($presentation->fresh());

        $this->assertCount(
            0,
            PresentationSoldComp::where('presentation_id', $presentation->id)->get(),
            'demo deals must not enter a real-data presentation pool',
        );
    }

    public function test_badge_source_agnostic_dedup_no_longer_double_counts_cma_plus_deal(): void
    {
        [$presentation, $agencyId, $userId] = $this->seedAgencyPropertyPresentation();

        $saleDate = today()->subMonths(3)->toDateString();
        $address  = '99 Same Sale Ave';
        $price    = 1_750_000;

        // Same sale appears in BOTH a CMA Info import AND HFC's deal register.
        $this->seedMicCompRow($agencyId, address: $address, saleDate: $saleDate, salePrice: $price, extentM2: 700);
        $this->seedDeal($agencyId, propertyId: null, address: $address . ', Testville', regDate: $saleDate, salePrice: $price);

        $property = Property::find($presentation->property_id);

        $score = (new CmaCoverageService())->scoreForProperty($property->fresh());

        $this->assertSame(1, $score['comp_count'], 'badge must count the shared sale ONCE — Build 8d source-agnostic fingerprint');
    }

    public function test_comp_fingerprint_source_agnostic_key_collapses_cross_source_duplicates(): void
    {
        $dealKey = CompFingerprint::sourceAgnosticKey(
            address: '10 Smith St',
            schemeName: null,
            sectionNumber: null,
            saleDate: '2024-05-01',
            salePrice: 1_500_000,
        );
        $micKey = CompFingerprint::sourceAgnosticKey(
            address: '10 Smith St',
            schemeName: null,
            sectionNumber: null,
            saleDate: '2024-05-01',
            salePrice: 1_500_000,
        );
        $this->assertSame($dealKey, $micKey);

        // Sectional key uses scheme + section.
        $sectionalKey = CompFingerprint::sourceAgnosticKey(
            address: '10 Smith St',
            schemeName: 'Ocean Breeze',
            sectionNumber: '5',
            saleDate: '2024-05-01',
            salePrice: 1_500_000,
        );
        $this->assertSame('OCEAN BREEZE|S5|2024-05-01|1500000', $sectionalKey);

        // Source-tagged form prefixes only.
        $this->assertSame('D|' . $dealKey, CompFingerprint::sourceTaggedKey(
            CompFingerprint::SOURCE_DEAL,
            '10 Smith St', null, null, '2024-05-01', 1_500_000,
        ));
    }

    // ── Helpers ────────────────────────────────────────────────────────

    /** @return array{0:Presentation, 1:int, 2:int} */
    private function seedAgencyPropertyPresentation(string $propertyType = 'House'): array
    {
        $agencyId = (int) DB::table('agencies')->insertGetId([
            'name' => 'Build8d ' . Str::random(6),
            'slug' => 'b8d-' . Str::random(8),
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
            'title'         => 'Subject',
            'property_type' => $propertyType,
            'category'      => 'Residential',
            'suburb'        => 'Testville',
            'price'         => 1_900_000,
            'address'       => '1 Subject Way',
            'status'        => 'active',
            'listing_type'  => 'sale',
            'erf_size_m2'   => 800,
            'size_m2'       => 200,
            'latitude'      => -30.84,
            'longitude'     => 30.39,
        ]);
        $presentation = Presentation::create([
            'agency_id'          => $agencyId,
            'branch_id'          => $agencyId,
            'property_id'        => $property->id,
            'created_by_user_id' => $user->id,
            'title'              => 'Build8dDealCompsTest',
            'property_address'   => '1 Subject Way',
            'suburb'             => 'Testville',
            'property_type'      => 'other',
            'erf_size_m2'        => 800,
            'floor_area_m2'      => 200,
            'asking_price_inc'   => 1_900_000,
            'status'             => 'draft',
            'currency'           => 'ZAR',
        ]);
        return [$presentation, $agencyId, $user->id];
    }

    private function seedComparableProperty(int $agencyId, string $propertyType, string $titleType, ?int $erfSize, ?int $sizeM2): Property
    {
        $user = DB::table('users')->where('agency_id', $agencyId)->value('id');
        return Property::create([
            'agency_id'     => $agencyId,
            'branch_id'     => $agencyId,
            'agent_id'      => $user,
            'title'         => 'Comp ' . Str::random(4),
            'property_type' => $propertyType,
            'category'      => 'Residential',
            'suburb'        => 'Testville',
            'price'         => 1_300_000,
            'address'       => Str::random(8) . ' Street',
            'status'        => 'active',
            'listing_type'  => 'sale',
            'erf_size_m2'   => $erfSize,
            'size_m2'       => $sizeM2,
        ]);
    }

    private function seedDeal(
        int $agencyId,
        ?int $propertyId,
        string $address,
        string $regDate,
        int $salePrice,
        bool $isDemo = false,
    ): int {
        return (int) DB::table('deals')->insertGetId([
            'agency_id'         => $agencyId,
            'property_id'       => $propertyId,
            'property_address'  => $address,
            'period'            => '2026-01',
            'deal_date'         => $regDate,
            'registration_date' => $regDate,
            'sale_date'         => $regDate,
            'sale_price'        => $salePrice,
            'property_value'    => $salePrice,
            'total_commission'  => 0,
            'listing_external'  => 0,
            'selling_external'  => 0,
            'is_demo'           => $isDemo ? 1 : 0,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
    }

    private function seedMicCompRow(
        int $agencyId,
        string $address,
        string $saleDate,
        int $salePrice,
        ?int $extentM2 = null,
    ): int {
        return (int) DB::table('market_report_comp_rows')->insertGetId([
            'agency_id'         => $agencyId,
            'market_report_id'  => null,
            'row_index'         => 1,
            'row_type'          => 'comp',
            'address'           => $address,
            'suburb_normalised' => 'testville',
            'property_type'     => 'House',
            'extent_m2'         => $extentM2,
            'sale_date'         => $saleDate,
            'sale_price'        => $salePrice,
            'is_demo'           => 0,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
    }
}
