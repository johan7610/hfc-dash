<?php

/**
 * Investigation + verification script: multi-agency P24 listing dedup.
 * Tests the AnalysisDataService dedup pipeline for presentation 11.
 *
 * Run: php check_dedup.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\PortalCapture;
use App\Models\Presentation;
use App\Services\Presentations\AnalysisDataService;

$presentationId = 11;

echo str_repeat('=', 80) . "\n";
echo "P24 MULTI-AGENCY LISTING DEDUP — INVESTIGATION & VERIFICATION\n";
echo str_repeat('=', 80) . "\n\n";

// ─── PART 1: Raw data analysis ──────────────────────────────────────

$captures = PortalCapture::where('presentation_id', $presentationId)
    ->where('page_type', 'search')
    ->where('parse_status', 'parsed')
    ->get();

echo "=== RAW DATA (before dedup) ===\n";
echo "Search captures: {$captures->count()}\n";

$allItems = [];
$searchTotalCount = null;
foreach ($captures as $cap) {
    $fields = $cap->extracted_fields_json;
    $items = $fields['search']['items'] ?? [];
    $tc = $fields['search']['total_count'] ?? null;
    if ($tc !== null && ($searchTotalCount === null || $tc > $searchTotalCount)) {
        $searchTotalCount = (int) $tc;
    }
    echo "  Capture {$cap->id}: total_count={$tc}, items=" . count($items) . "\n";
    foreach ($items as $item) {
        $allItems[] = $item;
    }
}

// Dedup by listing ID
$byId = [];
foreach ($allItems as $item) {
    $id = $item['portal_listing_id'] ?? 'unknown';
    if (!isset($byId[$id])) $byId[$id] = $item;
}

echo "\nRaw items across pages:  " . count($allItems) . "\n";
echo "Unique by listing ID:   " . count($byId) . "\n";
echo "P24 total_count:        " . ($searchTotalCount ?? '?') . "\n";

// ─── PART 2: Property grouping analysis ─────────────────────────────

// Group by price + erf (exact)
$exactGroups = [];
foreach ($byId as $id => $item) {
    $key = ($item['price'] ?? 0) . '|' . ($item['erf_m2'] ?? $item['size_m2'] ?? 0);
    $exactGroups[$key][] = $id;
}

// Group by price + erf (10% tolerance) — same algo as AnalysisDataService
$toleranceGroups = [];
$assigned = [];
$sortedItems = [];
foreach ($byId as $id => $item) {
    $sortedItems[] = ['id' => $id, 'price' => $item['price'] ?? 0, 'erf' => $item['erf_m2'] ?? $item['size_m2'] ?? 0];
}
usort($sortedItems, fn($a, $b) => $a['price'] <=> $b['price'] ?: $a['erf'] <=> $b['erf']);

foreach ($sortedItems as $si) {
    $id = $si['id'];
    $price = $si['price'];
    $erf = $si['erf'];

    if ($price <= 0) {
        $gIdx = count($toleranceGroups);
        $toleranceGroups[$gIdx] = [$id];
        continue;
    }

    $foundGroup = null;
    foreach ($toleranceGroups as $gIdx => $gIds) {
        $rep = $byId[$gIds[0]];
        $repPrice = $rep['price'] ?? 0;
        $repErf = $rep['erf_m2'] ?? $rep['size_m2'] ?? 0;

        if ($price === $repPrice && erfMatch($erf, $repErf)) {
            $foundGroup = $gIdx;
            break;
        }
    }

    if ($foundGroup !== null) {
        $toleranceGroups[$foundGroup][] = $id;
    } else {
        $toleranceGroups[count($toleranceGroups)] = [$id];
    }
}

echo "\n=== GROUPING RESULTS ===\n";
echo "Exact (price + erf):         " . count($exactGroups) . " groups\n";
echo "Tolerance (price + erf±10%): " . count($toleranceGroups) . " groups\n";
echo "P24 target:                  " . ($searchTotalCount ?? '?') . " properties\n";

// Show multi-agency groups
echo "\n=== MULTI-AGENCY GROUPS (tolerance matching) ===\n";
$multiAgencyCount = 0;
foreach ($toleranceGroups as $gIdx => $ids) {
    if (count($ids) > 1) {
        $multiAgencyCount++;
        $first = $byId[$ids[0]];
        echo "\nGroup: R " . number_format($first['price'] ?? 0) . " | erf " . ($first['erf_m2'] ?? $first['size_m2'] ?? 0) . "m²\n";
        foreach ($ids as $id) {
            $item = $byId[$id];
            echo "  P24-{$id} | erf " . ($item['erf_m2'] ?? $item['size_m2'] ?? 0) . "m² | " . mb_substr($item['title'] ?? '', 0, 60) . "\n";
        }
    }
}
echo "\nMulti-agency groups found: {$multiAgencyCount}\n";

// ─── PART 3: Verify AnalysisDataService output ──────────────────────

echo "\n" . str_repeat('=', 80) . "\n";
echo "=== VERIFICATION: AnalysisDataService output ===\n";
echo str_repeat('=', 80) . "\n";

$presentation = Presentation::findOrFail($presentationId);
$service = new AnalysisDataService();
$data = $service->compile($presentation);

$active = $data['active_competition'];
$stock = $data['stock_absorption'];
$counts = $data['data_counts'];

echo "\nActive competition:\n";
echo "  Deduped row count:     {$active['count']}\n";
echo "  Raw listing count:     " . ($active['raw_listing_count'] ?? 'N/A') . "\n";
echo "  Total count (w/ excl): " . ($active['total_count'] ?? 'N/A') . "\n";
echo "  Avg asking price:      R " . number_format($active['avg_asking_price'] ?? 0) . "\n";

$multiRows = array_filter($active['rows'], fn($r) => !empty($r['is_multi_agency']));
echo "  Multi-agency rows:     " . count($multiRows) . "\n";
foreach ($multiRows as $r) {
    echo "    R " . number_format($r['list_price'] ?? 0) . " | erf " . ($r['extent_m2'] ?? 0)
        . "m² | " . ($r['listing_ids_in_group'] ?? 0) . " agencies | " . mb_substr($r['address'] ?? '', 0, 50) . "\n";
}

echo "\nStock absorption:\n";
echo "  P24 search total:     " . ($stock['search_total_count'] ?? 'N/A') . "\n";
echo "  Total active stock:   " . ($stock['total_active_stock'] ?? 'N/A') . "\n";
echo "  Stock source:         " . ($stock['stock_source'] ?? 'N/A') . "\n";
echo "  Months of supply:     " . ($stock['months_of_supply'] ?? 'N/A') . "\n";

echo "\nData counts (header):\n";
echo "  active_listings:      {$counts['active_listings']}\n";

echo "\n" . str_repeat('=', 80) . "\n";
echo "FINAL SUMMARY\n";
echo str_repeat('=', 80) . "\n";
echo "P24 says:           " . ($searchTotalCount ?? '?') . " unique properties\n";
echo "Raw listing IDs:    " . count($byId) . " (inflated by multi-agency)\n";
echo "After dedup:        {$active['count']} unique properties\n";
echo "Reduction:          " . (count($byId) - $active['count']) . " duplicates removed\n";
echo "Gap from P24:       " . ($active['count'] - ($searchTotalCount ?? 0)) . " (remaining unmatched multi-agency)\n";
echo "\nNote: Gap exists because some agencies list the same property at\n";
echo "different erf sizes (>10% diff) or with slightly different prices.\n";
echo "Stock absorption uses P24's total_count directly as the authority.\n";
echo "\nDone.\n";

function erfMatch(int $a, int $b): bool
{
    if ($a === 0 && $b === 0) return true;
    if ($a === 0 || $b === 0) return false;
    $max = max($a, $b);
    return abs($a - $b) / $max <= 0.10;
}
