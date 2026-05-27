<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Deal;
use App\Models\User;
use App\Services\Presentations\PresentationOutcomeService;
use Illuminate\Support\Facades\Log;

/**
 * Phase 8 — auto-record outcome=won_sale when a deal transitions to
 * "registered" (accepted_status='R' OR registration_date set), provided
 * the deal links to a presentation that has no outcome yet.
 *
 * The agent retains 90 days of edit window if the auto-categorisation is
 * wrong (e.g. the deal didn't actually come from this presentation).
 *
 * Lives in App\Observers (not coupled to Deal::booted()) so the dependency
 * runs Presentations → Deals, not the other way around. Registered in
 * AppServiceProvider::boot().
 */
final class DealRegisteredForOutcomeObserver
{
    public function updated(Deal $deal): void
    {
        if (!$this->justBecameRegistered($deal)) {
            return;
        }

        try {
            $systemUser = $this->resolveSystemUser($deal);
            if (!$systemUser) {
                return;
            }
            app(PresentationOutcomeService::class)->autoRecordWonSaleForDeal($deal, $systemUser);
        } catch (\Throwable $e) {
            // Never let outcome auto-detection break a deal save.
            Log::warning('outcome.auto_won_sale.failed', [
                'deal_id' => $deal->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * True iff the just-completed save flipped the deal into the "registered"
     * state. Two signals: accepted_status becoming 'R' OR registration_date
     * being newly set.
     */
    private function justBecameRegistered(Deal $deal): bool
    {
        if ($deal->wasChanged('accepted_status') && $deal->accepted_status === 'R') {
            return true;
        }
        if ($deal->wasChanged('registration_date') && !empty($deal->registration_date)) {
            return true;
        }
        return false;
    }

    /**
     * The User to record as the actor. Prefers the listing agent on the
     * linked presentation (so the activity feed shows their name), falling
     * back to the deal's creating user if we can find one.
     */
    private function resolveSystemUser(Deal $deal): ?User
    {
        $presentation = \App\Models\Presentation::where('deal_id', $deal->id)->first();
        if ($presentation && $presentation->created_by_user_id) {
            $u = User::find($presentation->created_by_user_id);
            if ($u) return $u;
        }
        // Fallback — any active user in the same agency. Used only when the
        // listing agent has been deactivated.
        return User::where('agency_id', $deal->agency_id)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();
    }
}
