<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use App\Models\Concerns\BelongsToAgency;
class PresentationVersion extends Model
{
    use BelongsToAgency, SoftDeletes;

    protected $fillable = [
        'agency_id',
        'presentation_id',
        'compiled_by',
        'blueprint_version',
        'analytics_run_id',
        'probability_run_id',
        'data_snapshot_json',
        'hydration_summary_json',
        'compiled_at',
        // Phase 3 — AI summary fields.
        'ai_variant_id',
        'ai_summary_text',
        'ai_summary_raw_text',
        'ai_summary_edited_by_agent',
        'ai_summary_generated_at',
        'ai_summary_edited_at',
        'ai_summary_model',
        'ai_summary_prompt_hash',
        'ai_summary_input_facts_json',
        // Build 2 — per-version review lifecycle.
        'review_status',
        'reviewer_user_id',
        'reviewer_locked_at',
        'awaiting_review_at',
        'published_at',
        'archived_at',
        'included_comp_ids_json',
    ];

    protected $casts = [
        'compiled_at'                 => 'datetime',
        'hydration_summary_json'      => 'array',
        'ai_summary_edited_by_agent'  => 'boolean',
        'ai_summary_generated_at'     => 'datetime',
        'ai_summary_edited_at'        => 'datetime',
        'ai_summary_input_facts_json' => 'array',
        // Build 2 — review-flow timestamps.
        'reviewer_locked_at'          => 'datetime',
        'awaiting_review_at'          => 'datetime',
        'published_at'                => 'datetime',
        'archived_at'                 => 'datetime',
        'included_comp_ids_json'      => 'array',
    ];

    // Build 2 — review_status states.
    public const REVIEW_DRAFT           = 'draft';
    public const REVIEW_AWAITING        = 'awaiting_review';
    public const REVIEW_PUBLISHED       = 'published';
    public const REVIEW_ARCHIVED        = 'archived';

    /** Concurrent-reviewer window (minutes). A second agent opening the
     *  version within this window sees the "currently being reviewed by X"
     *  banner. After the window expires the lock is considered stale. */
    public const REVIEWER_LOCK_MINUTES = 10;

    public function reviewerUser()
    {
        return $this->belongsTo(\App\Models\User::class, 'reviewer_user_id');
    }

    public function agentOverrides()
    {
        return $this->hasMany(AgentOverride::class, 'presentation_version_id');
    }

    public function isReviewerLockActive(): bool
    {
        if (!$this->reviewer_user_id || !$this->reviewer_locked_at) return false;
        return $this->reviewer_locked_at
            ->gt(now()->subMinutes(self::REVIEWER_LOCK_MINUTES));
    }

    public function aiVariant()
    {
        return $this->belongsTo(PresentationAiVariant::class, 'ai_variant_id');
    }

    public function hasAiSummary(): bool
    {
        return !empty($this->ai_summary_text);
    }

    public function presentation()
    {
        return $this->belongsTo(Presentation::class);
    }

    public function compiledBy()
    {
        return $this->belongsTo(User::class, 'compiled_by');
    }

    public function getSnapshotArray(): array
    {
        return json_decode($this->data_snapshot_json, true) ?? [];
    }
}
