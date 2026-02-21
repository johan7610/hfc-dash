<?php

namespace Tests\Unit\Presentations\Evidence;

use App\Services\Presentations\Evidence\Extractors\Property24SearchExtractorV1;
use App\Models\PortalCapture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Property24SearchExtractorV1Test extends TestCase
{
    use RefreshDatabase;

    private Property24SearchExtractorV1 $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new Property24SearchExtractorV1();
    }

    public function test_extract_from_fixture_html(): void
    {
        $html = file_get_contents(base_path('tests/Fixtures/property24_search.html'));

        Storage::fake('local');
        Storage::disk('local')->put('portal_captures/1.html', $html);

        $capture = PortalCapture::factory()->create([
            'raw_html_path' => 'portal_captures/1.html',
            'source_site'   => 'www.property24.com',
            'page_type'     => 'search',
            'captured_at'   => now(),
        ]);

        $result = $this->extractor->extract($capture);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('search', $result);
        $this->assertArrayHasKey('_extraction', $result);

        // 3 listings in fixture
        $this->assertEquals(3, $result['search']['items_on_page']);
        $this->assertEquals('p24_search_dom_v1', $result['_extraction']['extractor']);
        $this->assertEquals('stored_html', $result['_extraction']['source']);

        $items = $result['search']['items'];
        $this->assertCount(3, $items);

        // Listing 1: R 1 299 990, 4 bed, 2 bath, erf 1950
        $this->assertEquals('116765021', $items[0]['portal_listing_id']);
        $this->assertEquals(1299990, $items[0]['price']);
        $this->assertEquals('ZAR', $items[0]['currency']);
        $this->assertEquals(4, $items[0]['beds']);
        $this->assertEquals(2, $items[0]['baths']);
        $this->assertEquals(1950, $items[0]['erf_m2']);
        $this->assertStringContainsString('property24.com', $items[0]['url']);
        $this->assertStringContainsString('116765021', $items[0]['url']);

        // Listing 2: R 1 750 000, 4 bed, 3 bath, floor 220m²
        $this->assertEquals('116800001', $items[1]['portal_listing_id']);
        $this->assertEquals(1750000, $items[1]['price']);
        $this->assertEquals(4, $items[1]['beds']);
        $this->assertEquals(3, $items[1]['baths']);
        $this->assertEquals(220, $items[1]['size_m2']);

        // Listing 3: R 1 995 000, 5 bed, 2 bath, no size
        $this->assertEquals('116900002', $items[2]['portal_listing_id']);
        $this->assertEquals(1995000, $items[2]['price']);
        $this->assertEquals(5, $items[2]['beds']);
        $this->assertEquals(2, $items[2]['baths']);
        $this->assertNull($items[2]['size_m2']);
    }

    public function test_deduplication_by_portal_listing_id(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html><body>
<div class="p24_regularTile" data-listing-number="111111">
    <a href="/for-sale/test/111111"><div class="p24_price">R 1 000 000</div></a>
</div>
<div class="p24_regularTile" data-listing-number="111111">
    <a href="/for-sale/test/111111"><div class="p24_price">R 1 000 000</div></a>
</div>
<div class="p24_regularTile" data-listing-number="222222">
    <a href="/for-sale/test/222222"><div class="p24_price">R 2 000 000</div></a>
</div>
</body></html>
HTML;

        Storage::fake('local');
        Storage::disk('local')->put('portal_captures/2.html', $html);

        $capture = PortalCapture::factory()->create([
            'raw_html_path' => 'portal_captures/2.html',
            'source_site'   => 'www.property24.com',
            'page_type'     => 'search',
            'captured_at'   => now(),
        ]);

        $result = $this->extractor->extract($capture);

        $this->assertNotNull($result);
        // Deduped: 2 unique listings, not 3
        $this->assertEquals(2, $result['search']['items_on_page']);
    }

    public function test_null_fields_when_missing(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html><body>
<div class="p24_regularTile" data-listing-number="333333">
    <a href="/for-sale/test/333333"><div class="p24_price">R 500 000</div></a>
</div>
</body></html>
HTML;

        Storage::fake('local');
        Storage::disk('local')->put('portal_captures/3.html', $html);

        $capture = PortalCapture::factory()->create([
            'raw_html_path' => 'portal_captures/3.html',
            'source_site'   => 'www.property24.com',
            'page_type'     => 'search',
            'captured_at'   => now(),
        ]);

        $result = $this->extractor->extract($capture);

        $this->assertNotNull($result);
        $item = $result['search']['items'][0];

        $this->assertEquals(500000, $item['price']);
        $this->assertNull($item['beds']);
        $this->assertNull($item['baths']);
        $this->assertNull($item['parking']);
        $this->assertNull($item['size_m2']);
        $this->assertNull($item['erf_m2']);
    }

    public function test_total_count_extraction(): void
    {
        $html = file_get_contents(base_path('tests/Fixtures/property24_search.html'));

        Storage::fake('local');
        Storage::disk('local')->put('portal_captures/4.html', $html);

        $capture = PortalCapture::factory()->create([
            'raw_html_path' => 'portal_captures/4.html',
            'source_site'   => 'www.property24.com',
            'page_type'     => 'search',
            'captured_at'   => now(),
        ]);

        $result = $this->extractor->extract($capture);

        // Fixture has "Showing : 1 - 20 of 50"
        $this->assertEquals(50, $result['search']['total_count']);
    }

    public function test_url_normalized_to_absolute(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html><body>
<div class="p24_regularTile" data-listing-number="444444">
    <a href="/for-sale/uvongo/margate/kwazulu-natal/6359/444444?plId=123">
        <div class="p24_price">R 900 000</div>
    </a>
</div>
</body></html>
HTML;

        Storage::fake('local');
        Storage::disk('local')->put('portal_captures/5.html', $html);

        $capture = PortalCapture::factory()->create([
            'raw_html_path' => 'portal_captures/5.html',
            'source_site'   => 'www.property24.com',
            'page_type'     => 'search',
            'captured_at'   => now(),
        ]);

        $result = $this->extractor->extract($capture);
        $item = $result['search']['items'][0];

        $this->assertStringStartsWith('https://www.property24.com/', $item['url']);
        $this->assertStringNotContainsString('?', $item['url']);
    }
}
