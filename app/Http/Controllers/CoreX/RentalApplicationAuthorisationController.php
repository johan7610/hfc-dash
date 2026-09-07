<?php

namespace App\Http\Controllers\CoreX;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\RentalApplication;
use App\Models\RentalApplicationAssessment;
use App\Models\RentalApplicationDocumentHighlight;
use App\Models\RentalApplicationQualifyingSetting;
use App\Models\RentalApplicationStatusHistory;
use App\Models\User;
use App\Services\RentalApplications\RentalApplicationAuditService;
use App\Services\RentalApplications\RentalApplicationMailer;
use App\Services\RentalApplications\RentalApplicationNotifier;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * AT-392 authoriser flow, 2026-09-08. Johan, verbatim: "auth goes through
 * and only the auth can accept / reject / ask for more information etc,"
 * and the two-tier design: "ro then co approval process? so admin or bm
 * acts like the co. selected agents act as ro... ro can approve / decline.
 * but then lets say the tenant speaks to admin and they decide they want to
 * override ro, then can approve / decline with reasons given... like an
 * admin override."
 *
 * A NEW controller, not an addition to RentalApplicationReviewController —
 * access here is gated on RO/CO tier membership (User::isRentalApplicationRO()
 * / isRentalApplicationCO()), a completely different check from the
 * ordinary rental_applications.view permission every agent already has.
 *
 * Tier rules, enforced server-side on every action below, not by hiding a
 * button:
 *   - RO or CO may make the FIRST decision on a pending application
 *     (approve/decline/request-more-info) — reason optional.
 *   - Once a decision exists (status is already approved/declined), only a
 *     CO may change it — that's an OVERRIDE, is_override=true on the audit
 *     row, reason REQUIRED. An RO attempting to override is refused (403).
 */
class RentalApplicationAuthorisationController extends Controller
{
    /** Mime types the browser can render natively — mirrors RentalApplicationReviewController exactly. */
    private const INLINE_VIEWABLE_MIME_PREFIXES = ['application/pdf', 'image/'];

    /**
     * @return array{tier: string, is_override: bool}
     */
    private function guardCanDecide(RentalApplication $rentalApplication): array
    {
        /** @var User|null $user */
        $user = auth()->user();
        abort_unless($user !== null, 403);

        $isRO = $user->isRentalApplicationRO((int) $rentalApplication->agency_id);
        $isCO = $user->isRentalApplicationCO((int) $rentalApplication->agency_id);
        abort_unless($isRO || $isCO, 403, 'Only a configured Reviewer or Override user may act on this application.');

        $alreadyDecided = in_array($rentalApplication->status, ['approved', 'declined'], true);

        if ($alreadyDecided) {
            abort_unless($isCO, 403, 'This application already has a decision — only an Override (CO) user may change it.');

            return ['tier' => 'co', 'is_override' => true];
        }

        abort_unless($rentalApplication->isPendingAuthorisation(), 422, 'This application is not currently awaiting authorisation.');

        return ['tier' => $isCO ? 'co' : 'ro', 'is_override' => false];
    }

    private function guardCanView(RentalApplication $rentalApplication): void
    {
        $user = auth()->user();
        abort_unless($user !== null, 403);
        abort_unless(
            $user->isRentalApplicationRO((int) $rentalApplication->agency_id) || $user->isRentalApplicationCO((int) $rentalApplication->agency_id),
            403,
        );
    }

