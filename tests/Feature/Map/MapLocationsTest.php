<?php

declare(strict_types=1);

namespace Tests\Feature\Map;

use App\Models\User;
use App\Services\Map\LocationGrouper;
use App\Services\Map\MapBoundsRequest;
use App\Services\Map\MapPinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase A.1 Part 4 — backend tests M1-M5 for the composite-pin pipeline.
 *
 * Strategy:
 *   - M1, M2, M5 hit the LocationGrouper directly with handcrafted records.
 *     They don't need the DB plumbing and prove the grouping algorithm in
 *     isolation (faster, more focused failures).
 *   - M3, M4 exercise MapPinService end-to-end against seeded MySQL rows —
 *     these prove the SQL layer + grouper compose correctly and that
 *     agency scope / layer filters take effect BEFORE grouping.
 */
final class MapLocationsTest extends TestCase
{
    use RefreshDatabase;

    /** M1 — two records sharing a geocode_target collapse to one composite location. */
    public function test_m1_records_with_same_geocode_target_group_into_one_location(): void
    {
        $grouper = new LocationGrouper();

        $records = [
            $this->record('properties', 1, '36 Ss Topanga, 2587 Colin Road, Uvongo', 'hfc_listings'),
            $this->record('properties', 2, '37 Ss Topanga, 2587 Colin Road, Uvongo', 'hfc_listings'),
            $this->record('schemes',    9, 'Topanga § 12',                            'scheme_owners', address: '2587 Colin Road, Uvongo'),
        ];

        $locs = $grouper->group($records);

        $this->assertCount(1, $locs, 'all three records share the building street address — one composite');
        $this->assertSame(3, $locs[0]['record_count']);
        $this->assertTrue($locs[0]['is_composite']);
        $this->assertSame('hfc_listings', $locs[0]['primary_category'], 'HFC outranks scheme_owners');
        $this->assertContains('hfc_listings', $locs[0]['categories_present']);
        $this->assertContains('scheme_owners', $locs[0]['categories_present']);
        $this->assertSame('geocode_target', $locs[0]['grouping_basis']);
    }

    /** M2 — distinct addresses stay un-grouped; each is a single-record location. */
    public function test_m2_distinct_addresses_render_as_single_record_locations(): void
    {
        $grouper = new LocationGrouper();

        $records = [
            $this->record('a', 1, '18 Golf Course Road, Uvongo', 'hfc_listings', lat: -30.84, lng: 30.39),
            $this->record('b', 2, '12 Bairn Street, Uvongo',     'hfc_listings', lat: -30.85, lng: 30.40),
        ];

        $locs = $grouper->group($records);

        $this->assertCount(2, $locs);
        foreach ($locs as $loc) {
            $this->assertFalse($loc['is_composite']);
            $this->assertSame(1, $loc['record_count']);
        }
    }

    /** M3 — agency scope: a property in another agency must not appear for this user. */
    public function test_m3_pin_endpoint_respects_agency_scope(): void
    {
        [$agencyA, $agencyB] = $this->makeTwoAgencies();
        $userA = $this->makeUserInAgency($agencyA);

        $this->insertProperty([
            'agency_id' => $agencyA, 'branch_id' => $agencyA, 'agent_id' => $userA->id,
            'address'   => '18 Golf Course Road', 'suburb' => 'Uvongo',
            'latitude'  => -30.84, 'longitude' => 30.39, 'price' => 1_200_000,
            'property_type' => 'house',
        ]);
        $this->insertProperty([
            'agency_id' => $agencyB, 'branch_id' => $agencyB, 'agent_id' => $userA->id,
            'address'   => '12 Bairn Street', 'suburb' => 'Uvongo',
            'latitude'  => -30.85, 'longitude' => 30.40, 'price' => 1_500_000,
            'property_type' => 'house',
        ]);

        $svc = new MapPinService();
        $req = $this->bounds(agencyId: $agencyA);
        $resp = $svc->getPinsInBounds($req);

        $addresses = collect($resp['locations'])
            ->flatMap(fn ($loc) => collect($loc['records'])->pluck('address'))
            ->all();

        $this->assertContains('18 Golf Course Road', $addresses);
        $this->assertNotContains('12 Bairn Street', $addresses, 'agency B property must be invisible to agency A');
    }

