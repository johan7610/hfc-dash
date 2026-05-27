<?php

declare(strict_types=1);

namespace App\Models\Map;

use App\Models\Concerns\BelongsToAgency;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Phase A.3.2 — per-user, agency-scoped saved-search record for the Map.
 *
 * `filter_payload` carries the full FILTER_DEFAULTS-shaped object from
 * the map page (scope, search, types, ranges, listingStatus, soldWindow,
 * domMin/Max, year range). The model does NOT validate the payload's
 * inner shape — the controller validates on save and the Map page
 * re-applies its own defaults on load.
 */
final class MapSavedSearch extends Model
{
    use BelongsToAgency, HasFactory, SoftDeletes;

    protected $table = 'map_saved_searches';

    protected $fillable = [
        'agency_id', 'user_id', 'name', 'filter_payload', 'is_default',
    ];

    protected $casts = [
        'filter_payload' => 'array',
        'is_default'     => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
