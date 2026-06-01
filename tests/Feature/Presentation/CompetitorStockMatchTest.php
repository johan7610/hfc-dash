<?php

declare(strict_types=1);

namespace Tests\Feature\Presentation;

use App\Models\Agency;
use App\Models\Presentation;
use App\Models\PresentationVersion;
use App\Models\Property;
use App\Models\User;
use App\Services\Presentations\AnalysisDataService;
use App\Services\Presentations\CompetitorStockMatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Competitor Stock build — synthetic-ContactMatch adapter reuses
 * Core Matches scoring engine against prospecting_listings.
 */
final class CompetitorStockMatchTest extends TestCase
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

    public function test_returns_matches_in_price_band_sorted_by_score(): void
    {
        [$subject, $agencyId] = $this->seedSubject(price: 2_000_000, beds: 3, suburb: 'Uvongo', type: 'House');

        // In-band competitors — same price/suburb/type/beds → perfect.
        $exactId = $this->seedListing($agencyId, suburb: 'Uvongo', price: 1_950_000, beds: 3, type: 'House');
        // Different type, otherwise close → strong/approximate range.
        $offTypeId = $this->seedListing($agencyId, suburb: 'Uvongo', price: 2_000_000, beds: 3, type: 'Apartment');
        // Out of band — beds hard-fail (too many).
        $offBedsId = $this->seedListing($agencyId, suburb: 'Uvongo', price: 2_000_000, beds: 6, type: 'House');

        $matches = (new CompetitorStockMatchService())->findCompetitors($subject)->all();

        $ids = array_column($matches, 'listing_id');
        // Exact match present; out-of-bed-band excluded (hard fail).
        $this->assertContains($exactId, $ids);
        $this->assertNotContains($offBedsId, $ids);
        // Sorted by score DESC.
        $scores = array_column($matches, 'score');
        $this->assertSame($scores, collect($scores)->sortDesc()->values()->all());
    }

    public function test_respects_agency_min_score_threshold(): void
    {
        [$subject, $agencyId] = $this->seedSubject(price: 2_000_000, beds: 3, suburb: 'Uvongo', type: 'House');

        // Same-FAMILY (freehold) non-exact-kind candidate — Vacant Land
        // alongside a House subject. Level-1 gate passes (both freehold);
        // Level-2 misses (different kind so no +5 bonus); score lands in
        // the 85-92 range, perfect for a 50-vs-95 threshold split.
        $this->seedListing($agencyId, suburb: 'Uvongo', price: 1_950_000, beds: null, type: 'Vacant land');

        // Loose threshold (50) — should include the listing.
        Agency::find($agencyId)->update([
            'competitor_stock_min_score' => 50,
            // Step-up disabled so the family fallback always shows the
            // non-exact-kind row (otherwise the floor=5 default would
            // suppress it when exact-kind count is 0).
            'competitor_stock_min_same_type' => 0,
        ]);
        $loose = (new CompetitorStockMatchService())->findCompetitors($subject)->all();
        $this->assertNotEmpty($loose, 'min_score=50 should keep the in-family listing');

        // Strict threshold (95) — same listing should now drop out.
        Agency::find($agencyId)->update(['competitor_stock_min_score' => 95]);
        $strict = (new CompetitorStockMatchService())->findCompetitors($subject)->all();
        $this->assertLessThan(count($loose), count($strict),
            'min_score=95 should filter more strictly than 50');
        foreach ($strict as $m) {
            $this->assertGreaterThanOrEqual(95, $m['score']);
        }
    }

    public function test_subject_without_price_or_suburb_returns_empty(): void
    {
        [$subject, $agencyId] = $this->seedSubject(price: 0, beds: 3, suburb: 'Uvongo', type: 'House');
        // The seedSubject sets price=0 → service should bail early.
        $matches = (new CompetitorStockMatchService())->findCompetitors($subject)->all();
        $this->assertSame([], $matches);
    }

    public function test_competitor_stock_compiled_into_analysis_payload(): void
    {
        [$subject, $agencyId] = $this->seedSubject(price: 2_000_000, beds: 3, suburb: 'Uvongo', type: 'House');
        $this->seedListing($agencyId, suburb: 'Uvongo', price: 1_950_000, beds: 3, type: 'House');

        $presentation = $this->seedPresentation($subject);
        $version      = $this->seedVersion($presentation);

        $analysis = (new AnalysisDataService())->compile($presentation->fresh(), $version);
        $this->assertArrayHasKey('competitor_stock', $analysis);
        $this->assertNotEmpty($analysis['competitor_stock']['matches']);
        // include_ids null on first paint → all visible.
        $this->assertNull($analysis['competitor_stock']['included_ids']);
        $this->assertSameSize(
            $analysis['competitor_stock']['matches'],
            $analysis['competitor_stock']['visible'],
        );
    }

    public function test_visible_set_respects_version_whitelist(): void
    {
        [$subject, $agencyId] = $this->seedSubject(price: 2_000_000, beds: 3, suburb: 'Uvongo', type: 'House');
        $idA = $this->seedListing($agencyId, suburb: 'Uvongo', price: 1_950_000, beds: 3, type: 'House');
        $idB = $this->seedListing($agencyId, suburb: 'Uvongo', price: 2_050_000, beds: 3, type: 'House');

        $presentation = $this->seedPresentation($subject);
        $version      = $this->seedVersion($presentation, includedCompetitorIds: [$idA]);

        $analysis = (new AnalysisDataService())->compile($presentation->fresh(), $version);
        $visible  = $analysis['competitor_stock']['visible'];

        $this->assertCount(1, $visible);
        $this->assertSame($idA, $visible[0]['listing_id']);
    }

    public function test_visible_empty_when_whitelist_is_empty_array(): void
    {
        [$subject, $agencyId] = $this->seedSubject(price: 2_000_000, beds: 3, suburb: 'Uvongo', type: 'House');
        $this->seedListing($agencyId, suburb: 'Uvongo', price: 1_950_000, beds: 3, type: 'House');

        $presentation = $this->seedPresentation($subject);
        $version      = $this->seedVersion($presentation, includedCompetitorIds: []);

        $analysis = (new AnalysisDataService())->compile($presentation->fresh(), $version);
        $this->assertSame([], $analysis['competitor_stock']['visible']);
        $this->assertNotEmpty($analysis['competitor_stock']['matches'], 'matches still computed; only visible is empty');
    }

    public function test_hfc_owned_enrichment_attaches_dom_and_views(): void
    {
        [$subject, $agencyId] = $this->seedSubject(price: 2_000_000, beds: 3, suburb: 'Uvongo', type: 'House');
        $listingId = $this->seedListing(
            $agencyId, suburb: 'Uvongo', price: 1_950_000, beds: 3, type: 'House',
            portalRef: 'P24-987654',
        );
        // PropCon stock row for HFC's mandate of the same listing.
        // days_on_market is a computed accessor on ListingStock —
        // derived from listed_at; we set listed_at = 42 days ago.
        DB::table('listing_stocks')->insert([
            'user_id'      => User::factory()->create(['agency_id' => $agencyId])->id,
            'agency_id'    => $agencyId,
            'source'       => 'propcon',
            'external_ref' => 'P24-987654',
            'property'     => 'Test HFC mandate',
            'price_cents'  => 1_950_000 * 100,
            'status'       => 'For Sale',
            'listed_at'    => now()->subDays(42),
            'raw_payload'  => json_encode(['Views' => 1234, 'Matches' => 8]),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $matches = (new CompetitorStockMatchService())->findCompetitors($subject)->all();
        $hfc = collect($matches)->firstWhere('listing_id', $listingId);
        $this->assertNotNull($hfc);
        $this->assertTrue($hfc['is_hfc_owned']);
        $this->assertSame(42, $hfc['days_on_market']);
        $this->assertSame(1234, $hfc['views']);
        $this->assertSame(8, $hfc['matches']);
    }

    public function test_output_includes_rich_card_fields_for_review_screen(): void
    {
        [$subject, $agencyId] = $this->seedSubject(price: 2_000_000, beds: 3, suburb: 'Uvongo', type: 'House');
        $this->seedListing(
            $agencyId,
            suburb:    'Uvongo',
            price:     1_950_000,
            beds:      3,
            type:      'House',
            portalRef: 'P24-RICH-001',
        );

        $matches = (new CompetitorStockMatchService())->findCompetitors($subject)->all();
        $this->assertNotEmpty($matches);
        $row = $matches[0];

        // garages — was missing pre-fix; now exposed for the rich card.
        $this->assertArrayHasKey('garages',            $row);
        // portal_ref — fetched internally pre-fix, surfaced now.
        $this->assertArrayHasKey('portal_ref',         $row);
        $this->assertSame('P24-RICH-001', $row['portal_ref']);
        // thumbnail_url + thumbnail_abs_path — null when no thumbnail
        // cached, but the keys must exist so the card can render the
        // placeholder branch.
        $this->assertArrayHasKey('thumbnail_url',      $row);
        $this->assertArrayHasKey('thumbnail_abs_path', $row);
        $this->assertNull($row['thumbnail_url']);
        $this->assertNull($row['thumbnail_abs_path']);
    }

    public function test_non_hfc_listings_do_not_get_dom_or_views(): void
    {
        [$subject, $agencyId] = $this->seedSubject(price: 2_000_000, beds: 3, suburb: 'Uvongo', type: 'House');
        $this->seedListing($agencyId, suburb: 'Uvongo', price: 1_950_000, beds: 3, type: 'House', portalRef: 'P24-OTHER');
        // No listing_stocks row → no PropCon enrichment.

        $matches = (new CompetitorStockMatchService())->findCompetitors($subject)->all();
        $this->assertFalse($matches[0]['is_hfc_owned']);
        $this->assertNull($matches[0]['days_on_market']);
        $this->assertNull($matches[0]['views']);
    }

    // ── Level-1 hard gate (FH/SS family) ──────────────────────────────

    public function test_sectional_subject_drops_freehold_candidates(): void
    {
        // Sectional Title apartment subject.
        [$subject, $agencyId] = $this->seedSubject(
            price: 1_200_000, beds: 2, suburb: 'Uvongo', type: 'Sectional Title',
        );

        $sectionalId = $this->seedListing($agencyId, suburb: 'Uvongo', price: 1_200_000, beds: 2, type: 'Apartment');
        $townhouseId = $this->seedListing($agencyId, suburb: 'Uvongo', price: 1_200_000, beds: 2, type: 'Townhouse');
        $houseId     = $this->seedListing($agencyId, suburb: 'Uvongo', price: 1_200_000, beds: 2, type: 'House');
        $landId      = $this->seedListing($agencyId, suburb: 'Uvongo', price: 1_200_000, beds: null, type: 'Vacant land');

        // Step-up off so we see the full pre-step-up result set.
        Agency::find($agencyId)->update(['competitor_stock_min_same_type' => 0]);

        $matches = (new CompetitorStockMatchService())->findCompetitors($subject)->all();
        $ids = array_column($matches, 'listing_id');

        $this->assertContains($sectionalId, $ids, 'Apartment must qualify for a sectional subject');
        $this->assertContains($townhouseId, $ids, 'Townhouse must qualify for a sectional subject (same family)');
        $this->assertNotContains($houseId, $ids, 'House (freehold) must NEVER reach a sectional subject');
        $this->assertNotContains($landId,  $ids, 'Vacant land (freehold) must NEVER reach a sectional subject');
    }

    public function test_freehold_subject_drops_sectional_candidates(): void
    {
        // Full-title house subject.
        [$subject, $agencyId] = $this->seedSubject(
            price: 2_000_000, beds: 3, suburb: 'Uvongo', type: 'House',
        );

        $houseId     = $this->seedListing($agencyId, suburb: 'Uvongo', price: 2_000_000, beds: 3, type: 'House');
        $landId      = $this->seedListing($agencyId, suburb: 'Uvongo', price: 1_900_000, beds: null, type: 'Vacant land');
        $apartmentId = $this->seedListing($agencyId, suburb: 'Uvongo', price: 2_000_000, beds: 3, type: 'Apartment');
        $townhouseId = $this->seedListing($agencyId, suburb: 'Uvongo', price: 2_000_000, beds: 3, type: 'Townhouse');

        Agency::find($agencyId)->update(['competitor_stock_min_same_type' => 0]);

        $matches = (new CompetitorStockMatchService())->findCompetitors($subject)->all();
        $ids = array_column($matches, 'listing_id');

        $this->assertContains($houseId, $ids, 'House must qualify for a freehold subject');
        $this->assertContains($landId,  $ids, 'Vacant land must qualify for a freehold subject (same family)');
        $this->assertNotContains($apartmentId, $ids, 'Apartment (sectional) must NEVER reach a freehold subject');
        $this->assertNotContains($townhouseId, $ids, 'Townhouse (sectional) must NEVER reach a freehold subject');
    }

    public function test_commercial_and_industrial_excluded_for_residential_subjects(): void
    {
        [$subject, $agencyId] = $this->seedSubject(price: 2_000_000, beds: 3, suburb: 'Uvongo', type: 'House');

        $okId        = $this->seedListing($agencyId, suburb: 'Uvongo', price: 2_000_000, beds: 3, type: 'House');
        $commercId   = $this->seedListing($agencyId, suburb: 'Uvongo', price: 2_000_000, beds: null, type: 'Commercial');
        $industrialId = $this->seedListing($agencyId, suburb: 'Uvongo', price: 2_000_000, beds: null, type: 'Industrial');

        $matches = (new CompetitorStockMatchService())->findCompetitors($subject)->all();
        $ids = array_column($matches, 'listing_id');

        $this->assertContains($okId, $ids);
        $this->assertNotContains($commercId,   $ids, 'Commercial must be excluded from residential matching');
        $this->assertNotContains($industrialId,$ids, 'Industrial must be excluded from residential matching');
    }

    // ── Level-2 preference (exact kind > same-family-other-kind) ──────

    public function test_apartment_subject_ranks_apartments_above_townhouses(): void
    {
        [$subject, $agencyId] = $this->seedSubject(
            price: 1_200_000, beds: 2, suburb: 'Uvongo', type: 'Sectional Title',
        );

        // Both candidates are same Level-1 family (sectional). Apartment
        // is exact-kind for a "Sectional Title" subject (normaliseTypeKind
        // maps both "Sectional Title" and "Apartment" to 'apartment').
        // Townhouse is same-family-different-kind.
        $aptId  = $this->seedListing($agencyId, suburb: 'Uvongo', price: 1_200_000, beds: 2, type: 'Apartment');
        $thId   = $this->seedListing($agencyId, suburb: 'Uvongo', price: 1_200_000, beds: 2, type: 'Townhouse');

        // Step-up off so both show. The +5 exact-kind bonus must push
        // apartment above townhouse in the score sort.
        Agency::find($agencyId)->update(['competitor_stock_min_same_type' => 0]);

        $matches = (new CompetitorStockMatchService())->findCompetitors($subject)->values()->all();
        $this->assertCount(2, $matches);

        // First row by score-DESC sort = apartment. Second = townhouse.
        $this->assertSame($aptId, $matches[0]['listing_id'], 'Apartment must rank above townhouse for apartment subject');
        $this->assertSame('exact', $matches[0]['level2_match']);
        $this->assertSame($thId,  $matches[1]['listing_id']);
        $this->assertSame('family', $matches[1]['level2_match']);
        $this->assertGreaterThan($matches[1]['score'], $matches[0]['score'],
            'Apartment score must exceed townhouse score (Level-2 +5 bonus)');
    }

    // ── Step-up fallback ──────────────────────────────────────────────

    public function test_step_up_suppresses_other_kinds_when_exact_count_meets_floor(): void
    {
        [$subject, $agencyId] = $this->seedSubject(
            price: 2_000_000, beds: 3, suburb: 'Uvongo', type: 'House',
        );

        // Floor = 2. Seed 2 exact-kind (House) + 1 same-family other-kind
        // (Vacant land). With floor met, step-up suppresses non-exact.
        Agency::find($agencyId)->update(['competitor_stock_min_same_type' => 2]);

        $h1 = $this->seedListing($agencyId, suburb: 'Uvongo', price: 2_000_000, beds: 3, type: 'House');
        $h2 = $this->seedListing($agencyId, suburb: 'Uvongo', price: 2_050_000, beds: 3, type: 'House');
        $vl = $this->seedListing($agencyId, suburb: 'Uvongo', price: 2_000_000, beds: null, type: 'Vacant land');

        $matches = (new CompetitorStockMatchService())->findCompetitors($subject)->all();
        $ids = array_column($matches, 'listing_id');

        $this->assertContains($h1, $ids);
        $this->assertContains($h2, $ids);
        $this->assertNotContains($vl, $ids, 'Vacant land must be suppressed when exact-kind floor is met');
    }

    public function test_step_up_widens_to_family_when_exact_count_below_floor(): void
    {
        [$subject, $agencyId] = $this->seedSubject(
            price: 2_000_000, beds: 3, suburb: 'Uvongo', type: 'House',
        );

        // Floor = 5. Only 1 House + 1 Vacant land available. Exact-kind
        // count (1) < floor (5) → widen to include same-family other kind.
        Agency::find($agencyId)->update(['competitor_stock_min_same_type' => 5]);

        $h1 = $this->seedListing($agencyId, suburb: 'Uvongo', price: 2_000_000, beds: 3, type: 'House');
        $vl = $this->seedListing($agencyId, suburb: 'Uvongo', price: 2_000_000, beds: null, type: 'Vacant land');

        $matches = (new CompetitorStockMatchService())->findCompetitors($subject)->all();
        $ids = array_column($matches, 'listing_id');

        $this->assertContains($h1, $ids);
        $this->assertContains($vl, $ids, 'Vacant land must surface when exact-kind is below floor (step-up)');
    }

    // ── helpers ────────────────────────────────────────────────────────

    /** @return array{0:Property, 1:int} */
    private function seedSubject(int $price, int $beds, string $suburb, string $type): array
    {
        $agencyId = (int) DB::table('agencies')->insertGetId([
            'name' => 'Competitor ' . Str::random(4),
            'slug' => 'comp-' . Str::random(6),
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
            'property_type' => $type,
            'category'      => 'Residential',
            'suburb'        => $suburb,
            'price'         => $price,
            'beds'          => $beds,
            'address'       => '1 Subject Way',
            'status'        => 'active',
            'listing_type'  => 'sale',
        ]);
        return [$property, $agencyId];
    }

    private function seedListing(int $agencyId, string $suburb, int $price, ?int $beds, string $type, ?string $portalRef = null): int
    {
        return (int) DB::table('prospecting_listings')->insertGetId([
            'agency_id'         => $agencyId,
            'captured_by_user_id' => User::factory()->create(['agency_id' => $agencyId])->id,
            'portal_source'     => 'p24',
            'portal_ref'        => $portalRef ?? ('P24-' . Str::random(8)),
            'portal_url'        => 'https://www.property24.com/' . Str::random(10),
            'address'           => ($beds ?? 0) . 'BR ' . $type . ', ' . $suburb,
            'suburb'            => $suburb,
            'price'             => $price,
            'bedrooms'          => $beds,
            'bathrooms'         => $beds !== null ? 2 : null,
            'property_size_m2'  => $beds !== null ? 150 : null,
            'erf_size_m2'       => 500,
            'property_type'     => $type,
            'first_seen_at'     => now(),
            'last_seen_at'      => now(),
            'is_active'         => 1,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
    }

    private function seedPresentation(Property $subject): Presentation
    {
        return Presentation::create([
            'agency_id'          => $subject->agency_id,
            'branch_id'          => $subject->branch_id,
            'property_id'        => $subject->id,
            'created_by_user_id' => $subject->agent_id,
            'title'              => 'CompetitorTest',
            'property_address'   => $subject->address,
            'suburb'             => $subject->suburb,
            'property_type'      => $subject->property_type,
            'asking_price_inc'   => $subject->price,
            'status'             => 'draft',
            'currency'           => 'ZAR',
        ]);
    }

    private function seedVersion(Presentation $presentation, ?array $includedCompetitorIds = null): PresentationVersion
    {
        return PresentationVersion::create([
            'agency_id'                    => $presentation->agency_id,
            'presentation_id'              => $presentation->id,
            'blueprint_version'            => 'test',
            'data_snapshot_json'           => json_encode(['note' => 'competitor-test']),
            'compiled_at'                  => now(),
            'included_competitor_ids_json' => $includedCompetitorIds,
        ]);
    }
}
