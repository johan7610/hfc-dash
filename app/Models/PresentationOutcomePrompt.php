<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 8 — log of outcome-capture nudges dispatched to agents.
 *
 * Used by PromptOutcomeCaptureJob for cooldown enforcement (one prompt
 * per presentation per 30 days) and by BMs to audit "did the agent
 * actually get the prompt before missing the deadline".
 */
final class PresentationOutcomePrompt extends Model
{
    use BelongsToAgency;

    protected $fillable = [
        'presentation_id',
        'agency_id',
        'prompted_user_id',
        'prompted_at',
        'channel',
    ];

    protected $casts = [
        'prompted_at' => 'datetime',
    ];

    public function presentation(): BelongsTo
    {
        return $this->belongsTo(Presentation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prompted_user_id');
    }
}
