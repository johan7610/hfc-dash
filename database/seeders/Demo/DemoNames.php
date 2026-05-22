<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

/**
 * Phase 3h — curated plausible SA names for synthetic scheme owners + deals.
 *
 * 30 first names + 30 surnames spanning Afrikaans / English / isiZulu / Xhosa,
 * matching the demographic mix of the KZN South Coast. All entirely fictional
 * combinations are produced by mixing.
 */
final class DemoNames
{
    private const FIRST_NAMES = [
        // English / Afrikaans
        'Johan', 'Pieter', 'Susan', 'Marlene', 'Werner', 'Hendrik',
        'Charl', 'Elsabe', 'Annette', 'Riaan',
        // isiZulu / Xhosa
        'Sipho', 'Nomvula', 'Thandiwe', 'Bongani', 'Lindiwe',
        'Sibusiso', 'Zandile', 'Mandla', 'Phumzile', 'Themba',
        // English (common)
        'Michael', 'Sarah', 'David', 'Catherine', 'Robert',
        'Emily', 'James', 'Olivia', 'Daniel', 'Sophie',
    ];

    private const SURNAMES = [
        // Afrikaans
        'Van der Merwe', 'Botha', 'Pretorius', 'Du Plessis', 'Coetzee',
        'Smit', 'Steyn', 'Du Toit', 'Bezuidenhout', 'Kruger',
        // Zulu / Xhosa
        'Dlamini', 'Mthembu', 'Khumalo', 'Ndlovu', 'Nkosi',
        'Mahlangu', 'Mokoena', 'Zulu', 'Mabaso', 'Sithole',
        // English (common in SA)
        'Naidoo', 'Pillay', 'Singh', 'Smith', 'Jones',
        'Brown', 'Wilson', 'Anderson', 'Taylor', 'Hughes',
    ];

    /**
     * Returns one deterministic plausible name for a given seed string.
     * Same seed → same name (idempotent seeding).
     */
    public static function name(string $seed): string
    {
        $hash = crc32($seed);
        $first = self::FIRST_NAMES[$hash % count(self::FIRST_NAMES)];
        // Use a different slice of the hash for the surname so similar seeds
        // don't always pair the same way.
        $surname = self::SURNAMES[intdiv($hash, count(self::FIRST_NAMES)) % count(self::SURNAMES)];
        return $first . ' ' . $surname;
    }
}
