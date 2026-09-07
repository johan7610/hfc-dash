<?php

namespace App\Http\Controllers\CoreX;

use App\Http\Controllers\Concerns\AuthorizesRentalApplicationAccess;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\RentalApplication;
use App\Models\RentalApplicationAssessment;
use App\Models\RentalApplicationDocumentHighlight;
use App\Models\RentalApplicationQualifyingSetting;
use App\Models\RentalApplicationStatusHistory;
use App\Services\RentalApplications\RentalApplicationDocumentHighlightService;
use App\Services\RentalApplications\RentalApplicationMailer;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * AT-392 Phase 2 — the agent review split-screen. Johan's own design-
 * conversation words: "application gets returned, agent open application -
 * sees application and supporting docs on left panel of screen... then have
 * a place on the right panel to input things like - income, salary / etc
 * etc... doing the calcs to the bottom to see if tenant qualifies." Explicit
 * scoping/CRUD standard (BUILD_STANDARD §1) applied identically to every
 * action here via AuthorizesRentalApplicationAccess — the same trait every
 * other rental-applications controller action already uses, so this screen
 * can never grant more access than show()/downloadDocument() already do.
 *
 * Deliberately a NEW controller, not an edit to RentalApplicationController
 * (owned by another lane, actively being edited concurrently) or show.blade.php
 * (owned by another lane, has a queued late-document badge). No shared file
 * touched except one appended route group and one appended settings-form
 * section.
 */
class RentalApplicationReviewController extends Controller
{
    use AuthorizesRentalApplicationAccess;

    /** Mime types the browser can render natively — everything else gets a download-only fallback. */
    private const INLINE_VIEWABLE_MIME_PREFIXES = ['application/pdf', 'image/'];

    public function show(Request $request, RentalApplication $rentalApplication): View
    {
        $this->guardRentalApplication($rentalApplication);
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

        return view('corex.rental-applications.review', compact(
            'rentalApplication', 'assessment', 'multiplier', 'result', 'documents'
        ))->with('isPendingAuthorisation', $rentalApplication->isPendingAuthorisation());
    }

