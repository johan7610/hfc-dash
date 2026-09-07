<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;

/**
 * AT-392 Phase 2 — Johan: "qualifying formula - agency can set this."
 * Deliberately follows STANDARDS.md Rule 17's safe pattern: forAgency()
 * NEVER creates a row on read, only returns a sensible in-memory default
 * (multiplier 3.0) when the agency has never opened the settings screen.
 * A row is only ever written when the agency explicitly saves.
 */
class RentalApplicationQualifyingSetting extends Model
{
    use BelongsToAgency;

    public const DEFAULT_MULTIPLIER = 3.00;

    protected $fillable = ['agency_id', 'income_to_rent_multiplier'];

    protected $casts = [
        'income_to_rent_multiplier' => 'decimal:2',
    ];

    public static function multiplierFor(?int $agencyId): float
    {
        if ($agencyId === null || $agencyId <= 0) {
            return self::DEFAULT_MULTIPLIER;
        }

        $row = static::where('agency_id', $agencyId)->first();

        return $row ? (float) $row->income_to_rent_multiplier : self::DEFAULT_MULTIPLIER;
    }
}
