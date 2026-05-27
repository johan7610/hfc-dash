<?php

declare(strict_types=1);

/**
 * Phase 3h Step 2 — KZN South Coast gazetteer for demo seeding.
 *
 * Curated real data. Street names cross-checked against CMA reports we've
 * already imported (Tucker, Marine, Pitts, Edward, Salmon, Devon, Emtunzi,
 * Stewards) and supplemented with verifiable street names. Scheme names
 * include real sectional title schemes from the same reports.
 *
 * Bounds for each suburb are GPS rectangles that sit comfortably on land
 * (no ocean pins). Centres are roughly suburb centroids. Price bands are
 * researched against typical 2025 KZN South Coast asking prices.
 *
 * Used by the four Demo*Seeder classes — they all read this single source
 * of truth so it stays consistent.
 */
return [

    'uvongo' => [
        'name' => 'Uvongo',
        'town' => 'Uvongo',
        'municipality' => 'Ray Nkonyeni',
        'bounds' => ['south' => -30.855, 'north' => -30.830, 'west' => 30.365, 'east' => 30.400],
        'center' => ['lat' => -30.844, 'lng' => 30.380],
        'price_band_house'      => [800000, 2500000],
        'price_band_sectional'  => [550000, 1400000],
        'price_band_vacant'     => [200000, 700000],
        'streets' => [
            'Tucker Avenue', 'Marine Drive', 'Pitts Avenue', 'Edward Avenue',
            'Salmon Road', 'Devon Place', 'Emtunzi Drive', 'Stewards Road',
            'Carlton Road', 'Mitchell Avenue', 'Aloha Drive', 'Birmingham Drive',
        ],
        'schemes' => [
            'Madeira Gardens', 'Parklands', 'Tucker Mews', 'San Savino',
            'Beth-El', 'Chanri Gardens', 'Stewart Manor', 'San Remo',
            'Devon Place Court', 'Southglen', 'Cranbrooke Mews', 'Claverhouse Close',
        ],
    ],

    'margate' => [
        'name' => 'Margate',
        'town' => 'Margate',
        'municipality' => 'Ray Nkonyeni',
        'bounds' => ['south' => -30.880, 'north' => -30.855, 'west' => 30.350, 'east' => 30.385],
        'center' => ['lat' => -30.867, 'lng' => 30.367],
        'price_band_house'      => [850000, 2700000],
        'price_band_sectional'  => [600000, 1500000],
        'price_band_vacant'     => [250000, 800000],
        'streets' => [
            'Marine Drive', 'Wartski Drive', 'Boyes Lane', 'Patterson Street',
            'Erasmus Street', 'Allison Road', 'Reynolds Drive', 'Acacia Road',
            'Marlin Avenue', 'St Andrews Drive', 'Carlton Drive', 'Pioneer Drive',
        ],
        'schemes' => [
            'Margate Sands', 'Riviera', 'Cabana Beach', 'Sea Lodge',
            'Margate Place', 'Beach Court', 'Atlantis', 'Sunset Manor',
            'Margate Mews', 'Marlin Quays', 'Boulevard Beach', 'Pioneer Court',
        ],
    ],

    'shelly_beach' => [
        'name' => 'Shelly Beach',
        'town' => 'Shelly Beach',
        'municipality' => 'Ray Nkonyeni',
        'bounds' => ['south' => -30.795, 'north' => -30.770, 'west' => 30.395, 'east' => 30.430],
        'center' => ['lat' => -30.783, 'lng' => 30.412],
        'price_band_house'      => [1000000, 3000000],
        'price_band_sectional'  => [700000, 1600000],
        'price_band_vacant'     => [300000, 900000],
        'streets' => [
            'Kingsway Avenue', 'Wessels Road', 'Tweni Road', 'Compensation Beach Road',
            'Sandown Drive', 'Banana Beach Road', 'Cumberland Drive', 'Glentana Road',
            'Highland Avenue', 'Lighthouse Road', 'St James Avenue', 'Coleridge Road',
        ],
        'schemes' => [
            'Shelly Sands', 'Coral Reef', 'Shelly Heights', 'Beach House',
            'Compensation Court', 'Highland Park', 'Lighthouse Lodge', 'Banana Beach Mews',
            'Sandown Manor', 'Kingsway Court', 'Tweni Place', 'Sea Spray',
        ],
    ],

    'southbroom' => [
        'name' => 'Southbroom',
        'town' => 'Southbroom',
        'municipality' => 'Ray Nkonyeni',
        'bounds' => ['south' => -30.925, 'north' => -30.900, 'west' => 30.310, 'east' => 30.345],
        'center' => ['lat' => -30.912, 'lng' => 30.325],
        'price_band_house'      => [1500000, 5000000],
        'price_band_sectional'  => [900000, 2200000],
        'price_band_vacant'     => [400000, 1200000],
        'streets' => [
            'Outlook Road', 'Stafford Drive', 'Caister Road', 'Beachway',
            'Outlook View', 'Spinnaker Drive', 'Pelican Drive', 'Cormorant Crescent',
            'Bushveld Road', 'Whisper Way', 'Coastline Road', 'Tides Way',
        ],
        'schemes' => [
            'Southbroom Heights', 'Outlook Manor', 'Caister Court', 'Spinnaker Bay',
            'Pelican Cove', 'Bushveld Mews', 'Tides Estate', 'Coastline Place',
            'Beachway Lodge', 'Whisper Heights', 'Stafford Place', 'Cormorant Court',
        ],
    ],

    'port_shepstone' => [
        'name' => 'Port Shepstone',
        'town' => 'Port Shepstone',
        'municipality' => 'Ray Nkonyeni',
        'bounds' => ['south' => -30.745, 'north' => -30.720, 'west' => 30.435, 'east' => 30.470],
        'center' => ['lat' => -30.733, 'lng' => 30.452],
        'price_band_house'      => [600000, 1800000],
        'price_band_sectional'  => [400000, 1100000],
        'price_band_vacant'     => [180000, 600000],
        'streets' => [
            'Aiken Street', 'Reynolds Street', 'Bisset Street', 'Main Road',
            'Sutton Street', 'Reynolds Avenue', 'Hospital Road', 'St Helier Road',
            'Buchanan Road', 'Berea Road', 'Murchison Road', 'Wood Street',
        ],
        'schemes' => [
            'Aiken Court', 'Port Place', 'Murchison Manor', 'Berea Heights',
            'Wood Park', 'Bisset Court', 'Reynolds Mews', 'St Helier Lodge',
            'Buchanan Square', 'Hospital View', 'Main Street Court', 'Sutton Place',
        ],
    ],

    'manaba_beach' => [
        'name' => 'Manaba Beach',
        'town' => 'Manaba Beach',
        'municipality' => 'Ray Nkonyeni',
        'bounds' => ['south' => -30.830, 'north' => -30.805, 'west' => 30.380, 'east' => 30.415],
        'center' => ['lat' => -30.817, 'lng' => 30.397],
        'price_band_house'      => [900000, 2800000],
        'price_band_sectional'  => [650000, 1500000],
        'price_band_vacant'     => [280000, 800000],
        'streets' => [
            'Manaba Avenue', 'Brock Street', 'Robberg Road', 'Sands Drive',
            'Crayfish Avenue', 'Beach View Drive', 'Surfers Way', 'Reef Road',
            'Tropic Lane', 'Bay View Crescent', 'Coastline Drive', 'Palm Beach Drive',
        ],
        'schemes' => [
            'Manaba Sands', 'Robberg Heights', 'Crayfish Court', 'Surfers Lodge',
            'Reef Manor', 'Bay View Mews', 'Tropic Court', 'Coastline Lodge',
            'Palm Beach Place', 'Sands Estate', 'Brock Manor', 'Beach View Heights',
        ],
    ],

];
