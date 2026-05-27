<?php

declare(strict_types=1);

namespace App\Events\AI;

use App\Events\AbstractDomainEvent;
use App\Models\Agency;

/**
 * Fires when an agency crosses an AI budget warning threshold (80% / 95% /
 * 100% by default). Recorded by LogAgentActivity into agent_activity_events
 * as `ai.agency_budget_warning`. Future Phase D listener may send a Slack
 * / email nudge to the agency admin.
 *
 * The fire is one-shot per threshold per month — `Agency::aiBudgetUsedPct()`
 * is checked against `ai_budget_last_warned_at` to avoid notification spam.
 *
 * Spec: .ai/specs/mic-complete-spec.md §4.8 (cost dashboard), Phase B2 brief.
 */
final class AgencyAiBudgetWarning extends AbstractDomainEvent
{
    public function __construct(
        public readonly Agency $agency,
        public readonly int $thresholdPct,
        public readonly float $usedZar,
        public readonly float $budgetZar,
        public readonly float $usedPct,
        ?string $traceId = null,
    ) {
        parent::__construct($traceId);
    }

    public function agencyId(): ?int    { return (int) $this->agency->id; }
    public function actorUserId(): ?int { return null; }

    public function subject(): ?array
    {
        return [Agency::class, (int) $this->agency->id];
    }

    public function context(): array
    {
        return [
            'threshold_pct' => $this->thresholdPct,
            'used_zar'      => $this->usedZar,
            'budget_zar'    => $this->budgetZar,
            'used_pct'      => $this->usedPct,
        ];
    }
}
