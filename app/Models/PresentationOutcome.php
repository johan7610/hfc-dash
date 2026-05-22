<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Phase 8 — closes the loop on a presentation: what actually happened.
 *
 * One row per presentation. Editable for 90 days, then auto-locked by
 * LockOldOutcomesJob to preserve analytics integrity (so historical
 * win-rate dashboards don't shift retroactively).
 */
final class PresentationOutcome extends Model
{
    use BelongsToAgency, SoftDeletes;

    public const OUTCOME_WON_MANDATE           = 'won_mandate';
    public const OUTCOME_WON_SALE              = 'won_sale';
    public const OUTCOME_LOST_TO_COMPETITOR    = 'lost_to_competitor';
    public const OUTCOME_LOST_TO_NO_DECISION   = 'lost_to_no_decision';
    public const OUTCOME_LOST_TO_PRICE_DISPUTE = 'lost_to_price_dispute';
    public const OUTCOME_LOST_TO_NO_RESPONSE   = 'lost_to_no_response';
    public const OUTCOME_STILL_PENDING         = 'still_pending';
    public const OUTCOME_OTHER                 = 'other';

    public const ALL_OUTCOMES = [
        self::OUTCOME_WON_MANDATE,
        self::OUTCOME_WON_SALE,
        self::OUTCOME_LOST_TO_COMPETITOR,
        self::OUTCOME_LOST_TO_NO_DECISION,
        self::OUTCOME_LOST_TO_PRICE_DISPUTE,
        self::OUTCOME_LOST_TO_NO_RESPONSE,
        self::OUTCOME_STILL_PENDING,
        self::OUTCOME_OTHER,
    ];

    public const WON_OUTCOMES = [
        self::OUTCOME_WON_MANDATE,
        self::OUTCOME_WON_SALE,
    ];

    public const LOST_OUTCOMES = [
        self::OUTCOME_LOST_TO_COMPETITOR,
        self::OUTCOME_LOST_TO_NO_DECISION,
        self::OUTCOME_LOST_TO_PRICE_DISPUTE,
        self::OUTCOME_LOST_TO_NO_RESPONSE,
    ];

    public const ALL_CANCELLATION_REASONS = [
        'price_too_high_seller',
        'price_too_low_seller',
        'commission_concerns',
        'sole_mandate_concerns',
        'family_pressure',
        'existing_relationship',
        'agency_reputation',
        'agent_personality',
        'timing_change',
        'property_issues_discovered',
        'price_match_with_other',
        'other',
    ];

    /** Days after recording before the row is locked from edits. */
    public const LOCK_AFTER_DAYS = 90;

    protected $fillable = [
        'presentation_id',
        'agency_id',
        'outcome',
        'cancellation_reason',
        'cancellation_competitor_agency',
        'cancellation_competitor_price',
        'decision_at',
        'notes',
        'resulted_in_deal_id',
        'recorded_by_user_id',
        'recorded_at',
        'locked',
        'locked_at',
    ];

    protected $casts = [
        'decision_at'                   => 'date',
        'recorded_at'                   => 'datetime',
        'locked'                        => 'boolean',
        'locked_at'                     => 'datetime',
        'cancellation_competitor_price' => 'integer',
    ];

    public function presentation(): BelongsTo
    {
        return $this->belongsTo(Presentation::class);
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class, 'resulted_in_deal_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function isWon(): bool
    {
        return in_array($this->outcome, self::WON_OUTCOMES, true);
    }

    public function isLost(): bool
    {
        return in_array($this->outcome, self::LOST_OUTCOMES, true);
    }

    public function isPending(): bool
    {
        return $this->outcome === self::OUTCOME_STILL_PENDING;
    }

    public function requiresCancellationReason(): bool
    {
        return $this->isLost() || $this->outcome === self::OUTCOME_OTHER;
    }

    public function isEditable(): bool
    {
        if ($this->locked) {
            return false;
        }
        if (!$this->recorded_at) {
            return true;
        }
        return $this->recorded_at->diffInDays(now()) < self::LOCK_AFTER_DAYS;
    }
}
