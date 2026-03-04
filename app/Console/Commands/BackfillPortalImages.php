<?php

namespace App\Console\Commands;

use App\Models\PortalCapture;
use App\Models\PortalListing;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class BackfillPortalImages extends Command
{
    protected $signature = 'p24:backfill-images';
    protected $description = 'Backfill primary_image_url on portal_listings from stored capture HTML';

    public function handle(): int
    {
        $listings = PortalListing::whereNull('primary_image_url')->get();
        $total = $listings->count();
        $updated = 0;
        $skipped = 0;

        $this->info("Processing {$total} listings without primary_image_url...");

        foreach ($listings as $listing) {
            $imageUrl = $this->findImageForListing($listing);

            if ($imageUrl) {
                $listing->update(['primary_image_url' => $imageUrl]);
                $updated++;
            } else {
                $skipped++;
            }
        }

        $this->info("Done. Updated {$updated} of {$total} listings with images. Skipped {$skipped}.");

        return self::SUCCESS;
    }

    private function findImageForListing(PortalListing $listing): ?string
    {
        // Strategy 1: Check current_fields_json for image already extracted by search extractor
        $fields = $listing->current_fields_json ?? [];
        if (!empty($fields['image']) && is_string($fields['image'])) {
            return $fields['image'];
        }

        // Strategy 2: Find a property-type capture for this listing and extract from HTML
        $capture = PortalCapture::where('page_type', 'property')
            ->where('source_url', 'like', '%' . $listing->portal_listing_id . '%')
            ->whereNotNull('raw_html_path')
            ->orderByDesc('id')
            ->first();

        if ($capture) {
            return $this->extractImageFromCapture($capture);
        }

        // Strategy 3: Try any capture linked to this listing via last_capture_id
        if ($listing->last_capture_id) {
            $lastCapture = PortalCapture::find($listing->last_capture_id);
            if ($lastCapture && !empty($lastCapture->extracted_fields_json)) {
                $extractedFields = $lastCapture->extracted_fields_json;
                // Search extraction stores items with image field
                if (isset($extractedFields['search']['items'])) {
                    foreach ($extractedFields['search']['items'] as $item) {
                        if (($item['portal_listing_id'] ?? '') == $listing->portal_listing_id && !empty($item['image'])) {
                            return $item['image'];
                        }
                    }
                }
                // Detail extraction stores image directly
                if (!empty($extractedFields['image'])) {
                    return $extractedFields['image'];
                }
            }
        }

        return null;
    }

    private function extractImageFromCapture(PortalCapture $capture): ?string
    {
        if (empty($capture->raw_html_path) || !Storage::disk('local')->exists($capture->raw_html_path)) {
            return null;
        }

        $html = Storage::disk('local')->get($capture->raw_html_path);
        if (empty($html)) {
            return null;
        }

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        $xpath = new \DOMXPath($dom);

        // og:image meta tag
        $ogNodes = $xpath->query('//meta[@property="og:image"]');
        foreach ($ogNodes as $node) {
            $content = $node->getAttribute('content');
            if ($content !== '' && !str_contains(strtolower($content), 'icon_') && !str_contains(strtolower($content), '/logo')) {
                return $content;
            }
        }

        // JSON-LD image
        if (!empty($capture->jsonld_json)) {
            $ld = isset($capture->jsonld_json['@type']) ? $capture->jsonld_json : ($capture->jsonld_json[0] ?? []);
            $img = $ld['image'] ?? null;
            if (is_string($img) && $img !== '') {
                return $img;
            }
            if (is_array($img) && !empty($img[0]) && is_string($img[0])) {
                return $img[0];
            }
        }

        // Gallery/lightbox image
        $galleryNodes = $xpath->query('//img[contains(@class,"js_lightboxImage") or contains(@class,"p24_mainImage") or contains(@class,"mainImage")]');
        foreach ($galleryNodes as $img) {
            $src = $img->getAttribute('data-src') ?: $img->getAttribute('src');
            if ($src !== '' && !str_contains(strtolower($src), 'icon_')) {
                return $src;
            }
        }

        return null;
    }
}
