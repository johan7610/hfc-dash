<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 3i — one row per ambiguous deal→property match awaiting admin review.
 *
 * candidates_json shape: array<{property_id, score, confidence, address, suburb}>
 */
final class DealLinkReviewQueue extends Model
{
    use BelongsToAgency;

    protected $table = 'deal_link_review_queue';

    public const STATUS_PENDING            = 'pending';
    public const STATUS_RESOLVED_LINKED    = 'resolved_linked';
    public const STATUS_RESOLVED_UNLINKED  = 'resolved_unlinked';
    public const STATUS_RESOLVED_SKIP      = 'resolved_skip';

    protected $fillable = [
        'deal_id',
        'agency_id',
        'matched_at',
        'match_status',
        'candidates_json',
        'chosen_property_id',
        'reviewed_at',
        'reviewed_by_user_id',
        'review_note',
    ];

    protected $casts = [
        'matched_at'      => 'datetime',
        'reviewed_at'     => 'datetime',
        'candidates_json' => 'array',
    ];

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function chosenProperty(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'chosen_property_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function isPending(): bool
    {
        return $this->match_status === self::STATUS_PENDING;
    }
}
