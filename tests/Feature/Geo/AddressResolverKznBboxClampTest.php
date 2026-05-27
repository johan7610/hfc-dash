<?php

declare(strict_types=1);

namespace Tests\Feature\Geo;

use App\Models\Geocoding\GeocodingCache;
use App\Services\Geocoding\AddressResolverService;
use App\Services\Geocoding\GeocodeRateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Quality-protection clamps on AddressResolverService::callGoogle().
 *
 * Two layers of defence against ambiguous-suburb mis-resolves:
 *
 *   (a) KZN bounding-box clamp — Google's `bounds=` parameter is a
 *       viewport-biasing hint, not a strict constraint, so a strict
 *       post-resolution clamp catches:
 *         - SA centroid pins (~ -30.56, 22.94 Karoo) — Google's
 *           fallback when no specific match is found
 *         - Wrong-city resolves (Johannesburg, Cape Town, PE,
 *           Polokwane, etc.)
 *         - Out-of-country outliers (NZ row id=379 during the
 *           2026-05-27 backfill — "Rocklands" exists in Auckland)
 *       Out-of-bbox results return a DEFINITIVE failure with
 *       failure_reason='google:out_of_bbox' (cached) so future calls
 *       with the same normalised key short-circuit immediately —
 *       no repeat Google billing for known-bad addresses, distinct
 *       reason for operator filtering.
 *
 *   (b) partial_match downgrade — Google sets partial_match=true when
 *       it had to guess at part of the address (fuzzed street name,
 *       dropped unit number, etc.). We downgrade confidence to 'suburb'
 *       so a partial match never carries street/exact precision.
 *       Downgrade-only: never upgrades.
 */
final class AddressResolverKznBboxClampTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Config::set('geo.geocoding.enabled', true);
        Config::set('geo.geocoding.admin_override_enabled', false);
        Config::set('geo.geocoding.environment_daily_cap', 100);
        Config::set('geo.geocoding.user_daily_cap', 100);
        Config::set('services.google.geocoding_api_key', 'fake-test-key');
        Config::set('services.nominatim.enabled', false);
        GeocodeRateLimiter::releaseRuntimeOverride();
    }

    /** @dataProvider outOfBboxProvider */
    public function test_out_of_bbox_google_result_is_rejected_and_cached_as_definitive_failure(
        float $lat,
        float $lng,
        string $resolvedAddress,
        string $caseLabel,
    ): void {
        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status'  => 'OK',
                'results' => [[
                    'formatted_address'  => $resolvedAddress,
                    'geometry'           => [
                        'location'      => ['lat' => $lat, 'lng' => $lng],
                        'location_type' => 'APPROXIMATE',
                    ],
                    'address_components' => [],
                ]],
            ], 200),
        ]);

        $service = app(AddressResolverService::class);
        $result  = $service->resolve('test address', 'SomeSuburb', null);

        $this->assertSame('failed', $result->confidence,
            "$caseLabel: bbox-rejected result must return failed");
        $this->assertSame('google', $result->source,
            "$caseLabel: source is 'google' (definitive failure routed via cacheAndReturn)");
        $this->assertSame('google:out_of_bbox', $result->failureReason,
            "$caseLabel: distinctive failure_reason for operator filtering");

        // Cached so future calls with the same normalised key short-circuit
        // immediately (no repeat Google billing for known-bad addresses).
        $row = GeocodingCache::where('address_normalised', 'test address somesuburb')->first();
        $this->assertNotNull($row, "$caseLabel: bbox-rejected definitive failure must be cached");
        $this->assertSame('failed',              $row->confidence);
        $this->assertSame('google',              $row->source);
        $this->assertSame('google:out_of_bbox',  $row->failure_reason);
    }

    public static function outOfBboxProvider(): array
    {
        return [
            'SA centroid Karoo'   => [-30.5594820, 22.9375060, 'Karoo, South Africa', 'centroid'],
            'Johannesburg'        => [-26.2041,    28.0473,    'Johannesburg, South Africa', 'jhb'],
            'Cape Town'           => [-33.9249,    18.4241,    'Cape Town, South Africa', 'cpt'],
            'Pretoria'            => [-25.7479,    28.2293,    'Pretoria, South Africa', 'pta'],
            'Polokwane'           => [-23.9045,    29.4689,    'Polokwane, South Africa', 'polokwane'],
            'New Zealand outlier' => [-37.0766,    174.9500,   'Auckland, New Zealand', 'nz'],
        ];
    }

    /** @dataProvider inBboxProvider */
    public function test_in_bbox_google_result_is_accepted_and_cached(
        float $lat,
        float $lng,
        string $resolvedAddress,
        string $caseLabel,
    ): void {
        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status'  => 'OK',
                'results' => [[
                    'formatted_address'  => $resolvedAddress,
                    'geometry'           => [
                        'location'      => ['lat' => $lat, 'lng' => $lng],
                        'location_type' => 'APPROXIMATE',
                    ],
                    'address_components' => [],
                ]],
            ], 200),
        ]);

        $service = app(AddressResolverService::class);
        $result  = $service->resolve('test address', 'SomeSuburb', null);

        $this->assertSame('google', $result->source, "$caseLabel: source should be google");
        $this->assertEqualsWithDelta($lat, (float) $result->latitude, 0.0001,
            "$caseLabel: latitude preserved");
        $this->assertEqualsWithDelta($lng, (float) $result->longitude, 0.0001,
            "$caseLabel: longitude preserved");
        $this->assertSame(
            1,
            GeocodingCache::where('address_normalised', 'test address somesuburb')->count(),
            "$caseLabel: in-bbox result must be cached",
        );
    }

    public static function inBboxProvider(): array
    {
        return [
            'KZN South Coast — Margate'   => [-30.8627, 30.3719, 'Margate, KwaZulu-Natal',   'south_coast'],
            'KZN North Coast — Ballito'   => [-29.5396, 31.2147, 'Ballito, KwaZulu-Natal',   'north_coast'],
            'KZN inland — Pietermaritzburg'=> [-29.6094, 30.3781, 'Pietermaritzburg, KZN',    'pmb'],
            'KZN northern — Pongola'       => [-27.4000, 31.6167, 'Pongola, KwaZulu-Natal',   'pongola'],
            'KZN southern — Port Edward'   => [-31.0500, 30.2167, 'Port Edward, KwaZulu-Natal','port_edward'],
        ];
    }

    public function test_partial_match_downgrades_rooftop_confidence_to_suburb(): void
    {
        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status'  => 'OK',
                'results' => [[
                    'formatted_address'  => 'Margate, KZN',
                    'partial_match'      => true,
                    'geometry'           => [
                        'location'      => ['lat' => -30.8627, 'lng' => 30.3719],
                        'location_type' => 'ROOFTOP',  // Google's strongest precision
                    ],
                    'address_components' => [],
                ]],
            ], 200),
        ]);

        $result = app(AddressResolverService::class)->resolve('test address', 'Margate', null);

        // ROOFTOP normally maps to 'exact'. partial_match=true downgrades to 'suburb'.
        $this->assertSame('suburb', $result->confidence,
            'partial_match=true must downgrade ROOFTOP precision to suburb');
        $this->assertSame('google', $result->source);
    }

    public function test_partial_match_downgrades_street_confidence_to_suburb(): void
    {
        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status'  => 'OK',
                'results' => [[
                    'formatted_address'  => 'Margate, KZN',
                    'partial_match'      => true,
                    'geometry'           => [
                        'location'      => ['lat' => -30.8627, 'lng' => 30.3719],
                        'location_type' => 'GEOMETRIC_CENTER',  // would map to 'street'
                    ],
                    'address_components' => [],
                ]],
            ], 200),
        ]);

        $result = app(AddressResolverService::class)->resolve('test address', 'Margate', null);

        $this->assertSame('suburb', $result->confidence,
            'partial_match=true must downgrade GEOMETRIC_CENTER precision to suburb');
    }

    public function test_partial_match_does_not_upgrade_already_suburb_confidence(): void
    {
        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status'  => 'OK',
                'results' => [[
                    'formatted_address'  => 'Margate, KZN',
                    'partial_match'      => true,
                    'geometry'           => [
                        'location'      => ['lat' => -30.8627, 'lng' => 30.3719],
                        'location_type' => 'APPROXIMATE',  // already maps to 'suburb'
                    ],
                    'address_components' => [],
                ]],
            ], 200),
        ]);

        $result = app(AddressResolverService::class)->resolve('test address', 'Margate', null);

        $this->assertSame('suburb', $result->confidence,
            'already-suburb confidence stays suburb under partial_match');
    }

    public function test_no_partial_match_preserves_exact_confidence(): void
    {
        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status'  => 'OK',
                'results' => [[
                    'formatted_address'  => 'Margate, KZN',
                    // No partial_match key → treated as false
                    'geometry'           => [
                        'location'      => ['lat' => -30.8627, 'lng' => 30.3719],
                        'location_type' => 'ROOFTOP',
                    ],
                    'address_components' => [],
                ]],
            ], 200),
        ]);

        $result = app(AddressResolverService::class)->resolve('test address', 'Margate', null);

        $this->assertSame('exact', $result->confidence,
            'absent partial_match must NOT downgrade — ROOFTOP stays as exact');
    }

    public function test_bounds_param_is_sent_in_google_request(): void
    {
        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status'  => 'OK',
                'results' => [[
                    'formatted_address'  => 'Margate',
                    'geometry'           => [
                        'location'      => ['lat' => -30.8627, 'lng' => 30.3719],
                        'location_type' => 'APPROXIMATE',
                    ],
                    'address_components' => [],
                ]],
            ], 200),
        ]);

        app(AddressResolverService::class)->resolve('test address', 'Margate', null);

        Http::assertSent(function ($request) {
            $url = $request->url();
            return str_contains($url, 'bounds=')
                && str_contains($url, '-32')      // SW corner lat
                && str_contains($url, '28.5')     // SW corner lng
                && str_contains($url, '-27')      // NE corner lat
                && str_contains($url, '33')       // NE corner lng
                && str_contains($url, 'region=za');
        });
    }
}
