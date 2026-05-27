<?php

declare(strict_types=1);

namespace App\Events\Presentation;

use App\Events\AbstractDomainEvent;

/**
 * Phase 8 — fires when an agent records (or updates) the outcome of a presentation.
 *
 * Slug derivation by LogAgentActivity:
 *   PresentationOutcomeRecorded → presentation_outcome.recorded
 *
 * Renders in the property timeline + contact (seller) timeline + agent
 * activity feed. Drives win-rate dashboards downstream.
 */
final class PresentationOutcomeRecorded extends AbstractDomainEvent
{
    public function __construct(
        public readonly int $presentationOutcomeId,
        public readonly int $presentationId,
        public readonly string $outcome,
        public readonly ?string $cancellationReason,
        public readonly ?string $decisionAt,
        public readonly ?int $resultedInDealId,
        public readonly ?int $agencyIdValue,
        public readonly ?int $actorUserIdValue,
        ?string $traceId = null,
    ) {
        parent::__construct($traceId);
    }

    public function agencyId(): ?int
    {
        return $this->agencyIdValue;
    }

    public function actorUserId(): ?int
    {
        return $this->actorUserIdValue;
    }

    public function subject(): ?array
    {
        return ['App\\Models\\Presentation', $this->presentationId];
    }

    public function context(): array
    {
        return [
            'presentation_outcome_id' => $this->presentationOutcomeId,
            'presentation_id'         => $this->presentationId,
            'outcome'                 => $this->outcome,
            'cancellation_reason'     => $this->cancellationReason,
            'decision_at'             => $this->decisionAt,
            'resulted_in_deal_id'     => $this->resultedInDealId,
        ];
    }
}
