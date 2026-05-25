<?php

declare(strict_types=1);

namespace Tests\Feature\Map;

use App\Models\Map\MapSavedSearch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase A.3.2 — saved-search CRUD tests (M84-M88).
 *
 *   M84 — store creates a user-scoped row with a JSON payload
 *   M85 — index returns only the caller's rows (per-user isolation)
 *   M86 — setting is_default unsets it on the user's prior default
 *   M87 — store rejects a duplicate name for the same user
 *   M88 — destroy soft-deletes and the row drops out of index
 */
final class MapSavedSearchTest extends TestCase
{
    use RefreshDatabase;

    /** M84 — POST creates the row with payload intact. */
    public function test_m84_store_creates_user_scoped_saved_search(): void
    {
        $user = $this->makeUser();

        $resp = $this->actingAs($user)->postJson(route('corex.map.saved-searches.store'), [
            'name'           => 'Margate houses 1-2m',
            'filter_payload' => [
                'scope' => 'agency',
                'types' => ['house'],
                'priceMin' => 1_000_000,
                'priceMax' => 2_000_000,
            ],
            'is_default' => false,
        ]);

        $resp->assertCreated();
        $body = $resp->json();
        $this->assertNotEmpty($body['saved_search']['id']);
        $this->assertSame('Margate houses 1-2m', $body['saved_search']['name']);

        $row = MapSavedSearch::withoutGlobalScopes()->find($body['saved_search']['id']);
        $this->assertNotNull($row);
        $this->assertSame((int) $user->agency_id, (int) $row->agency_id);
        $this->assertSame((int) $user->id, (int) $row->user_id);
        $this->assertSame('agency', $row->filter_payload['scope']);
    }

    /** M85 — index isolates by user_id even within the same agency. */
    public function test_m85_index_is_per_user(): void
    {
        $alice = $this->makeUser();
        $bob   = $this->makeUser($alice->agency_id);

        MapSavedSearch::create([
            'agency_id' => $alice->agency_id, 'user_id' => $alice->id,
            'name' => 'Alice search', 'filter_payload' => ['scope' => 'my'], 'is_default' => false,
        ]);
        MapSavedSearch::create([
            'agency_id' => $bob->agency_id, 'user_id' => $bob->id,
            'name' => 'Bob search', 'filter_payload' => ['scope' => 'my'], 'is_default' => false,
        ]);

        $resp = $this->actingAs($alice)->getJson(route('corex.map.saved-searches.index'));
        $resp->assertOk();
        $names = collect($resp->json('saved_searches'))->pluck('name')->all();
        $this->assertContains('Alice search', $names);
        $this->assertNotContains('Bob search', $names,
            'index must NOT leak another user\'s saved searches');
    }

    /** M86 — promoting one search to default unsets the user's prior default. */
    public function test_m86_setting_default_unsets_prior_default(): void
    {
        $user = $this->makeUser();

        $first  = MapSavedSearch::create([
            'agency_id' => $user->agency_id, 'user_id' => $user->id,
            'name' => 'First', 'filter_payload' => [], 'is_default' => true,
        ]);
        $second = MapSavedSearch::create([
            'agency_id' => $user->agency_id, 'user_id' => $user->id,
            'name' => 'Second', 'filter_payload' => [], 'is_default' => false,
        ]);

        $resp = $this->actingAs($user)->patchJson(
            route('corex.map.saved-searches.update', ['id' => $second->id]),
            ['is_default' => true]
        );
        $resp->assertOk();

        $this->assertFalse((bool) $first->fresh()->is_default,
            'prior default must be cleared');
        $this->assertTrue((bool) $second->fresh()->is_default);
    }

    /** M87 — duplicate name for the same user is rejected with 422. */
    public function test_m87_duplicate_name_per_user_is_rejected(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->postJson(route('corex.map.saved-searches.store'), [
            'name'           => 'My favourites',
            'filter_payload' => ['scope' => 'my'],
        ])->assertCreated();

        $this->actingAs($user)->postJson(route('corex.map.saved-searches.store'), [
            'name'           => 'My favourites',
            'filter_payload' => ['scope' => 'agency'],
        ])->assertStatus(422);
    }

    /** M88 — destroy soft-deletes and the row vanishes from index. */
    public function test_m88_destroy_soft_deletes(): void
    {
        $user = $this->makeUser();
        $row  = MapSavedSearch::create([
            'agency_id' => $user->agency_id, 'user_id' => $user->id,
            'name' => 'Doomed', 'filter_payload' => [], 'is_default' => false,
        ]);

        $this->actingAs($user)
            ->deleteJson(route('corex.map.saved-searches.destroy', ['id' => $row->id]))
            ->assertOk();

        $this->assertSoftDeleted('map_saved_searches', ['id' => $row->id]);

        $names = collect(
            $this->actingAs($user)->getJson(route('corex.map.saved-searches.index'))->json('saved_searches')
        )->pluck('name')->all();
        $this->assertNotContains('Doomed', $names);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function makeUser(?int $agencyId = null): User
    {
        if ($agencyId === null) {
            $agencyId = (int) DB::table('agencies')->insertGetId([
                'name'       => 'Agency-' . Str::random(6),
                'slug'       => Str::random(8),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('branches')->insert([
                'id' => $agencyId, 'agency_id' => $agencyId, 'name' => 'Default',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        // role=super_admin bypasses the permission middleware via Role.is_owner.
        return User::factory()->create([
            'agency_id' => $agencyId,
            'branch_id' => $agencyId,
            'role'      => 'super_admin',
        ]);
    }
}
