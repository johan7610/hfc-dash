<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Phase 7 — one row per "refresh this link please" ask from the seller side.
 *
 * Lifecycle: pending → (acknowledged) → resolved | declined | cancelled.
 *
 * A single PresentationSnapshotLink may have many of these (seller pings
 * twice, agent declines the first, resolves the second). Resolution = agent
 * issued a new link and stored its id in resulting_link_id; that new link's
 * id also goes back on the source link's refresh_resulted_in_link_id +
 * superseded_by_link_id so the public viewer auto-resolves to the new one.
 */
final class PresentationRefreshRequest extends Model
{
    use BelongsToAgency, SoftDeletes;

    public const STATUS_PENDING      = 'pending';
    public const STATUS_ACKNOWLEDGED = 'acknowledged';
    public const STATUS_RESOLVED     = 'resolved';
    public const STATUS_DECLINED     = 'declined';
    public const STATUS_CANCELLED    = 'cancelled';

    protected $fillable = [
        'agency_id',
        'presentation_id',
        'snapshot_link_id',
        'recipient_contact_id',
        'requester_name',
        'requester_email',
        'requester_phone',
        'message',
        'fingerprint_hash',
        'ip_masked',
        'user_agent',
        'status',
        'acknowledged_at',
        'acknowledged_by_user_id',
        'resolved_at',
        'resolved_by_user_id',
        'resulting_link_id',
        'resolution_note',
        'declined_at',
        'declined_by_user_id',
        'decline_reason',
    ];

    protected $casts = [
        'acknowledged_at' => 'datetime',
        'resolved_at'     => 'datetime',
        'declined_at'     => 'datetime',
    ];

    public function presentation(): BelongsTo
    {
        return $this->belongsTo(Presentation::class);
    }

    public function link(): BelongsTo
    {
        return $this->belongsTo(PresentationSnapshotLink::class, 'snapshot_link_id');
    }

    public function recipientContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'recipient_contact_id');
    }

    public function acknowledger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by_user_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public function decliner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'declined_by_user_id');
    }

    public function resultingLink(): BelongsTo
    {
        return $this->belongsTo(PresentationSnapshotLink::class, 'resulting_link_id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_ACKNOWLEDGED], true);
    }

    public function isResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }

    public function isDeclined(): bool
    {
        return $this->status === self::STATUS_DECLINED;
    }
}