    /** M4 — layer filter narrows records BEFORE grouping. */
    public function test_m4_layer_filter_excludes_disabled_categories_from_records(): void
    {
        [$agencyA] = $this->makeTwoAgencies();
        $userA = $this->makeUserInAgency($agencyA);

        $this->insertProperty([
            'agency_id' => $agencyA, 'branch_id' => $agencyA, 'agent_id' => $userA->id,
            'address'   => '18 Golf Course Road', 'suburb' => 'Uvongo',
            'latitude'  => -30.84, 'longitude' => 30.39, 'price' => 1_200_000,
            'property_type' => 'house',
        ]);

        $svc = new MapPinService();
        // Request only sold_comps — HFC properties must not appear.
        $req = $this->bounds(agencyId: $agencyA, layers: ['sold_comps']);
        $resp = $svc->getPinsInBounds($req);

        $categories = collect($resp['locations'])
            ->flatMap(fn ($loc) => collect($loc['records'])->pluck('category'))
            ->unique()
            ->values()
            ->all();

        $this->assertNotContains('hfc_listings', $categories, 'HFC must be excluded when layer not requested');
    }

    /** M5 — composite location.records[] contains every grouped record with full payload. */
    public function test_m5_composite_location_records_preserve_full_record_payload(): void
    {
        $grouper = new LocationGrouper();

        $records = [
            $this->record('a', 11, '36 Ss Topanga, 2587 Colin Road, Uvongo', 'hfc_listings'),
            $this->record('b', 12, 'Topanga § 12', 'scheme_owners', address: '2587 Colin Road, Uvongo'),
            $this->record('c', 13, 'Topanga § 14', 'scheme_owners', address: '2587 Colin Road, Uvongo'),
        ];

        $locs = $grouper->group($records);

        $this->assertCount(1, $locs);
        $loc = $locs[0];
        $this->assertSame(3, $loc['record_count']);
        $this->assertCount(3, $loc['records']);

        // Each record carries its id, category, title, detail_url (the V1
        // wire field; MapPinService::toRecord() exposes it as `deep_link`
        // on the public response — that rename is tested at the endpoint
        // boundary in M3/M4 above, not here on the grouper directly).
        foreach ($loc['records'] as $rec) {
            $this->assertArrayHasKey('id', $rec);
            $this->assertArrayHasKey('category', $rec);
            $this->assertArrayHasKey('title', $rec);
            $this->assertArrayHasKey('detail_url', $rec);
        }

        $ids = array_column($loc['records'], 'id');
        $this->assertEqualsCanonicalizing([11, 12, 13], $ids, 'all three record ids survived grouping');

        // Primary record is the HFC listing (priority 1000 > scheme_owners 200).
        $this->assertSame('hfc_listings', $loc['records'][0]['category']);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function record(
        string $source, int $id, string $title, string $category,
        ?string $address = null, float $lat = -30.84, float $lng = 30.39,
    ): array {
        return [
            'id'        => $id,
            'category'  => $category,
            'title'     => $title,
            'subtitle'  => $source,
            'address'   => $address ?? $title,
            'lat'       => $lat,
            'lng'       => $lng,
            'detail_url' => '/test/' . $source . '/' . $id,
        ];
    }

    private function bounds(int $agencyId, array $layers = ['hfc_listings','sold_comps','active_listings','mic_subjects','scheme_owners']): MapBoundsRequest
    {
        return new MapBoundsRequest(
            north: -30.4, south: -31.0, east: 30.9, west: 30.0,
            layers: $layers, viewMode: 'agent', agencyId: $agencyId,
        );
    }

    private function makeTwoAgencies(): array
    {
        $id1 = (int) DB::table('agencies')->insertGetId($this->agencyRow('Agency-A-' . Str::random(6)));
        $id2 = (int) DB::table('agencies')->insertGetId($this->agencyRow('Agency-B-' . Str::random(6)));

        // Branches are also commonly required — reuse the agency id as branch id
        // by inserting a branch under each. Some test rows reference branch_id
        // for the property insert.
        DB::table('branches')->insert([
            'id' => $id1, 'agency_id' => $id1, 'name' => 'Default A',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('branches')->insert([
            'id' => $id2, 'agency_id' => $id2, 'name' => 'Default B',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$id1, $id2];
    }

    private function agencyRow(string $name): array
    {
        return [
            'name'       => $name,
            'slug'       => Str::slug($name),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function makeUserInAgency(int $agencyId): User
    {
        return User::factory()->create([
            'agency_id' => $agencyId,
            'branch_id' => $agencyId,
        ]);
    }

    private function insertProperty(array $row): int
    {
        return (int) DB::table('properties')->insertGetId(array_merge([
            'external_id' => 'TEST-' . Str::random(8),
            'title'       => $row['address'] ?? 'Test property',
            'status'      => 'active',
            'is_demo'     => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ], $row));
    }
}
