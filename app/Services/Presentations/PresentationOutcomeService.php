<?php

declare(strict_types=1);

namespace App\Services\Presentations;

use App\Events\Presentation\PresentationOutcomeRecorded;
use App\Models\Deal;
use App\Models\Presentation;
use App\Models\PresentationOutcome;
use App\Models\User;
use App\Notifications\Presentations\WonMandateNotification;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Phase 8 — single entry point for the outcome lifecycle.
 *
 * Writes presentation_outcomes rows, emits the PresentationOutcomeRecorded
 * domain event (auto-audited by LogAgentActivity → agent_activity_events),
 * and pings BMs on wins via WonMandateNotification.
 *
 * Lock window: outcomes are editable for 90 days after recorded_at, then
 * locked by LockOldOutcomesJob. The isEditable() check on the model is
 * the source of truth; this service raises OutcomeLockedException when
 * a caller tries to mutate a locked row.
 *
 * Notifications + events fire OUTSIDE the DB transaction so a mail hiccup
 * never aborts the state change.
 */
final class PresentationOutcomeService
{
    /**
     * Record OR update the outcome on a presentation. Upsert semantics:
     * if a row exists, treat as edit (and respect the lock).
     *
     * @param array{
     *   outcome: string,
     *   cancellation_reason?: string|null,
     *   cancellation_competitor_agency?: string|null,
     *   cancellation_competitor_price?: int|null,
     *   decision_at?: string|\DateTimeInterface|null,
     *   notes?: string|null,
     *   resulted_in_deal_id?: int|null,
     * } $data
     *
     * @throws OutcomeLockedException
     * @throws InvalidArgumentException
     */
    public function record(Presentation $presentation, array $data, User $by): PresentationOutcome
    {
        $this->validate($data, $presentation);

        $existing = PresentationOutcome::where('presentation_id', $presentation->id)->first();
        if ($existing && !$existing->isEditable()) {
            throw new OutcomeLockedException('This outcome was recorded over 90 days ago and is locked for analytics integrity.');
        }

        $attrs = $this->buildAttributes($data, $presentation, $by);

        $outcome = DB::transaction(function () use ($existing, $presentation, $attrs) {
            if ($existing) {
                $existing->forceFill($attrs)->save();
                return $existing;
            }
            return PresentationOutcome::create(array_merge($attrs, [
                'presentation_id' => $presentation->id,
                'agency_id'       => $presentation->agency_id,
            ]));
        });

        $this->dispatchSideEffects($outcome, $by);

        return $outcome->refresh();
    }

    /**
     * Explicit update — same as record() but the caller's intent is "edit
     * an existing row" rather than "upsert". If the row doesn't exist,
     * delegates to record() so the call still does the right thing.
     */
    public function update(Presentation $presentation, array $data, User $by): PresentationOutcome
    {
        return $this->record($presentation, $data, $by);
    }

    /**
     * Presentations created more than $daysOld days ago that still have NO
     * outcome row. Used by the daily PromptOutcomeCaptureJob.
     *
     * @return Collection<int, Presentation>
     */
    public function findOpenOutcomes(int $agencyId, int $daysOld = 30): Collection
    {
        $cutoff = Carbon::now()->subDays($daysOld);

        return Presentation::query()
            ->where('agency_id', $agencyId)
            ->where('created_at', '<=', $cutoff)
            ->whereDoesntHave('outcome')
            ->get();
    }

    /**
     * Called by the deal-registered listener when a deal hits status='R'.
     * If the deal links to a presentation (presentation.deal_id = deal.id)
     * AND that presentation has no outcome yet, auto-record outcome=won_sale.
     *
     * The agent can still edit within 90 days if the categorisation is wrong.
     */
    public function autoRecordWonSaleForDeal(Deal $deal, User $systemUser): ?PresentationOutcome
    {
        $presentation = Presentation::where('deal_id', $deal->id)
            ->whereDoesntHave('outcome')
            ->first();
        if (!$presentation) {
            return null;
        }

        return $this->record($presentation, [
            'outcome'             => PresentationOutcome::OUTCOME_WON_SALE,
            'resulted_in_deal_id' => $deal->id,
            'decision_at'         => $deal->registration_date ?? now()->toDateString(),
            'notes'               => 'Auto-recorded from deal #' . ($deal->deal_no ?? $deal->id) . ' registration.',
        ], $systemUser);
    }

    // ── Internals ───────────────────────────────────────────────────────────

