<?php

declare(strict_types=1);

namespace App\Models\Docuperfect;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * E-Sign V3 Phase 1B.9 (FIX 1) — agent-initiated post-completion flag
 * removal request, mediated by an authenticated recipient consent step.
 *
 * Lifecycle:
 *   pending   → agent issued the request; recipient hasn't responded yet
 *   consented → recipient authorised + e-signed the consent
 *   rejected  → recipient declined
 *   expired   → consent token TTL (14 days) elapsed without action
 *   cancelled → agent cancelled their own request
 *
 * Spec: .ai/specs/esign-v3-complete-spec.md §7.5.8.
 */
class FlagRemovalRequest extends Model
{
    protected $table = 'flag_removal_requests';

    protected $fillable = [
        'signature_template_id',
        'document_amendment_id',
        'clause_ref',
        'requested_by_user_id',
        'requested_at',
        'reason',
        'recipient_signing_party_id',
        'consent_token',
        'consent_sent_at',
        'consent_received_at',
        'consent_ip_address',
        'consent_user_agent',
        'consent_signature_data',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'requested_at'        => 'datetime',
        'consent_sent_at'     => 'datetime',
        'consent_received_at' => 'datetime',
        'expires_at'          => 'datetime',
    ];

    public const STATUS_PENDING   = 'pending';
    public const STATUS_CONSENTED = 'consented';
    public const STATUS_REJECTED  = 'rejected';
    public const STATUS_EXPIRED   = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    public function signatureTemplate(): BelongsTo
    {
        return $this->belongsTo(SignatureTemplate::class);
    }

    public function amendment(): BelongsTo
    {
        return $this->belongsTo(DocumentAmendment::class, 'document_amendment_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function recipientSigningParty(): BelongsTo
    {
        return $this->belongsTo(SignatureRequest::class, 'recipient_signing_party_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isExpiredNow(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
