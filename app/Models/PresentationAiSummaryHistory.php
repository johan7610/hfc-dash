<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 3 — audit row for one AI summary generation attempt.
 *
 * One row per call to AiSummaryService::generate(). was_saved=true when
 * the agent accepts that generation into the version snapshot. Failed
 * generations have failure_reason populated + generated_text=null.
 */
final class PresentationAiSummaryHistory extends Model
{
    protected $table = 'presentation_ai_summary_history';

    public $timestamps = false;

    protected $fillable = [
        'presentation_id',
        'presentation_version_id',
        'ai_variant_id',
        'generated_text',
        'generated_at',
        'generated_by_user_id',
        'was_saved',
        'tokens_used',
        'latency_ms',
        'failure_reason',
        'prompt_hash',
        'model',
        'created_at',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'created_at'   => 'datetime',
        'was_saved'    => 'boolean',
    ];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(PresentationAiVariant::class, 'ai_variant_id');
    }

    public function presentation(): BelongsTo
    {
        return $this->belongsTo(Presentation::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by_user_id');
    }
}
