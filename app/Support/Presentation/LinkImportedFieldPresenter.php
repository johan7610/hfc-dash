<?php

namespace App\Support\Presentation;

use App\Models\PortalCapture;
use App\Models\PresentationLink;

/**
 * Builds a human-readable view array from PresentationLink + optional PortalCapture data.
 *
 * Precedence:
 *   1. If link has portal_capture_id AND PortalCapture.extracted_fields_json is non-empty,
 *      use PortalCapture data as the "imported" source UNLESS PresentationLink.extracted_at
 *      is more recent than PortalCapture.captured_at — in that case, prefer link's extracted_json.
 *   2. Otherwise fall back to PresentationLink.extracted_json.
 *   3. Override data (override_json) is NOT merged into imported — it's shown separately.
 */
class LinkImportedFieldPresenter
{
    /**
     * Known field mappings: internal key => human label.
     * Order determines display order.
     */
    private const LISTING_FIELDS = [
        'asking_price'  => 'Price',
        'price'         => 'Price',
        'beds'          => 'Bedrooms',
        'bedrooms'      => 'Bedrooms',
        'baths'         => 'Bathrooms',
        'bathrooms'     => 'Bathrooms',
        'floor_area_m2' => 'Floor size',
        'floor_m2'      => 'Floor size',
        'floor_size'    => 'Floor size',
        'erf_m2'        => 'Erf size',
        'lot_size'      => 'Erf size',
        'suburb'        => 'Area',
        'address'       => 'Address',
        'listing_id'    => 'Listing ID',
        'title'         => 'Listing title',
        'name'          => 'Listing title',
        'property_type' => 'Property type',
        'agent_name'    => 'Agent',
        'agent_phone'   => 'Agent phone',
        'rooms'         => 'Rooms',
    ];

    /**
     * Search result field mappings.
     */
    private const SEARCH_FIELDS = [
        'results_count' => 'Results',
        'price_min'     => 'Price min',
        'price_max'     => 'Price max',
        'price_median'  => 'Median price',
    ];

    /**
     * Keys to skip entirely (metadata, not useful to agents).
     */
    private const SKIP_KEYS = [
        'extractor_version', 'link_type', 'url', 'source_domain', 'source_site',
        'link_subtype', 'snapshot_id', 'extraction_method', 'snapshot_error',
        'blocked_reason', 'timed_out', 'http_status', 'content_bytes',
        'top_listings', 'rows_extracted', 'image', '_jsonld_type', 'currency',
        '_page_type', '_extractor', '_extraction', '_capture_source', '_capture_id',
        'search', 'listing_urls_count',
    ];

    /**
     * Overridable fields for listing-type links. Key => human label.
     */
    private const OVERRIDE_FIELDS_LISTING = [
        'asking_price'  => 'Price',
        'suburb'        => 'Suburb',
        'beds'          => 'Bedrooms',
        'baths'         => 'Bathrooms',
        'floor_area_m2' => 'Floor m²',
        'erf_m2'        => 'Erf m²',
    ];

    /**
     * Build the full view array for a single link.
     *
     * @return array{imported: array, meta: array, override_fields: array, raw: array|null, is_search: bool, capture_page_type: string|null, search_summary: array|null}
     */
    public function build(PresentationLink $link): array
    {
        $capture = $link->portal_capture_id ? $link->portalCapture : null;

        // Resolve which data source to use for "imported" fields
        $sourceData   = $this->resolveImportedSource($link, $capture);
        $sourceName   = $sourceData['source'];
        $importedRaw  = $sourceData['data'];

        // Determine page type: check _page_type marker, capture page_type, legacy link_subtype
        $capturePageType = $this->resolvePageType($importedRaw, $capture);
        $isSearch = ($capturePageType === 'search');

        // Build curated imported fields
        $imported = $isSearch
            ? $this->mapFields($importedRaw, self::SEARCH_FIELDS)
            : $this->mapFields($importedRaw, self::LISTING_FIELDS);

        // Build meta row
        $meta = $this->buildMeta($link, $capture, $sourceName);

        // Build override comparison fields (only for listings)
        $overrideFields = $this->buildOverrideFields($link, $importedRaw, $isSearch);

        // Build search summary for search captures
        $searchSummary = null;
        if ($isSearch) {
            $searchSummary = $this->buildSearchSummary($importedRaw, $capture);
        }

        return [
            'imported'          => $imported,
            'meta'              => $meta,
            'override_fields'   => $overrideFields,
            'raw'               => $importedRaw,
            'is_search'         => $isSearch,
            'capture_page_type' => $capturePageType,
            'search_summary'    => $searchSummary,
        ];
    }

