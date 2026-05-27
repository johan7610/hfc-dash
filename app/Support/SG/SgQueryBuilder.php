<?php

declare(strict_types=1);

namespace App\Support\SG;

use App\Models\Property;

/**
 * Phase 3j D1 — pre-populate the SG search query from a Property.
 *
 * Returns the values we KNOW from the property record plus a list of
 * fields the agent must supply before search can fire. The controller
 * surfaces the missing list to the UI so the agent fills in only what
 * isn't already on the property.
 *
 * The province lookup uses database/seeders/data/sa_suburb_provinces.php
 * — case-insensitive normalisation, alternate-spelling friendly.
 */
final class SgQueryBuilder
{
    private static ?array $gazetteer = null;

    /**
     * @return array{defaults: array<string, mixed>, missing: array<int, string>}
     */
    public function buildFromProperty(Property $property): array
    {
        $defaults = [];
        $missing  = [];

        // ── province ──
        $province = $property->sg_province
            ?: $this->lookupProvince($property->suburb)
            ?: $this->lookupProvince($property->town);
        if ($province) {
            $defaults['province'] = $province;
        } else {
            $missing[] = 'province';
        }

        // ── rural / urban ──
        $defaults['rural_urban'] = $property->sg_rural_urban ?? $this->inferRuralUrban($property);

        // ── town ──
        $town = $property->suburb ?: $property->town;
        if ($town) {
            $defaults['town'] = $town;
        } else {
            $missing[] = 'town';
        }

        // ── parcel (erf) ──
        $erf = $property->erf_number;
        if ($erf !== null && $erf !== '') {
            $defaults['parcel_number'] = (string) $erf;
        } else {
            $missing[] = 'parcel_number';
        }

        // ── portion ──
        $portion = $property->erf_portion ?? '0';
        $defaults['portion'] = (string) ($portion === '' ? '0' : $portion);

        // ── farm name (optional, rural only) ──
        if ($property->sg_farm_name) {
            $defaults['farm_name'] = $property->sg_farm_name;
        }

        return ['defaults' => $defaults, 'missing' => $missing];
    }

    /**
     * Lookup the SG-friendly province name for a suburb (case-insensitive).
     * Returns null when not in the gazetteer.
     */
    public function lookupProvince(?string $suburbOrTown): ?string
    {
        if (!$suburbOrTown) return null;

        if (self::$gazetteer === null) {
            $path = database_path('seeders/data/sa_suburb_provinces.php');
            self::$gazetteer = is_file($path) ? require $path : [];
        }

        $key = mb_strtolower(trim($suburbOrTown));
        return self::$gazetteer[$key] ?? null;
    }

    /**
     * Best-effort: residential / sectional title → urban; vacant agricultural
     * or large farm-style → rural. Defaults urban.
     */
    private function inferRuralUrban(Property $property): string
    {
        $type = mb_strtolower((string) ($property->property_type ?? ''));
        if (str_contains($type, 'farm') || str_contains($type, 'agricultur')) {
            return 'rural';
        }
        if ((int) ($property->erf_size_m2 ?? 0) >= 50_000) {
            return 'rural';
        }
        return 'urban';
    }
}