    /**
     * Everything an agency's RO/CO users currently have waiting on them —
     * the underlying BelongsToAgency global scope on RentalApplication still
     * means a user can only ever see their OWN agency's applications, cross-
     * agency data never reaches this query at all.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->isRentalApplicationRO() || $user->isRentalApplicationCO(), 403,
            'You are not configured as a rental application Reviewer or Override user. Ask an admin to add you in Settings.');

        $applications = RentalApplication::whereNotNull('submitted_for_approval_at')
            ->where('status', 'under_assessment')
            ->with(['contact', 'property'])
            ->orderBy('submitted_for_approval_at')
            ->paginate(20);

        return view('corex.rental-applications.authorisation.index', compact('applications'));
    }

    /**
     * The authoriser's read view. Deliberately reuses the SAME document +
     * highlight + assessment data shape RentalApplicationReviewController::
     * show() builds — Johan: "the authoriser must see the agent's
     * highlights on the documents — that is what persisting marks was for."
     */
    public function show(Request $request, RentalApplication $rentalApplication): View
    {
        $this->guardCanView($rentalApplication);

        $rentalApplication->load(['contact', 'property', 'signatures', 'documents.documentType']);

        $assessment = RentalApplicationAssessment::firstOrNew(
            ['rental_application_id' => $rentalApplication->id],
            ['agency_id' => $rentalApplication->agency_id],
        );

        $multiplier = RentalApplicationQualifyingSetting::multiplierFor((int) $rentalApplication->agency_id);
        $result = $assessment->exists ? $assessment->qualifyingResult($multiplier) : null;

        $highlightedByDocId = RentalApplicationDocumentHighlight::whereIn('document_id', $rentalApplication->documents->pluck('id'))
            ->whereNotNull('highlighted_file_path')
            ->pluck('id', 'document_id');

        $documents = $rentalApplication->documents->map(function (Document $document) use ($highlightedByDocId) {
            return [
                'document' => $document,
                'inline_viewable' => $this->isInlineViewable($document->mime_type),
                'has_highlights' => $highlightedByDocId->has($document->id),
            ];
        });

        $history = $rentalApplication->statusHistory()->with('changedBy')->latest('created_at')->get();
        $auditLog = $rentalApplication->auditLog()->with('user')->latest('created_at')->get();

        $user = $request->user();
        $canOverride = $user->isRentalApplicationCO((int) $rentalApplication->agency_id);
        $alreadyDecided = in_array($rentalApplication->status, ['approved', 'declined'], true);

        return view('corex.rental-applications.authorisation.show', compact(
            'rentalApplication', 'assessment', 'multiplier', 'result', 'documents', 'history', 'auditLog', 'canOverride', 'alreadyDecided'
        ));
    }

    public function approve(
        Request $request,
        RentalApplication $rentalApplication,
        RentalApplicationAuditService $audit,
        RentalApplicationMailer $mailer,
        RentalApplicationNotifier $notifier,
    ) {
        $decision = $this->guardCanDecide($rentalApplication);

        $validated = $request->validate([
            // Johan: "capture the approved amount... update agent rental
            // screen - tenant approved for x amount." Required — the whole
            // point of this outcome is that figure.
            'approved_rental_amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'reason' => $decision['is_override'] ? ['required', 'string', 'max:2000'] : ['nullable', 'string', 'max:2000'],
        ]);

        $fromStatus = $rentalApplication->status;
        $oldAmount = $rentalApplication->approved_rental_amount;

        $rentalApplication->status = 'approved';
        $rentalApplication->approved_rental_amount = $validated['approved_rental_amount'];
        $rentalApplication->save();

        RentalApplicationStatusHistory::record(
            $rentalApplication, $fromStatus, 'approved', $request->user(), $validated['reason'] ?? null,
        );

        $audit->log(
            $rentalApplication,
            eventCategory: 'authorisation',
            eventType: $decision['is_override'] ? 'approved_override' : 'approved',
            user: $request->user(),
            isOverride: $decision['is_override'],
            reason: $validated['reason'] ?? null,
            oldValues: ['status' => $fromStatus, 'approved_rental_amount' => $oldAmount],
            newValues: ['status' => 'approved', 'approved_rental_amount' => $validated['approved_rental_amount']],
            humanSummary: ($decision['is_override'] ? 'Overrode a prior decision to approve' : 'Approved')
                . " for R" . number_format((float) $validated['approved_rental_amount'], 2) . " ({$decision['tier']})",
        );

        $notifier->notifyAgentOfDecision($rentalApplication, 'approved', $validated['reason'] ?? null, $decision['is_override']);
        $mailer->sendApproved($rentalApplication);

        return redirect()->route('corex.rental-applications.authorisation.index')
            ->with('success', 'Application approved.');
    }

    public function decline(
        Request $request,
        RentalApplication $rentalApplication,
        RentalApplicationAuditService $audit,
        RentalApplicationMailer $mailer,
        RentalApplicationNotifier $notifier,
    ) {
        $decision = $this->guardCanDecide($rentalApplication);

        $validated = $request->validate([
            'reason' => $decision['is_override'] ? ['required', 'string', 'max:2000'] : ['nullable', 'string', 'max:2000'],
        ]);

        $fromStatus = $rentalApplication->status;
        $rentalApplication->status = 'declined';
        $rentalApplication->save();

        RentalApplicationStatusHistory::record(
            $rentalApplication, $fromStatus, 'declined', $request->user(), $validated['reason'] ?? null,
        );

        $audit->log(
            $rentalApplication,
            eventCategory: 'authorisation',
            eventType: $decision['is_override'] ? 'declined_override' : 'declined',
            user: $request->user(),
            isOverride: $decision['is_override'],
            reason: $validated['reason'] ?? null,
            oldValues: ['status' => $fromStatus],
            newValues: ['status' => 'declined'],
            humanSummary: ($decision['is_override'] ? 'Overrode a prior decision to decline' : 'Declined') . " ({$decision['tier']})",
        );

        $notifier->notifyAgentOfDecision($rentalApplication, 'declined', $validated['reason'] ?? null, $decision['is_override']);
        // Applicant-facing wording is EXPLICITLY unsettled beyond the agency's
        // own configured template — Johan: "still playing with this idea" on
        // any "how to improve" guidance. The template itself (subject/body,
        // agency-editable) is built and sent here; no extra content invented.
        $mailer->sendDecline($rentalApplication);

        return redirect()->route('corex.rental-applications.authorisation.index')
            ->with('success', 'Application declined.');
    }

