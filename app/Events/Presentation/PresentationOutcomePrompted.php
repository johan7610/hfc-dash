<?php

declare(strict_types=1);

namespace App\Events\Presentation;

use App\Events\AbstractDomainEvent;

/**
 * Phase 8 — fires when the daily PromptOutcomeCaptureJob dispatches a nudge
 * to an agent about a presentation older than 30 days with no outcome.
 *
 * Slug: presentation_outcome.prompted
 *
 * Visible in the agent's activity feed so they can see the system has
 * already chased them — useful when an outcome is finally captured and
 * the BM wants to know "how long did this take after the nudge".
 */
final class PresentationOutcomePrompted extends AbstractDomainEvent
{
    public function __construct(
        public readonly int $presentationId,
        public readonly int $promptedUserId,
        public readonly ?int $agencyIdValue,
        public readonly int $daysSinceCreation,
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
            'presentation_id'      => $this->presentationId,
            'prompted_user_id'     => $this->promptedUserId,
            'days_since_creation' => $this->daysSinceCreation,
        ];
    }
}
