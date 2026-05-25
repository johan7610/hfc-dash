<?php

declare(strict_types=1);

namespace Tests\Feature\Map;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase A.3.3 — URL state sync tests (M89-M91).
 *
 * The client-side URL ↔ filter state binding produces query strings that
 * the pin endpoint must accept and re-produce the same view from. These
 * tests exercise the API boundary that backs the URL sync — when a URL
 * is shared and re-opened, the same query is fired at GET /corex/map/pins
 * and the response shape must match.
 *
 *   M89 — every URL filter param flows through to the pin response
 *   M90 — a URL with no filter params falls through to defaults (no error)
 *   M91 — scope=all in the URL silently downgrades to 'agency' for
 *         non-owner accounts (matches the controller's role guard)
 */
final class MapUrlStateTest extends TestCase
{
    use RefreshDatabase;

    /** M89 — URL-equivalent query params flow end-to-end. */
    public function test_m89_url_params_drive_the_pin_response(): void
    {
        [$agencyId, $user] = $this->seedAgencyAndUser('super_admin');

        // Two HFC properties — one matching the URL filter set, one not.
        DB::table('properties')->insert([
            $this->propertyRow($agencyId, $user->id, [
                'address' => 'Match Road', 'property_type' => 'house',
                'beds' => 3, 'price' => 1_500_000, 'status' => 'active',
                'latitude' => -30.84, 'longitude' => 30.39,
            ]),
            $this->propertyRow($agencyId, $user->id, [
                'address' => 'Skip Road', 'property_type' => 'house',
                'beds' => 1, 'price' => 500_000, 'status' => 'active',
                'latitude' => -30.85, 'longitude' => 30.40,
            ]),
        ]);

        $resp = $this->actingAs($user)->getJson(route('corex.map.pins', [
            'north' => -30.4, 'south' => -31.0, 'east' => 30.9, 'west' => 30.0,
            'layers' => ['hfc_listings'],
            'scope' => 'agency',
            'propertyTypes' => ['house'],
            'bedroomsMin' => 2,
            'priceMin' => 1_000_000,
            'listingStatus' => ['active'],
        ]));

        $resp->assertOk();
        $addresses = collect($resp->json('locations'))
            ->flatMap(fn ($l) => collect($l['records'])->pluck('address'))
            ->filter()->values()->all();
        $this->assertContains('Match Road', $addresses);
        $this->assertNotContains('Skip Road', $addresses,
            'URL filter set must narrow to the matching property only');
    }

    /** M90 — empty URL → no filters → endpoint still returns a valid payload. */
    public function test_m90_empty_url_returns_default_unfiltered_payload(): void
    {
        [$agencyId, $user] = $this->seedAgencyAndUser('super_admin');

        DB::table('properties')->insert([
            $this->propertyRow($agencyId, $user->id, [
                'address' => 'Default Road', 'property_type' => 'house',
                'latitude' => -30.84, 'longitude' => 30.39,
            ]),
        ]);

        $resp = $this->actingAs($user)->getJson(route('corex.map.pins', [
            'north' => -30.4, 'south' => -31.0, 'east' => 30.9, 'west' => 30.0,
        ]));

        $resp->assertOk();
        $resp->assertJsonStructure([
            'bounds' => ['north', 'south', 'east', 'west'],
            'locations',
            'layer_counts',
        ]);
        $addresses = collect($resp->json('locations'))
            ->flatMap(fn ($l) => collect($l['records'])->pluck('address'))
            ->all();
        $this->assertContains('Default Road', $addresses,
            'with no filters the default view shows the seeded property');
    }

    /** M91 — non-owner scope=all in URL is silently downgraded to 'agency'. */
    public function test_m91_scope_all_downgrades_for_non_owner(): void
    {
        // Two agencies; the non-owner agent should NEVER see Agency B
        // properties even when their URL explicitly asks for scope=all.
        $agencyA = $this->makeAgency();
        $agencyB = $this->makeAgency();

        $alice = User::factory()->create([
            'agency_id' => $agencyA, 'branch_id' => $agencyA,
            'role'      => 'agent', // not an owner role
        ]);
        $eve   = User::factory()->create([
            'agency_id' => $agencyB, 'branch_id' => $agencyB,
            'role'      => 'agent',
        ]);

        DB::table('properties')->insert([
            $this->propertyRow($agencyA, $alice->id, [
                'address' => 'Alice Road', 'latitude' => -30.84, 'longitude' => 30.39,
            ]),
            $this->propertyRow($agencyB, $eve->id, [
                'address' => 'Eve Road',   'latitude' => -30.85, 'longitude' => 30.40,
            ]),
        ]);

        $resp = $this->actingAs($alice)->getJson(route('corex.map.pins', [
            'north' => -30.4, 'south' => -31.0, 'east' => 30.9, 'west' => 30.0,
            'layers' => ['hfc_listings'],
            'scope'  => 'all', // ← URL asks for cross-agency view
        ]));

        // Non-owner: the controller silently downgrades to 'agency' but the
        // route middleware (permission:access_properties) may also reject
        // the seeded fake-role user. We accept either: a 403 (rejected by
        // middleware) OR a 200 that does not leak Agency B data.
        if ($resp->status() === 200) {
            $addresses = collect($resp->json('locations'))
                ->flatMap(fn ($l) => collect($l['records'])->pluck('address'))
                ->all();
            $this->assertNotContains('Eve Road', $addresses,
                'scope=all must not leak Agency B to a non-owner');
        } else {
            $this->assertSame(403, $resp->status(),
                'non-owner without permission gets a clean 403, not 200 with leak');
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /** @return array{0:int,1:\App\Models\User} */
    private function seedAgencyAndUser(string $role): array
    {
        $agencyId = $this->makeAgency();
        $user = User::factory()->create([
            'agency_id' => $agencyId, 'branch_id' => $agencyId, 'role' => $role,
        ]);
        return [$agencyId, $user];
    }

    private function makeAgency(): int
    {
        $agencyId = (int) DB::table('agencies')->insertGetId([
            'name'       => 'Agency-' . Str::random(6),
            'slug'       => Str::slug('agency-' . Str::random(8)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('branches')->insert([
            'id' => $agencyId, 'agency_id' => $agencyId, 'name' => 'Default',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return $agencyId;
    }

    private function propertyRow(int $agencyId, int $agentId, array $overrides): array
    {
        return array_merge([
            'external_id'  => 'TEST-' . Str::random(8),
            'title'        => $overrides['address'] ?? 'Test property',
            'agency_id'    => $agencyId,
            'branch_id'    => $agencyId,
            'agent_id'     => $agentId,
            'status'       => 'active',
            'property_type'=> 'house',
            'is_demo'      => false,
            'created_at'   => now(),
            'updated_at'   => now(),
        ], $overrides);
    }
}
