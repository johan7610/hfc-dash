<?php

namespace Tests\Feature\Presentation;

use App\Models\PortalCapture;
use App\Models\PortalListing;
use App\Models\PortalListingObservation;
use App\Services\PortalListingTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalListingTrackingTest extends TestCase
{
    use RefreshDatabase;

    private PortalListingTrackingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PortalListingTrackingService();
    }

    public function test_new_listings_are_created(): void
    {
        $capture = PortalCapture::factory()->create([
            'source_site' => 'www.property24.com',
            'page_type'   => 'search',
            'captured_at' => now(),
        ]);

        $items = [
            [
                'portal_listing_id' => '116765021',
                'url'               => 'https://www.property24.com/for-sale/uvongo/116765021',
                'price'             => 1299990,
                'beds'              => 4,
                'baths'             => 2,
                'parking'           => null,
                'size_m2'           => null,
                'erf_m2'            => 1950,
                'title'             => '4 Bedroom House in Uvongo',
            ],
        ];

        $summary = $this->service->processItems($capture, 'www.property24.com', $items);

        $this->assertEquals(1, $summary['processed']);
        $this->assertEquals(1, $summary['new']);
        $this->assertEquals(0, $summary['updated']);
        $this->assertEquals(0, $summary['price_changes']);

        // Portal listing created
        $listing = PortalListing::where('source_site', 'www.property24.com')
            ->where('portal_listing_id', '116765021')
            ->first();

        $this->assertNotNull($listing);
        $this->assertEquals(1299990, $listing->current_fields_json['price']);
        $this->assertEquals(4, $listing->current_fields_json['beds']);
        $this->assertEquals($capture->id, $listing->last_capture_id);

        // Observation created
        $obs = PortalListingObservation::where('portal_listing_id', $listing->id)->first();
        $this->assertNotNull($obs);
        $this->assertEquals($capture->id, $obs->capture_id);
        $this->assertNull($obs->changed_fields_json);
    }

    public function test_existing_listing_is_reused_no_duplicates(): void
    {
        $capture1 = PortalCapture::factory()->create([
            'source_site' => 'www.property24.com',
            'page_type'   => 'search',
            'captured_at' => now()->subDay(),
        ]);

        $capture2 = PortalCapture::factory()->create([
            'source_site' => 'www.property24.com',
            'page_type'   => 'search',
            'captured_at' => now(),
        ]);

        $items = [
            [
                'portal_listing_id' => '116765021',
                'url'               => 'https://www.property24.com/for-sale/uvongo/116765021',
                'price'             => 1299990,
                'beds'              => 4,
                'baths'             => 2,
                'parking'           => null,
                'size_m2'           => null,
                'erf_m2'            => null,
                'title'             => '4 Bedroom House',
            ],
        ];

        // First capture
        $this->service->processItems($capture1, 'www.property24.com', $items);

        // Second capture — same listing, same data
        $summary = $this->service->processItems($capture2, 'www.property24.com', $items);

        $this->assertEquals(1, $summary['processed']);
        $this->assertEquals(0, $summary['new']);
        $this->assertEquals(1, $summary['updated']);
        $this->assertEquals(0, $summary['price_changes']);

        // Only ONE portal_listings row
        $count = PortalListing::where('source_site', 'www.property24.com')
            ->where('portal_listing_id', '116765021')
            ->count();
        $this->assertEquals(1, $count);

        // Two observations (one per capture)
        $listing = PortalListing::where('portal_listing_id', '116765021')->first();
        $this->assertEquals(2, $listing->observations()->count());
    }

    public function test_price_change_detected_and_recorded(): void
    {
        $capture1 = PortalCapture::factory()->create([
            'source_site' => 'www.property24.com',
            'page_type'   => 'search',
            'captured_at' => now()->subDay(),
        ]);

        $capture2 = PortalCapture::factory()->create([
            'source_site' => 'www.property24.com',
            'page_type'   => 'search',
            'captured_at' => now(),
        ]);

        // First capture: price 2,100,000
        $items1 = [
            [
                'portal_listing_id' => '116815341',
                'url'               => 'https://www.property24.com/for-sale/test/116815341',
                'price'             => 2100000,
                'beds'              => 3,
                'baths'             => 2,
                'parking'           => null,
                'size_m2'           => 180,
                'erf_m2'            => null,
                'title'             => '3 Bedroom House',
            ],
        ];

        $this->service->processItems($capture1, 'www.property24.com', $items1);

        // Second capture: price dropped to 1,895,000
        $items2 = [
            [
                'portal_listing_id' => '116815341',
                'url'               => 'https://www.property24.com/for-sale/test/116815341',
                'price'             => 1895000,
                'beds'              => 3,
                'baths'             => 2,
                'parking'           => null,
                'size_m2'           => 180,
                'erf_m2'            => null,
                'title'             => '3 Bedroom House',
            ],
        ];

        $summary = $this->service->processItems($capture2, 'www.property24.com', $items2);

        $this->assertEquals(1, $summary['price_changes']);

        // Verify the observation has the delta
        $listing = PortalListing::where('portal_listing_id', '116815341')->first();
        $this->assertNotNull($listing);

        // Current fields updated to new price
        $this->assertEquals(1895000, $listing->current_fields_json['price']);
        $this->assertEquals($capture2->id, $listing->last_capture_id);

        // The second observation has changed_fields_json with price old/new
        $obs = PortalListingObservation::where('portal_listing_id', $listing->id)
            ->where('capture_id', $capture2->id)
            ->first();
        $this->assertNotNull($obs);
        $this->assertNotNull($obs->changed_fields_json);
        $this->assertEquals(2100000, $obs->changed_fields_json['price']['old']);
        $this->assertEquals(1895000, $obs->changed_fields_json['price']['new']);
    }

    public function test_merge_preserves_non_null_fields(): void
    {
        $capture1 = PortalCapture::factory()->create([
            'source_site' => 'www.property24.com',
            'page_type'   => 'search',
            'captured_at' => now()->subDay(),
        ]);

        $capture2 = PortalCapture::factory()->create([
            'source_site' => 'www.property24.com',
            'page_type'   => 'search',
            'captured_at' => now(),
        ]);

        // First capture: full data including size
        $items1 = [
            [
                'portal_listing_id' => '555555',
                'url'               => 'https://www.property24.com/for-sale/test/555555',
                'price'             => 1500000,
                'beds'              => 3,
                'baths'             => 2,
                'parking'           => null,
                'size_m2'           => 150,
                'erf_m2'            => null,
                'title'             => 'Test',
            ],
        ];

        $this->service->processItems($capture1, 'www.property24.com', $items1);

        // Second capture: partial data (search page has no size_m2)
        $items2 = [
            [
                'portal_listing_id' => '555555',
                'url'               => 'https://www.property24.com/for-sale/test/555555',
                'price'             => 1500000,
                'beds'              => 3,
                'baths'             => null,
                'parking'           => null,
                'size_m2'           => null,
                'erf_m2'            => null,
                'title'             => 'Test',
            ],
        ];

        $this->service->processItems($capture2, 'www.property24.com', $items2);

        $listing = PortalListing::where('portal_listing_id', '555555')->first();

        // size_m2 should still be 150 (not overwritten by null)
        $this->assertEquals(150, $listing->current_fields_json['size_m2']);
        // baths should still be 2 (not overwritten by null)
        $this->assertEquals(2, $listing->current_fields_json['baths']);
    }

    public function test_all_changes_link_back_to_capture_id(): void
    {
        $capture = PortalCapture::factory()->create([
            'source_site' => 'www.property24.com',
            'page_type'   => 'search',
            'captured_at' => now(),
        ]);

        $items = [
            [
                'portal_listing_id' => '999999',
                'url'               => 'https://www.property24.com/for-sale/test/999999',
                'price'             => 800000,
                'beds'              => 2,
                'baths'             => 1,
                'parking'           => null,
                'size_m2'           => null,
                'erf_m2'            => null,
                'title'             => 'Test',
            ],
        ];

        $this->service->processItems($capture, 'www.property24.com', $items);

        $obs = PortalListingObservation::where('capture_id', $capture->id)->first();
        $this->assertNotNull($obs);
        $this->assertEquals($capture->id, $obs->capture_id);

        $listing = PortalListing::where('portal_listing_id', '999999')->first();
        $this->assertEquals($capture->id, $listing->last_capture_id);
    }
}
