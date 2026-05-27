<?php

declare(strict_types=1);

namespace Tests\Feature\Map;

use App\Services\Map\LocationGrouper;
use Tests\TestCase;

/**
 * Phase A.2.6 — hover_summary cascade tests M66-M71. Pure-unit against
 * LocationGrouper::group(); no DB needed.
 */
final class HoverSummaryTest extends TestCase
{
    /** M66 — HFC active listing composite → HFC details headline + +N footer. */
    public function test_m66_hfc_active_listing_composite_summary(): void
    {
        $grouper = new LocationGrouper();
        $locs = $grouper->group([
            $this->rec(1, '12 Hibiscus Road, Uvongo', 'hfc_listings',
                subtitle: 'house · R 1,420,000', lat: -30.84, lng: 30.39),
            $this->rec(2, '12 Hibiscus Road, Uvongo', 'sold_comps',
                subtitle: 'Sold R 1,300,000 · May 2024', lat: -30.84, lng: 30.39),
            $this->rec(3, '12 Hibiscus Road, Uvongo', 'mic_subjects',
                subtitle: 'CMA · May 2024', lat: -30.84, lng: 30.39),
        ]);

        $this->assertCount(1, $locs);
        $hs = $locs[0]['hover_summary'];
        $this->assertSame('12 Hibiscus Road, Uvongo', $hs['title']);
        $this->assertStringContainsString('HFC', $hs['subtitle']);
        $this->assertStringContainsString('R 1,420,000', $hs['subtitle']);
        $this->assertSame('+2 other records', $hs['footer']);
    }

    /** M67 — sectional schemes only → scheme name + N units, no footer. */
    public function test_m67_scheme_only_summary(): void
    {
        $grouper = new LocationGrouper();
        $locs = $grouper->group([
            $this->rec(11, 'Sunset Manor § 1', 'scheme_owners', address: 'Sunset Manor § 1'),
            $this->rec(12, 'Sunset Manor § 2', 'scheme_owners', address: 'Sunset Manor § 2'),
            $this->rec(13, 'Sunset Manor § 3', 'scheme_owners', address: 'Sunset Manor § 3'),
            $this->rec(14, 'Sunset Manor § 4', 'scheme_owners', address: 'Sunset Manor § 4'),
        ]);

        $this->assertCount(1, $locs);
        $hs = $locs[0]['hover_summary'];
        $this->assertSame('Sunset Manor', $hs['title']);
        $this->assertSame('4 units', $hs['subtitle']);
        $this->assertSame('', $hs['footer']);
    }

    /** M68 — same-category multi (5 sold comps) → category-specific summary. */
    public function test_m68_same_category_multi_summary(): void
    {
        $grouper = new LocationGrouper();
        $locs = $grouper->group([
            $this->rec(21, '7 Marine Drive', 'sold_comps', date: '2024-05-12'),
            $this->rec(22, '7 Marine Drive', 'sold_comps', date: '2024-02-03'),
            $this->rec(23, '7 Marine Drive', 'sold_comps', date: '2023-11-30'),
            $this->rec(24, '7 Marine Drive', 'sold_comps', date: '2023-08-15'),
            $this->rec(25, '7 Marine Drive', 'sold_comps', date: '2022-12-01'),
        ]);

        $hs = $locs[0]['hover_summary'];
        $this->assertSame('7 Marine Drive', $hs['title']);
        $this->assertStringContainsString('5 sold comps', $hs['subtitle']);
        $this->assertStringContainsString('most recent 12 May 2024', $hs['subtitle']);
    }

    /** M69 — mixed categories → category breakdown. */
    public function test_m69_mixed_categories_summary(): void
    {
        $grouper = new LocationGrouper();
        // No HFC listing here — otherwise it takes priority. Mix sold + MIC + portal.
        $locs = $grouper->group([
            $this->rec(31, '14 Bairn Street', 'sold_comps'),
            $this->rec(32, '14 Bairn Street', 'sold_comps'),
            $this->rec(33, '14 Bairn Street', 'mic_subjects'),
            $this->rec(34, '14 Bairn Street', 'active_listings'),
        ]);

        $hs = $locs[0]['hover_summary'];
        $this->assertStringContainsString('4 records', $hs['subtitle']);
        $this->assertStringContainsString('2 sold', $hs['subtitle']);
        $this->assertStringContainsString('1 MIC', $hs['subtitle']);
        $this->assertStringContainsString('1 portal', $hs['subtitle']);
    }

    /** M70 — single pin still gets hover_summary (consistent template). */
    public function test_m70_single_pin_summary_consistent(): void
    {
        $grouper = new LocationGrouper();
        $locs = $grouper->group([
            $this->rec(41, '18 Golf Course Road', 'hfc_listings',
                subtitle: 'house · R 1,200,000', lat: -30.84, lng: 30.39),
        ]);

        $this->assertCount(1, $locs);
        $hs = $locs[0]['hover_summary'];
        $this->assertArrayHasKey('title', $hs);
        $this->assertArrayHasKey('subtitle', $hs);
        $this->assertArrayHasKey('footer', $hs);
        $this->assertSame('18 Golf Course Road', $hs['title']);
        $this->assertSame('house · R 1,200,000', $hs['subtitle']);
        $this->assertSame('', $hs['footer']);
    }

    /** M71 — "THIS ADDRESS" must never appear in any hover_summary,
     *        case-insensitive, across all priority paths. */
    public function test_m71_no_this_address_placeholder_ever(): void
    {
        $grouper = new LocationGrouper();
        $locs = $grouper->group([
            // Mix scenarios — single, scheme, mixed.
            $this->rec(1, '12 Hibiscus Road, Uvongo', 'hfc_listings', lat: -30.84, lng: 30.39),
            $this->rec(2, '12 Hibiscus Road, Uvongo', 'sold_comps',   lat: -30.84, lng: 30.39),
            $this->rec(11, 'Atlantis § 1', 'scheme_owners', address: 'Atlantis § 1', lat: -30.85, lng: 30.40),
            $this->rec(12, 'Atlantis § 2', 'scheme_owners', address: 'Atlantis § 2', lat: -30.85, lng: 30.40),
            $this->rec(21, '18 Golf Course Road', 'sold_comps', lat: -30.86, lng: 30.41),
        ]);

        foreach ($locs as $loc) {
            $blob = ($loc['hover_summary']['title'] ?? '') . ' '
                  . ($loc['hover_summary']['subtitle'] ?? '') . ' '
                  . ($loc['hover_summary']['footer'] ?? '');
            $this->assertDoesNotMatchRegularExpression('/this address/i', $blob,
                'hover_summary still contains a "THIS ADDRESS" placeholder somewhere');
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function rec(
        int $id, string $title, string $category,
        ?string $address = null, ?string $subtitle = null, ?string $date = null,
        float $lat = -30.84, float $lng = 30.39,
    ): array {
        return [
            'id'         => $id,
            'category'   => $category,
            'title'      => $title,
            'subtitle'   => $subtitle ?? '',
            'address'    => $address ?? $title,
            'date'       => $date,
            'lat'        => $lat,
            'lng'        => $lng,
            'detail_url' => '/test/' . $id,
        ];
    }
}
