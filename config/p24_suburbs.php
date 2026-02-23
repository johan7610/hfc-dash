<?php

/**
 * Property24 suburb lookup — KZN South Coast (HF Coastal operating area).
 *
 * Each entry maps a normalised key (lowercase, spaces or hyphens) to:
 *   - id:          P24's numeric suburb identifier
 *   - slug:        URL path segment used on property24.com
 *   - surrounding: array of nearby suburb IDs to include in "wider area" searches
 *   - confirmed:   true if the P24 ID has been verified (false = use Term= fallback)
 *
 * Confirmed IDs: 6357 (Shelly Beach), 6358 (St Michaels), 6359 (Uvongo),
 *                6336 (Oslo Beach), 33106 (Uvongo Beach), 6361 (Manaba Beach)
 *
 * NOTE: This config is the fallback. If a p24_suburbs DB table exists, the app
 *       will query that first (see P24Suburb model). Edit via /settings/p24-suburbs.
 */

return [
    'shelly beach'          => ['id' => 6357,  'slug' => 'shelly-beach',        'surrounding' => [6358, 33106, 6336], 'confirmed' => true],
    'shelly-beach'          => ['id' => 6357,  'slug' => 'shelly-beach',        'surrounding' => [6358, 33106, 6336], 'confirmed' => true],
    'shelley beach'         => ['id' => 6357,  'slug' => 'shelly-beach',        'surrounding' => [6358, 33106, 6336], 'confirmed' => true],
    'uvongo'                => ['id' => 6359,  'slug' => 'uvongo',              'surrounding' => [33106, 6361], 'confirmed' => true],
    'margate'               => ['id' => 6348,  'slug' => 'margate',             'surrounding' => [], 'confirmed' => false],
    'ramsgate'              => ['id' => 6354,  'slug' => 'ramsgate',            'surrounding' => [], 'confirmed' => false],
    'southbroom'            => ['id' => 6360,  'slug' => 'southbroom',          'surrounding' => [], 'confirmed' => false],
    'manaba beach'          => ['id' => 6361,  'slug' => 'manaba-beach',        'surrounding' => [], 'confirmed' => true],
    'manaba-beach'          => ['id' => 6361,  'slug' => 'manaba-beach',        'surrounding' => [], 'confirmed' => true],
    'port shepstone'        => ['id' => 6353,  'slug' => 'port-shepstone',      'surrounding' => [], 'confirmed' => false],
    'port-shepstone'        => ['id' => 6353,  'slug' => 'port-shepstone',      'surrounding' => [], 'confirmed' => false],
    'st michaels on sea'    => ['id' => 6358,  'slug' => 'st-michaels-on-sea',  'surrounding' => [6357, 33106], 'confirmed' => true],
    'st-michaels-on-sea'    => ['id' => 6358,  'slug' => 'st-michaels-on-sea',  'surrounding' => [6357, 33106], 'confirmed' => true],
    'oslo beach'            => ['id' => 6336,  'slug' => 'oslo-beach',          'surrounding' => [6357], 'confirmed' => true],
    'oslo-beach'            => ['id' => 6336,  'slug' => 'oslo-beach',          'surrounding' => [6357], 'confirmed' => true],
    'uvongo beach'          => ['id' => 33106, 'slug' => 'uvongo-beach',        'surrounding' => [6359, 6358], 'confirmed' => true],
    'uvongo-beach'          => ['id' => 33106, 'slug' => 'uvongo-beach',        'surrounding' => [6359, 6358], 'confirmed' => true],
    'hibberdene'            => ['id' => 6342,  'slug' => 'hibberdene',          'surrounding' => [], 'confirmed' => false],
    'port edward'           => ['id' => 6352,  'slug' => 'port-edward',         'surrounding' => [], 'confirmed' => false],
    'port-edward'           => ['id' => 6352,  'slug' => 'port-edward',         'surrounding' => [], 'confirmed' => false],
    'palm beach'            => ['id' => 6351,  'slug' => 'palm-beach',          'surrounding' => [], 'confirmed' => false],
    'palm-beach'            => ['id' => 6351,  'slug' => 'palm-beach',          'surrounding' => [], 'confirmed' => false],
    'marina beach'          => ['id' => 6349,  'slug' => 'marina-beach',        'surrounding' => [], 'confirmed' => false],
    'marina-beach'          => ['id' => 6349,  'slug' => 'marina-beach',        'surrounding' => [], 'confirmed' => false],
    'trafalgar'             => ['id' => 6363,  'slug' => 'trafalgar',           'surrounding' => [], 'confirmed' => false],
    'san lameer'            => ['id' => 6356,  'slug' => 'san-lameer',          'surrounding' => [], 'confirmed' => false],
    'san-lameer'            => ['id' => 6356,  'slug' => 'san-lameer',          'surrounding' => [], 'confirmed' => false],
    'leisure bay'           => ['id' => 6345,  'slug' => 'leisure-bay',         'surrounding' => [], 'confirmed' => false],
    'leisure-bay'           => ['id' => 6345,  'slug' => 'leisure-bay',         'surrounding' => [], 'confirmed' => false],
    'munster'               => ['id' => 6350,  'slug' => 'munster',             'surrounding' => [], 'confirmed' => false],
    'sea park'              => ['id' => 11529, 'slug' => 'sea-park',            'surrounding' => [], 'confirmed' => false],
    'sea-park'              => ['id' => 11529, 'slug' => 'sea-park',            'surrounding' => [], 'confirmed' => false],
];
