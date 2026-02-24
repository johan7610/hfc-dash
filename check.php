<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$captures = DB::table('portal_captures')
    ->where('presentation_id', 11)
    ->where('page_type', 'search')
    ->select('id', 'extracted_fields_json')
    ->get();

$all = [];
foreach ($captures as $c) {
    $data = json_decode($c->extracted_fields_json, true);
    $items = $data['search']['items'] ?? $data['items'] ?? [];
    foreach ($items as $item) {
        $id = preg_replace('/^[^0-9]+/', '', $item['listing_id'] ?? $item['portal_listing_id'] ?? '');
        $all[$id] = [
            'price' => $item['price'] ?? $item['list_price'] ?? 0,
            'title' => $item['title'] ?? '?',
            'beds' => $item['beds'] ?? $item['bedrooms'] ?? '?',
            'size' => $item['size_m2'] ?? $item['erf_m2'] ?? $item['floor_m2'] ?? '?',
        ];
    }
}

// Group by price+beds+size (same property, different agency)
$groups = [];
foreach ($all as $id => $item) {
    $key = $item['price'] . '|' . $item['beds'] . '|' . $item['size'];
    $groups[$key][] = $id;
}

$multiAgency = array_filter($groups, fn($ids) => count($ids) > 1);
$uniqueProperties = count($groups);

echo "Unique listing IDs: " . count($all) . "\n";
echo "Unique properties (by price+beds+size): {$uniqueProperties}\n";
echo "Multi-agency listings: " . count($multiAgency) . "\n\n";

if ($multiAgency) {
    echo "=== SAME PROPERTY, MULTIPLE AGENCIES ===\n";
    foreach ($multiAgency as $key => $ids) {
        echo "\nProperty: {$key}\n";
        foreach ($ids as $id) {
            echo "  P24-{$id} | {$all[$id]['title']}\n";
        }
    }
}