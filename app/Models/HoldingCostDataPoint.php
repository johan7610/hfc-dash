<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Captured monthly holding-cost value tagged with the keys its
 * component AVERAGES by. Powers the Tier 1 (learned average) lookup
 * in HoldingCostEstimator's Tier 0/1/2 resolution chain.
 *
 * Capture-everything model — every agent override, parser-captured
 * line, and property-record seed lands here. Agency exclude-grid
 * (is_excluded flag) sanitises outliers later — no UI this build,
 * schema-ready for the fast-follow.
 */
final class HoldingCostDataPoint extends Model
{
    use BelongsToAgency, SoftDeletes;

    public const COMPONENT_RATES            = 'rates';
    public const COMPONENT_LEVY             = 'levy';
    public const COMPONENT_INSURANCE        = 'insurance';
    public const COMPONENT_UTILITIES        = 'utilities';
    public const COMPONENT_GARDEN           = 'garden';
    public const COMPONENT_POOL             = 'pool';
    public const COMPONENT_SECURITY         = 'security';
    public const COMPONENT_BOND             = 'bond';
    public const COMPONENT_OPPORTUNITY_COST = 'opportunity_cost';

    public const SOURCE_AGENT_OVERRIDE  = 'agent_override';
    public const SOURCE_CMA_IMPORT      = 'cma_import';
    public const SOURCE_MANUAL_CAPTURE  = 'manual_capture';
    public const SOURCE_PROPERTY_RECORD = 'property_record';

    /**
     * Components per title_type. opportunity_cost is calculated
     * (asking × pct / 12), never learned — included only so the
     * resolver knows the full set to compute.
     */
    public const COMPONENTS_SECTIONAL = [
        self::COMPONENT_LEVY,
        self::COMPONENT_RATES,
        self::COMPONENT_INSURANCE,
        self::COMPONENT_UTILITIES,
        self::COMPONENT_OPPORTUNITY_COST,
    ];
    public const COMPONENTS_FREEHOLD = [
        self::COMPONENT_RATES,
        self::COMPONENT_INSURANCE,
        self::COMPONENT_UTILITIES,
        self::COMPONENT_GARDEN,
        self::COMPONENT_POOL,
        self::COMPONENT_SECURITY,
        self::COMPONENT_OPPORTUNITY_COST,
    ];

    protected $fillable = [
        'agency_id',
        'presentation_version_id',
        'property_id',
        'tracked_property_id',
        'component',
        'monthly_value_zar',
        'scheme_name',
        'suburb_normalised',
        'municipality',
        'property_type',
        'title_type',
        'property_value_band',
        'source',
        'source_ref',
        'entered_by_user_id',
        'is_excluded',
        'excluded_by_user_id',
        'excluded_at',
        'exclusion_reason',
    ];

    protected $casts = [
        'monthly_value_zar' => 'integer',
        'is_excluded'       => 'boolean',
        'excluded_at'       => 'datetime',
    ];

    /**
     * Coarse property-value band for averaging insurance + rates
     * against value-similar peers. Buckets are deliberately wide so
     * thin agencies still get a useful sample size.
     *
     * Bands: 0_1M, 1_3M, 3_5M, 5M_PLUS.
     */
    public static function valueBandFor(?int $priceZar): ?string
    {
        if ($priceZar === null || $priceZar <= 0) return null;
        if ($priceZar < 1_000_000)  return '0_1M';
        if ($priceZar < 3_000_000)  return '1_3M';
        if ($priceZar < 5_000_000)  return '3_5M';
        return '5M_PLUS';
    }

    public function presentationVersion(): BelongsTo
    {
        return $this->belongsTo(PresentationVersion::class);
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by_user_id');
    }
}
