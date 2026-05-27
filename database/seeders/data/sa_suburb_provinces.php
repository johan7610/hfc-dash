<?php

/**
 * Phase 3j F1 — suburb → province gazetteer for SG search.
 *
 * Hardcoded mapping: keys are lowercased suburb names (or alternate
 * spellings), values are the SA province name as the SG site lists it
 * (used by SgQueryBuilder + the numeric-id mapper in SgSearchService).
 *
 * Coverage: full KZN South Coast (HFC's operating area) + stub entries
 * for other provinces' major suburbs so the system doesn't blank for
 * agencies CoreX expands to. Editable file — non-exhaustive by design.
 *
 * The SG site has duplicate Free State / Northern Cape office IDs (both
 * use office=1); when adding new entries that map to either, the lookup
 * will still work — pick the closer one.
 *
 * NOT for general geo lookups. This is purely for SG document searches.
 */

return [
    // ── KZN South Coast (HFC's home territory) ──
    'uvongo'         => 'Kwa-Zulu Natal',
    'margate'        => 'Kwa-Zulu Natal',
    'shelly beach'   => 'Kwa-Zulu Natal',
    'manaba beach'   => 'Kwa-Zulu Natal',
    'manaba'         => 'Kwa-Zulu Natal',
    'southbroom'     => 'Kwa-Zulu Natal',
    'south broom'    => 'Kwa-Zulu Natal',
    'port shepstone' => 'Kwa-Zulu Natal',
    'ramsgate'       => 'Kwa-Zulu Natal',
    'palm beach'     => 'Kwa-Zulu Natal',
    'munster'        => 'Kwa-Zulu Natal',
    'glenmore'       => 'Kwa-Zulu Natal',
    'leisure bay'    => 'Kwa-Zulu Natal',
    'trafalgar'      => 'Kwa-Zulu Natal',
    'marina beach'   => 'Kwa-Zulu Natal',
    'st michaels on sea' => 'Kwa-Zulu Natal',
    'st michaels'    => 'Kwa-Zulu Natal',
    'uvongo beach'   => 'Kwa-Zulu Natal',
    'sea park'       => 'Kwa-Zulu Natal',
    'oslo beach'     => 'Kwa-Zulu Natal',
    'umtentweni'     => 'Kwa-Zulu Natal',
    'pumula'         => 'Kwa-Zulu Natal',
    'hibberdene'     => 'Kwa-Zulu Natal',
    'banana beach'   => 'Kwa-Zulu Natal',
    'pennington'     => 'Kwa-Zulu Natal',
    'umkomaas'       => 'Kwa-Zulu Natal',
    'scottburgh'     => 'Kwa-Zulu Natal',
    'park rynie'     => 'Kwa-Zulu Natal',
    'kelso'          => 'Kwa-Zulu Natal',

    // ── Wider KZN (Durban + North Coast for completeness) ──
    'durban'         => 'Kwa-Zulu Natal',
    'umhlanga'       => 'Kwa-Zulu Natal',
    'ballito'        => 'Kwa-Zulu Natal',
    'pietermaritzburg' => 'Kwa-Zulu Natal',
    'pmb'            => 'Kwa-Zulu Natal',

    // ── Western Cape stubs ──
    'cape town'      => 'Western Cape',
    'stellenbosch'   => 'Western Cape',
    'somerset west'  => 'Western Cape',
    'paarl'          => 'Western Cape',
    'george'         => 'Western Cape',
    'mossel bay'     => 'Western Cape',
    'plettenberg bay' => 'Western Cape',
    'knysna'         => 'Western Cape',
    'hermanus'       => 'Western Cape',

    // ── Gauteng stubs ──
    'johannesburg'   => 'Gauteng',
    'sandton'        => 'Gauteng',
    'pretoria'       => 'Gauteng',
    'tshwane'        => 'Gauteng',
    'centurion'      => 'Gauteng',
    'midrand'        => 'Gauteng',
    'roodepoort'     => 'Gauteng',

    // ── Eastern Cape stubs ──
    'east london'    => 'Eastern Cape',
    'port elizabeth' => 'Eastern Cape',
    'gqeberha'       => 'Eastern Cape',
    'jeffreys bay'   => 'Eastern Cape',

    // ── Other provinces (single anchors) ──
    'bloemfontein'   => 'Free State',
    'polokwane'      => 'Limpopo',
    'nelspruit'      => 'Mpumalanga',
    'mbombela'       => 'Mpumalanga',
    'rustenburg'     => 'North West',
    'kimberley'      => 'Northern Cape',
];