    /**
     * AT-392 authoriser flow — the agent's own "request more information
     * from the applicant" action. Johan: "reuse the existing applicant flow
     * and token rather than inventing a second one" — the applicant's link
     * (rental-applications.public.show) already allows adding documents at
     * any status (cc4's add-after-submit build), so this only needs to
     * notify them and log the request; no status change, no new token.
     */
    public function requestMoreInfoFromApplicant(Request $request, RentalApplication $rentalApplication, RentalApplicationMailer $mailer)
    {
        $this->guardRentalApplication($rentalApplication);

        $validated = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
        ]);

        if (! $rentalApplication->token) {
            return response()->json(['error' => 'This application has no applicant link yet — send it first.'], 422);
        }

        $sent = $mailer->sendMoreInfoRequest($rentalApplication, $validated['note']);

        RentalApplicationStatusHistory::record(
            $rentalApplication,
            $rentalApplication->status,
            $rentalApplication->status,
            $request->user(),
            'Requested more information from applicant: ' . $validated['note'],
        );

        return response()->json([
            'ok' => true,
            'mail_sent' => $sent,
        ]);
    }

    /**
     * AT-392 authoriser flow — the agent hands the application to the
     * authoriser. Deliberately NOT a status change (see RentalApplication::
     * isPendingAuthorisation()) — status stays under_assessment, this
     * timestamp is the marker. Re-submittable any number of times (e.g.
     * after an authoriser asks for more info) — always just bumps the
     * timestamp and logs again.
     */
    public function submitForApproval(Request $request, RentalApplication $rentalApplication)
    {
        $this->guardRentalApplication($rentalApplication);

        abort_unless(in_array($rentalApplication->status, RentalApplication::POST_RETURN_STATUSES, true), 422);

        $rentalApplication->status = 'under_assessment';
        $rentalApplication->submitted_for_approval_at = now();
        $rentalApplication->save();

        RentalApplicationStatusHistory::record(
            $rentalApplication,
            $rentalApplication->status,
            $rentalApplication->status,
            $request->user(),
            'Submitted for authorisation.',
        );

        return response()->json([
            'ok' => true,
            'submitted_for_approval_at' => $rentalApplication->submitted_for_approval_at->toIso8601String(),
        ]);
    }

    /**
     * Autosave — called on every field blur/change from the right panel, not
     * a single final submit. "Nothing the agent types may ever be lost" —
     * this has bitten the feature three times today on other screens, so
     * this endpoint is deliberately fired on every change, not on navigate-
     * away. Every field is optional (BUILD_STANDARD §2) — a partial save is
     * a normal, expected state, not an error.
     */
    public function saveAssessment(Request $request, RentalApplication $rentalApplication)
    {
        $this->guardRentalApplication($rentalApplication);

        $validated = $request->validate([
            'monthly_income' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'other_monthly_income' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'monthly_expenses' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        // Empty-string inputs (a field the agent cleared) must persist as
        // NULL, never as a validation reject or a stray '' in a decimal
        // column (BUILD_STANDARD §2 — optional-and-empty must be accepted
        // gracefully, never break the column's own type contract).
        $fields = array_map(fn ($v) => $v === '' ? null : $v, $validated);

        $assessment = RentalApplicationAssessment::updateOrCreate(
            ['rental_application_id' => $rentalApplication->id],
            array_merge($fields, [
                'agency_id' => $rentalApplication->agency_id,
                'updated_by_user_id' => $request->user()->id,
            ]),
        );

        $multiplier = RentalApplicationQualifyingSetting::multiplierFor((int) $rentalApplication->agency_id);

        return response()->json([
            'ok' => true,
            'result' => $assessment->qualifyingResult($multiplier),
            'saved_at' => $assessment->updated_at?->toIso8601String(),
        ]);
    }

    /**
     * AT-392 Phase 2 usability round — page previews + any marks already
     * saved for this document, for the highlight tool. Mirrors
     * ViewingPackController::redactionData(). Nothing written to disk here —
     * the unmarked preview only lives in this authenticated response.
     */
    public function highlightData(RentalApplication $rentalApplication, Document $document, RentalApplicationDocumentHighlightService $highlights)
    {
        $this->guardRentalApplication($rentalApplication);
        $this->guardDocumentBelongsToApplication($rentalApplication, $document);

        try {
            return response()->json($highlights->pagePreviews($document));
        } catch (\Throwable $e) {
            \Log::error('Rental application document highlight preview failed', ['document' => $document->id, 'error' => $e->getMessage()]);

            return response()->json(['error' => 'This document could not be opened for highlighting.'], 422);
        }
    }

    /**
     * AT-392 Phase 2 usability round — apply the current mark set. Mirrors
     * ViewingPackController::redactDocument()'s request/response shape
     * (fetch + FormData, JSON on success/failure) so the frontend pattern
     * copied from show.blade.php's redactionTool() needs no adaptation here.
     */
    public function applyHighlight(Request $request, RentalApplication $rentalApplication, Document $document, RentalApplicationDocumentHighlightService $highlights)
    {
        $this->guardRentalApplication($rentalApplication);
        $this->guardDocumentBelongsToApplication($rentalApplication, $document);

        // Structural validation only — a mark is one of two shapes (a
        // highlight stroke's points array, or a note's x/y/text), and
        // RentalApplicationDocumentHighlightService::normalizeForStorage()
        // already defensively filters/coerces every field per type before
        // anything reaches the marks_json column (documented there: never
        // trust the raw payload verbatim). Re-litigating the same per-type
        // shape here would just duplicate that gate, not add a real one.
        $validated = $request->validate([
            'marks' => ['nullable', 'array'],
            'marks.*' => ['array'],
            'marks.*.*' => ['array'],
        ]);

        try {
            $highlight = $highlights->applyMarks(
                $document,
                (int) $rentalApplication->agency_id,
                $request->user()->id,
                (array) ($validated['marks'] ?? []),
            );
        } catch (\Throwable $e) {
            \Log::error('Rental application document highlight apply failed', ['document' => $document->id, 'error' => $e->getMessage()]);

            return response()->json(['error' => 'Could not save highlights on this document: ' . $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'has_highlights' => $highlight->highlighted_file_path !== null,
            'mark_count' => collect($highlight->marks_json ?? [])->flatten(1)->count(),
            'saved_at' => $highlight->updated_at?->toIso8601String(),
        ]);
    }

    /**
     * Playback for "the next party" — mirrors ViewingPackController::
     * redactedFile(). Whoever opens this document next (including the agent
     * reopening it) is served the marked-up copy automatically once one
     * exists; 404 if none has been applied yet (the caller falls back to
     * viewDocumentInline for the plain original).
     */
    public function highlightedFile(RentalApplication $rentalApplication, Document $document)
    {
        $this->guardRentalApplication($rentalApplication);
        $this->guardDocumentBelongsToApplication($rentalApplication, $document);

        $highlight = RentalApplicationDocumentHighlight::where('document_id', $document->id)->first();
        abort_if(!$highlight || !$highlight->highlighted_file_path, 404);
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

    /**
     * Inline document view for the left panel — the same scope guard AND the
     * same source_type/source_id defense-in-depth check downloadDocument()
     * already uses, so this can never open a door that action doesn't. PDFs
     * and images stream inline (Content-Disposition: inline); everything
     * else (doc/docx — the allowlist also permits these, and a browser
     * cannot render them natively) redirects to the existing download route
     * rather than attempting a broken inline render.
     */
    public function viewDocumentInline(RentalApplication $rentalApplication, Document $document)
    {
        $this->guardRentalApplication($rentalApplication);
        $this->guardDocumentBelongsToApplication($rentalApplication, $document);

        if (!$this->isInlineViewable($document->mime_type)) {
            return redirect()->route('corex.rental-applications.documents.download', [
                $rentalApplication, $document,
            ]);
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