    /**
     * Resolve effective page type from data + capture.
     */
    private function resolvePageType(array $importedRaw, ?PortalCapture $capture): ?string
    {
        // Explicit _page_type marker (set by new extraction pipeline)
        if (!empty($importedRaw['_page_type'])) {
            return $importedRaw['_page_type'] === 'listing' ? 'listing' : $importedRaw['_page_type'];
        }

        // Capture's page_type column
        if ($capture) {
            if ($capture->page_type === 'search') return 'search';
            if ($capture->page_type === 'property') return 'listing';
        }

        // Legacy: link_subtype
        if (($importedRaw['link_subtype'] ?? '') === 'search_results') {
            return 'search';
        }

        // Has search.items => search
        if (isset($importedRaw['search'])) {
            return 'search';
        }

        // Has listing fields => listing
        if (!empty($importedRaw['price']) || !empty($importedRaw['asking_price']) || !empty($importedRaw['bedrooms']) || !empty($importedRaw['beds'])) {
            return 'listing';
        }

        return null;
    }

    /**
     * Build a search summary array for display.
     */
    private function buildSearchSummary(array $importedRaw, ?PortalCapture $capture): array
    {
        $summary = [];

        $summary['listings_found'] = $importedRaw['listing_urls_count']
            ?? $importedRaw['search']['items_on_page']
            ?? null;

        $summary['total_results'] = $importedRaw['search']['total_count'] ?? null;

        if ($capture) {
            $summary['capture_time'] = $capture->captured_at?->format('Y-m-d H:i');
            $summary['html_bytes'] = $capture->html_bytes;
            $summary['parse_status'] = $capture->parse_status;
            $summary['price_change_count'] = $capture->priceChangeCount();
        }

        return array_filter($summary, fn($v) => $v !== null);
    }

    /**
     * Determine which data source to use for imported fields.
     *
     * @return array{source: string, data: array}
     */
    private function resolveImportedSource(PresentationLink $link, ?PortalCapture $capture): array
    {
        $linkData    = $this->safeArray($link->extracted_json);
        $captureData = $capture ? $this->safeArray($capture->extracted_fields_json) : [];

        // No capture or empty capture → use link data
        if (empty($captureData)) {
            return ['source' => 'link_extraction', 'data' => $linkData ?? []];
        }

        // Both exist: prefer capture unless link extracted_at is newer
        if (!empty($linkData) && $link->extracted_at && $capture->captured_at) {
            if ($link->extracted_at->gt($capture->captured_at)) {
                return ['source' => 'link_extraction', 'data' => $linkData];
            }
        }

        return ['source' => 'portal_capture', 'data' => $captureData];
    }

    /**
     * Map raw data keys to human-readable label => formatted value pairs.
     * Skips empty/null values. Deduplicates by label (first match wins).
     */
    private function mapFields(array $data, array $fieldMap): array
    {
        $result = [];
        $seenLabels = [];

        foreach ($fieldMap as $key => $label) {
            if (!isset($data[$key]) || $data[$key] === '' || $data[$key] === null) {
                continue;
            }
            if (isset($seenLabels[$label])) {
                continue;
            }
            $seenLabels[$label] = true;
            $result[$label] = $this->formatValue($label, $data[$key]);
        }

        // Also include any non-metadata fields that weren't in the map (catch-all)
        foreach ($data as $key => $val) {
            if (in_array($key, self::SKIP_KEYS, true)) continue;
            if ($val === null || $val === '' || is_array($val)) continue;

            $humanLabel = ucfirst(str_replace('_', ' ', $key));
            if (isset($seenLabels[$humanLabel])) continue;

            // Check if already mapped under a different label
            $alreadyMapped = false;
            foreach ($fieldMap as $fk => $fl) {
                if ($fk === $key) {
                    $alreadyMapped = true;
                    break;
                }
            }
            if ($alreadyMapped) continue;

            $seenLabels[$humanLabel] = true;
            $result[$humanLabel] = $this->formatValue($humanLabel, $val);
        }

        return $result;
    }

    /**
     * Format a value based on its label/type.
     */
    private function formatValue(string $label, mixed $value): string
    {
        if (is_array($value)) {
            return implode(', ', array_filter($value, fn ($v) => $v !== null && $v !== ''));
        }

        $val = (string) $value;

        // Price fields: format as ZAR
        if (in_array($label, ['Price', 'Price min', 'Price max', 'Median price'])) {
            if (is_numeric($value)) {
                return 'R ' . number_format((float) $value, 0, '.', ',');
            }
            // Already formatted or non-numeric
            return $val;
        }

        // Area/size fields: append m² if numeric and missing unit
        if (in_array($label, ['Floor size', 'Erf size'])) {
            if (is_numeric($value)) {
                return number_format((float) $value, 0) . ' m²';
            }
            // May already contain units
            return $val;
        }

        // Property type: capitalize
        if ($label === 'Property type' && $val !== '') {
            return ucfirst($val);
        }

        return $val;
    }

