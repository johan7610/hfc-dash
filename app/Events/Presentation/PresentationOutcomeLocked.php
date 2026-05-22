<?php

declare(strict_types=1);

namespace App\Events\Presentation;

use App\Events\AbstractDomainEvent;

/**
 * Phase 8 — fires when an outcome auto-locks after 90 days, sealing it for
 * analytics integrity. Silent to the agent (no notification) but appears
 * in the activity feed so the audit trail is complete.
 *
 * Slug: presentation_outcome.locked
 */
final class PresentationOutcomeLocked extends AbstractDomainEvent
{
    public function __construct(
        public readonly int $presentationOutcomeId,
        public readonly int $presentationId,
        public readonly ?int $agencyIdValue,
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
        return null;
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
        ];
    }
}
