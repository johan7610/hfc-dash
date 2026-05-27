<?php

declare(strict_types=1);

namespace App\Http\Controllers\Presentation;

use App\Http\Controllers\Controller;
use App\Models\Presentation;
use App\Models\PresentationOutcome;
use App\Services\Presentations\OutcomeLockedException;
use App\Services\Presentations\PresentationOutcomeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Phase 8 — record / update / view outcomes on a single presentation.
 *
 * Dashboard (index across all presentations) lives in
 * PresentationOutcomesDashboardController.
 */
final class PresentationOutcomeController extends Controller
{
    public function __construct(private readonly PresentationOutcomeService $svc) {}

    /** POST /presentations/{presentation}/outcome */
    public function record(Request $request, Presentation $presentation): RedirectResponse
    {
        $this->guardAgency($request, $presentation);

        $data = $this->validateInput($request);

        try {
            $this->svc->record($presentation, $data, $request->user());
        } catch (OutcomeLockedException $e) {
            return back()->withErrors(['outcome' => $e->getMessage()])->withInput();
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['outcome' => $e->getMessage()])->withInput();
        }

        return redirect()->route('presentations.show', $presentation->id)
            ->with('status', 'Outcome recorded.');
    }

    /** PATCH /presentations/{presentation}/outcome */
    public function update(Request $request, Presentation $presentation): RedirectResponse
    {
        return $this->record($request, $presentation);
    }

    // ── Internals ───────────────────────────────────────────────────────────

    private function validateInput(Request $request): array
    {
        return $request->validate([
            'outcome' => ['required', Rule::in(PresentationOutcome::ALL_OUTCOMES)],
            'cancellation_reason' => ['nullable', Rule::in(PresentationOutcome::ALL_CANCELLATION_REASONS)],
            'cancellation_competitor_agency' => 'nullable|string|max:200',
            'cancellation_competitor_price'  => 'nullable|integer|min:0|max:1000000000',
            'decision_at'         => 'nullable|date|before_or_equal:today',
            'notes'               => 'nullable|string|max:5000',
            'resulted_in_deal_id' => 'nullable|integer|exists:deals,id',
        ]);
    }

    private function guardAgency(Request $request, Presentation $presentation): void
    {
        $effective = $request->user()?->effectiveAgencyId();
        if (!$effective || (int) $presentation->agency_id !== (int) $effective) {
            abort(403, 'Cross-agency access denied.');
        }
    }
}
