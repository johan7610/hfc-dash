<?php

namespace Database\Seeders;

use App\Models\P24Suburb;
use Illuminate\Database\Seeder;

class P24SuburbSeeder extends Seeder
{
    public function run(): void
    {
        // Deduplicate config entries (config has both "shelly beach" and "shelly-beach" pointing to same ID)
        $configEntries = config('p24_suburbs', []);
        $seen = [];

        foreach ($configEntries as $key => $entry) {
            $slug = $entry['slug'] ?? str_replace(' ', '-', $key);

            // Skip duplicate slugs (config has space and hyphen variants)
            if (isset($seen[$slug])) {
                continue;
            }
            $seen[$slug] = true;

            // Derive display name from slug
            $name = ucwords(str_replace('-', ' ', $slug));

            P24Suburb::updateOrCreate(
                ['slug' => $slug],
                [
                    'name'            => $name,
                    'p24_id'          => $entry['id'] ?? null,
                    'region'          => 'kzn-south-coast',
                    'surrounding_ids' => $entry['surrounding'] ?? [],
                    'confirmed'       => $entry['confirmed'] ?? false,
                ]
            );
        }
    }
}
