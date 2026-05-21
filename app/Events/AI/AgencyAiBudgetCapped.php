<?php

declare(strict_types=1);

namespace App\Events\AI;

use App\Events\AbstractDomainEvent;
use App\Models\Agency;

/**
 * Fires the first time an AI call is REFUSED in a given month because the
 * agency has reached its hard cap (and overage is not allowed). Subsequent
 * refusals in the same month do NOT re-fire — the `ai_budget_last_hard_stopped_at`
 * timestamp gates the event so the agency admin gets one alert per breach.
 *
 * Different from AINarrativeFailedFallback (which fires per failed call) —
 * this is the agency-level signal "you've hit the wall this month".
 *
 * Spec: .ai/specs/mic-complete-spec.md §4.8, Phase B2 brief.
 */
final class AgencyAiBudgetCapped extends AbstractDomainEvent
{
    public function __construct(
        public readonly Agency $agency,
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
            'used_zar'   => $this->usedZar,
            'budget_zar' => $this->budgetZar,
            'used_pct'   => $this->usedPct,
        ];
    }
}