    /**
     * @throws InvalidArgumentException
     */
    private function validate(array $data, Presentation $presentation): void
    {
        $outcome = $data['outcome'] ?? null;
        if (!in_array($outcome, PresentationOutcome::ALL_OUTCOMES, true)) {
            throw new InvalidArgumentException('Invalid outcome value.');
        }

        $reason = $data['cancellation_reason'] ?? null;
        $requiresReason = in_array($outcome, PresentationOutcome::LOST_OUTCOMES, true)
            || $outcome === PresentationOutcome::OUTCOME_OTHER;

        if ($requiresReason && empty($reason)) {
            throw new InvalidArgumentException('Cancellation reason is required for this outcome.');
        }

        if ($reason !== null && !in_array($reason, PresentationOutcome::ALL_CANCELLATION_REASONS, true)) {
            throw new InvalidArgumentException('Invalid cancellation reason value.');
        }

        if ($reason === 'other' && empty(trim((string) ($data['notes'] ?? '')))) {
            throw new InvalidArgumentException('Notes are required when cancellation reason is "other".');
        }

        if (!empty($data['decision_at'])) {
            $dec = Carbon::parse($data['decision_at']);
            if ($dec->isFuture()) {
                throw new InvalidArgumentException('Decision date cannot be in the future.');
            }
        }

        $dealId = $data['resulted_in_deal_id'] ?? null;
        if ($dealId) {
            $deal = Deal::withoutGlobalScopes()->find($dealId);
            if (!$deal || (int) $deal->agency_id !== (int) $presentation->agency_id) {
                throw new InvalidArgumentException('Linked deal must belong to the same agency.');
            }
            // Either the deal already links back to this presentation OR it
            // shares the property. Loose check — the auto-detect path may
            // create a deal first and link later.
            $sameProperty = $presentation->property_id
                && $deal->getAttribute('property_id') === $presentation->property_id;
            $linkedDeal   = (int) ($presentation->deal_id ?? 0) === (int) $deal->id;
            if (!$sameProperty && !$linkedDeal) {
                // Don't block — just log. Real data is messy and we don't
                // want to refuse a valid outcome capture over a soft link.
                Log::info('outcome.deal_link.soft', [
                    'presentation_id' => $presentation->id,
                    'deal_id'         => $deal->id,
                ]);
            }
        }
    }

    private function buildAttributes(array $data, Presentation $presentation, User $by): array
    {
        $outcome = $data['outcome'];
        $isLostOrOther = in_array($outcome, PresentationOutcome::LOST_OUTCOMES, true)
            || $outcome === PresentationOutcome::OUTCOME_OTHER;

        return [
            'outcome'                        => $outcome,
            'cancellation_reason'            => $isLostOrOther ? ($data['cancellation_reason'] ?? null) : null,
            'cancellation_competitor_agency' => $isLostOrOther ? ($data['cancellation_competitor_agency'] ?? null) : null,
            'cancellation_competitor_price'  => $isLostOrOther
                ? (isset($data['cancellation_competitor_price']) ? (int) $data['cancellation_competitor_price'] : null)
                : null,
            'decision_at'         => !empty($data['decision_at']) ? Carbon::parse($data['decision_at'])->toDateString() : null,
            'notes'               => $data['notes'] ?? null,
            'resulted_in_deal_id' => isset($data['resulted_in_deal_id']) ? (int) $data['resulted_in_deal_id'] : null,
            'recorded_by_user_id' => $by->id,
            'recorded_at'         => now(),
        ];
    }

    private function dispatchSideEffects(PresentationOutcome $outcome, User $by): void
    {
        try {
            event(new PresentationOutcomeRecorded(
                presentationOutcomeId: $outcome->id,
                presentationId:        $outcome->presentation_id,
                outcome:               $outcome->outcome,
                cancellationReason:    $outcome->cancellation_reason,
                decisionAt:            $outcome->decision_at?->toDateString(),
                resultedInDealId:      $outcome->resulted_in_deal_id,
                agencyIdValue:         $outcome->agency_id,
                actorUserIdValue:      $by->id,
            ));
        } catch (\Throwable $e) {
            Log::warning('outcome.event_dispatch_failed', [
                'outcome_id' => $outcome->id,
                'error'      => $e->getMessage(),
            ]);
        }

        if (in_array($outcome->outcome, PresentationOutcome::WON_OUTCOMES, true)) {
            $this->dispatchWonMandateNotifications($outcome);
        }
    }

    private function dispatchWonMandateNotifications(PresentationOutcome $outcome): void
    {
        try {
            // BMs + Principals in the agency. Role values match the rest of
            // the codebase (see CalendarVisibilityResolver, LeaveApplicationController).
            $recipients = User::where('agency_id', $outcome->agency_id)
                ->whereIn('role', ['branch_manager', 'principal'])
                ->where('id', '!=', $outcome->recorded_by_user_id)
                ->get();

            foreach ($recipients as $recipient) {
                $recipient->notify(new WonMandateNotification($outcome->id));
            }
        } catch (\Throwable $e) {
            Log::warning('outcome.won_notify_failed', [
                'outcome_id' => $outcome->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