    /**
     * The AUTHORISER's "request more information" — a separate thing from
     * the agent's own version (which goes to the applicant). This one goes
     * back to the AGENT — Johan confirmed: "my reading is it goes back to
     * the AGENT, who then decides whether they need to go back to the
     * applicant" and this was subsequently confirmed as correct. Clears
     * submitted_for_approval_at (same marker the agent's submit-for-approval
     * action sets) — the application returns to "agent working," not a new
     * status value.
     */
    public function requestMoreInfo(
        Request $request,
        RentalApplication $rentalApplication,
        RentalApplicationAuditService $audit,
        RentalApplicationNotifier $notifier,
    ) {
        // Not guardCanDecide() — this is only ever a FIRST-stage action (you
        // cannot "request more info" on an application that already has a
        // final decision; that's what override is for), so it always uses
        // the non-override gate directly.
        $user = auth()->user();
        abort_unless($user !== null, 403);
        abort_unless(
            $user->isRentalApplicationRO((int) $rentalApplication->agency_id) || $user->isRentalApplicationCO((int) $rentalApplication->agency_id),
            403,
        );
        abort_unless($rentalApplication->isPendingAuthorisation(), 422, 'This application is not currently awaiting authorisation.');

        // A blank request tells the agent nothing — required, same reasoning
        // as the agent's own request-more-info-from-applicant action.
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $rentalApplication->submitted_for_approval_at = null;
        $rentalApplication->save();

        RentalApplicationStatusHistory::record(
            $rentalApplication, $rentalApplication->status, $rentalApplication->status, $request->user(),
            'Authoriser requested more information: ' . $validated['reason'],
        );

        $audit->log(
            $rentalApplication,
            eventCategory: 'authorisation',
            eventType: 'more_info_requested',
            user: $request->user(),
            reason: $validated['reason'],
            humanSummary: 'Requested more information, returned to agent',
        );

        $notifier->notifyAgentOfDecision($rentalApplication, 'more_info_requested', $validated['reason']);

        return redirect()->route('corex.rental-applications.authorisation.index')
            ->with('success', 'Sent back to the agent for more information.');
    }

    /**
     * The authoriser's own document view — deliberately NOT
     * RentalApplicationReviewController::viewDocumentInline(), which is
     * gated by the AGENT's own/branch/agency guard
     * (AuthorizesRentalApplicationAccess). An authoriser's access model is
     * different — RO/CO tier membership for the agency, not owner/branch of
     * this specific record.
     */
    public function viewDocumentInline(RentalApplication $rentalApplication, Document $document)
    {
        $this->guardCanView($rentalApplication);
        $this->guardDocumentBelongsToApplication($rentalApplication, $document);

        if (! $this->isInlineViewable($document->mime_type)) {
            abort(404);
        }

        return response()->streamDownload(
            function () use ($document) {
                echo $document->decryptedContents();
            },
            $document->original_name,
            ['Content-Type' => $document->mime_type ?: 'application/octet-stream'],
            'inline',
        );
    }

    /** Same substitution RentalApplicationReviewController::highlightedFile() does — the marked-up copy if one exists. */
    public function highlightedFile(RentalApplication $rentalApplication, Document $document)
    {
        $this->guardCanView($rentalApplication);
        $this->guardDocumentBelongsToApplication($rentalApplication, $document);

        $highlight = RentalApplicationDocumentHighlight::where('document_id', $document->id)->first();
        abort_if(! $highlight || ! $highlight->highlighted_file_path, 404);
        abort_unless(\Storage::disk('local')->exists($highlight->highlighted_file_path), 404);

        return response()->streamDownload(
            function () use ($highlight) {
                echo \Storage::disk('local')->get($highlight->highlighted_file_path);
            },
            $document->original_name,
            ['Content-Type' => 'application/pdf'],
            'inline',
        );
    }

    private function guardDocumentBelongsToApplication(RentalApplication $rentalApplication, Document $document): void
    {
        abort_unless(
            $document->source_type === 'rental_application' && (int) $document->source_id === $rentalApplication->id,
            404
        );
    }

    private function isInlineViewable(?string $mimeType): bool
    {
        $mimeType = $mimeType ?? '';
        foreach (self::INLINE_VIEWABLE_MIME_PREFIXES as $prefix) {
            if (str_starts_with($mimeType, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