    /**
     * Build the meta information row.
     */
    private function buildMeta(PresentationLink $link, ?PortalCapture $capture, string $sourceName): array
    {
        $meta = [];

        if ($capture) {
            $meta['Source'] = ucfirst(str_replace('_', ' ', $capture->source_site ?? 'Portal'));
            if ($capture->captured_at) {
                $meta['Captured'] = $capture->captured_at->format('Y-m-d H:i');
            }
            $meta['Capture status'] = $capture->parse_status ?? 'unknown';
        } else {
            $meta['Source'] = 'Link extraction';
        }

        $extStatus = $link->extraction_status ?? 'pending';
        $meta['Extraction status'] = $extStatus;

        if ($link->extracted_at) {
            $meta['Extracted'] = $link->extracted_at->format('Y-m-d H:i');
        }

        $meta['Data from'] = $sourceName === 'portal_capture' ? 'Portal capture' : 'Link extraction';

        return $meta;
    }

    /**
     * Build override comparison fields.
     * Each entry: ['label' => ..., 'key' => ..., 'current' => ..., 'imported' => ..., 'imported_missing_label' => ..., 'captured_at' => ...]
     */
    private function buildOverrideFields(PresentationLink $link, array $importedRaw, bool $isSearch): array
    {
        if ($isSearch) {
            // Search results are not individually overridable
            return [];
        }

        $override  = $this->safeArray($link->override_json) ?? [];
        $capture   = $link->portal_capture_id ? $link->portalCapture : null;
        $capturedAt = $capture?->captured_at?->format('Y-m-d H:i');
        $hasCaptureData = $capture && !empty($capture->extracted_fields_json);

        $fields = [];
        foreach (self::OVERRIDE_FIELDS_LISTING as $key => $label) {
            // Current effective value (override wins, then extracted, then link column)
            $currentRaw = $override[$key]
                ?? ($this->safeArray($link->extracted_json)[$key] ?? null)
                ?? $this->getLinkColumnValue($link, $key);

            // Imported suggestion from resolved source
            $importedVal = $importedRaw[$key] ?? $this->mapCaptureKey($importedRaw, $key);

            // Determine label for missing imported values
            $importedMissingLabel = $hasCaptureData ? 'Not found in listing page' : 'No imported value yet';

            $fields[] = [
                'label'                => $label,
                'key'                  => $key,
                'current'              => $currentRaw !== null && $currentRaw !== '' ? $this->formatValue($label, $currentRaw) : null,
                'current_raw'          => $currentRaw,
                'imported'             => $importedVal !== null && $importedVal !== '' ? $this->formatValue($label, $importedVal) : null,
                'imported_missing_label' => $importedMissingLabel,
                'captured_at'          => $capturedAt,
            ];
        }

        return $fields;
    }

    /**
     * Get a link's own column value (from the PresentationLink table columns).
     */
    private function getLinkColumnValue(PresentationLink $link, string $key): mixed
    {
        return match ($key) {
            'asking_price'  => $link->asking_price_inc,
            'beds'          => $link->beds,
            'baths'         => $link->baths,
            'floor_area_m2' => $link->floor_area_m2,
            'erf_m2'        => $link->erf_m2,
            'suburb'        => $link->suburb,
            'property_type' => $link->property_type,
            default         => null,
        };
    }

    /**
     * Map capture-specific key names to link-standard key names.
     * e.g. capture uses 'price' but link uses 'asking_price'.
     */
    private function mapCaptureKey(array $data, string $linkKey): mixed
    {
        return match ($linkKey) {
            'asking_price'  => $data['price'] ?? null,
            'beds'          => $data['bedrooms'] ?? null,
            'baths'         => $data['bathrooms'] ?? null,
            'floor_area_m2' => $data['floor_size'] ?? null,
            'erf_m2'        => $data['lot_size'] ?? null,
            'suburb'        => $data['address'] ?? null,
            default         => null,
        };
    }

    /**
     * Safely coerce a value to array.
     */
    private function safeArray(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_string($decoded)) {
                $decoded = json_decode($decoded, true);
            }
            return is_array($decoded) ? $decoded : null;
        }
        return null;
    }
}
