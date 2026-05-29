<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use App\Models\Concerns\BelongsToAgency;
class PropertySettingItem extends Model
{
    use BelongsToAgency, SoftDeletes;

    protected $fillable = [
        'agency_id','group', 'name', 'sort_order', 'is_default', 'active', 'title_type',
        // Build 3 — only relevant on group='condition_level' rows.
        'adjustment_pct',
    ];

    protected $casts = [
        'sort_order'     => 'integer',
        'is_default'     => 'boolean',
        'active'         => 'boolean',
        'adjustment_pct' => 'decimal:2',
    ];

    // Allowed groups
    const GROUP_CATEGORY        = 'category';
    const GROUP_TYPE            = 'property_type';
    const GROUP_STATUS          = 'property_status';
    const GROUP_MANDATE_TYPE    = 'mandate_type';
    // Build 3 — condition levels with adjustment_pct.
    const GROUP_CONDITION_LEVEL = 'condition_level';

    /** 'Average' is the baseline (0%) and cannot be deleted. The controller
     *  enforces this; the UI surfaces it so the agent knows. */
    public const CONDITION_BASELINE_NAME = 'Average';

    // title_type values (only meaningful on group='category' rows).
    // See .ai/specs/presentation-data-lineage.md §3-A — enforced by
    // MicSnapshotHydrator at comp selection so a vacant land subject
    // never gets compared against sectional title sales.
    public const TITLE_FULL       = 'full_title';
    public const TITLE_SECTIONAL  = 'sectional_title';
    public const TITLE_VACANT     = 'vacant_land';
    public const TITLE_OTHER      = 'other';

    public const TITLE_TYPES = [
        self::TITLE_FULL      => 'Full Title',
        self::TITLE_SECTIONAL => 'Sectional Title',
        self::TITLE_VACANT    => 'Vacant Land',
        self::TITLE_OTHER     => 'Other / Mixed',
    ];

    public function scopeGroup($query, string $group)
    {
        return $query->where('group', $group)->orderBy('sort_order')->orderBy('name');
    }
}
