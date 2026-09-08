<?php

namespace App\Http\Controllers\CoreX;

use App\Http\Controllers\Concerns\AuthorizesRentalApplicationAccess;
use App\Http\Controllers\Concerns\HandlesRentalApplicationDocumentMarks;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\RentalApplication;
use App\Models\RentalApplicationAssessment;
use App\Models\RentalApplicationDocumentHighlight;
use App\Models\RentalApplicationExpenseItem;
use App\Models\RentalApplicationIncomeItem;
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
    use HandlesRentalApplicationDocumentMarks;

    /** Fulfils HandlesRentalApplicationDocumentMarks's guard requirement with the agent's own access rule. */
    protected function guardDocumentMarkAccess(RentalApplication $rentalApplication, Document $document): void
    {
        $this->guardRentalApplication($rentalApplication);
        $this->guardDocumentBelongsToApplication($rentalApplication, $document);
    }

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
        $assessment->setRelation('incomeItems', $assessment->exists ? $assessment->incomeItems : collect());
        $assessment->setRelation('expenseItems', $assessment->exists ? $assessment->expenseItems : collect());

        $maxRentPercent = RentalApplicationQualifyingSetting::maxRentPercentFor((int) $rentalApplication->agency_id);
        $result = $assessment->exists ? $assessment->qualifyingResult($maxRentPercent) : null;

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
            'rentalApplication', 'assessment', 'maxRentPercent', 'result', 'documents'
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

        // RA-02 (cc5 re-test, Round 8) — every numeric money field on this
        // feature. Round 9 (item 5) — monthly_income/other_monthly_income/
        // monthly_expenses became growable lists; the sanitizer still
        // applies to each item's own 'amount', not a top-level field.
        $incomeItemsInput = array_map(
            fn ($item) => RentalApplication::sanitizeNumericInput((array) $item, ['amount']),
            (array) $request->input('income_items', []),
        );
        $expenseItemsInput = array_map(
            fn ($item) => RentalApplication::sanitizeNumericInput((array) $item, ['amount']),
            (array) $request->input('expense_items', []),
        );
        $request->merge(['income_items' => $incomeItemsInput, 'expense_items' => $expenseItemsInput]);

        $validated = $request->validate([
            'income_items' => ['nullable', 'array'],
            'income_items.*.id' => ['nullable', 'integer'],
            'income_items.*.description' => ['nullable', 'string', 'max:255'],
            'income_items.*.amount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'expense_items' => ['nullable', 'array'],
            'expense_items.*.id' => ['nullable', 'integer'],
            'expense_items.*.description' => ['nullable', 'string', 'max:255'],
            'expense_items.*.amount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'notes' => ['nullable', 'string', 'max:5000'],
            // Round 11 — Johan: "we have to ask the nr of months the bank
            // statement is for." A bank statement's captured lines are a
            // lump sum over this many months, not a monthly figure.
            'statement_months' => ['nullable', 'integer', 'min:1', 'max:36'],
            // Round 16 — Johan: "unpaid transactions on bank statement...
            // this is a dangerous app." A single flag, not a list of
            // amounts — individual declined lines are marked on the
            // document itself via the highlighter.
            'has_unpaid_transactions' => ['nullable', 'boolean'],
        ]);

        // A row the agent never filled in (no description, no amount) is the
        // ever-present trailing "type here to add another" placeholder —
        // Johan: "empty trailing rows must not save as zero-value rows or
        // clutter the record." Filtered server-side too, not just by the
        // frontend, since this is the only thing standing between a crafted
        // request and a junk row.
        $isBlank = fn ($item) => empty($item['description'] ?? null) && (($item['amount'] ?? null) === null || $item['amount'] === '');

        $assessment = RentalApplicationAssessment::updateOrCreate(
            ['rental_application_id' => $rentalApplication->id],
            [
                'agency_id' => $rentalApplication->agency_id,
                'notes' => ($validated['notes'] ?? '') === '' ? null : ($validated['notes'] ?? null),
                'statement_months' => ($validated['statement_months'] ?? '') === '' ? null : ($validated['statement_months'] ?? null),
                'has_unpaid_transactions' => $request->boolean('has_unpaid_transactions'),
                'updated_by_user_id' => $request->user()->id,
            ],
        );

        $this->syncItems(
            $assessment,
            RentalApplicationIncomeItem::class,
            array_values(array_filter($validated['income_items'] ?? [], fn ($i) => ! $isBlank($i))),
        );
        $this->syncItems(
            $assessment,
            RentalApplicationExpenseItem::class,
            array_values(array_filter($validated['expense_items'] ?? [], fn ($i) => ! $isBlank($i))),
        );

        $maxRentPercent = RentalApplicationQualifyingSetting::maxRentPercentFor((int) $rentalApplication->agency_id);
        $assessment = $assessment->fresh(['incomeItems', 'expenseItems']);

        // Round 9 (item 5) — the client must learn each row's real id after
        // its first save, or the NEXT autosave would have no way to match
        // existing rows and would create duplicates instead of updating
        // them. Echoing the canonical saved list back is simpler and safer
        // than the client guessing its own ids.
        return response()->json([
            'ok' => true,
            'result' => $assessment->qualifyingResult($maxRentPercent),
            'income_items' => $assessment->incomeItems->map(fn ($i) => ['id' => $i->id, 'description' => $i->description, 'amount' => $i->amount])->values(),
            'expense_items' => $assessment->expenseItems->map(fn ($i) => ['id' => $i->id, 'description' => $i->description, 'amount' => $i->amount])->values(),
            'saved_at' => $assessment->updated_at?->toIso8601String(),
        ]);
    }

    /**
     * Replace an assessment's income/expense line items with $items,
     * matched by id where the client already has one (a row it typed into
     * on a previous autosave). Rows no longer present are SOFT-deleted
     * (non-negotiable #1 — an agent removing a line from a financial
     * record is a real, recoverable event, never a hard delete), never
     * blind delete-all-then-recreate — that would soft-delete and
     * immediately recreate every unchanged row on every keystroke,
     * turning the audit trail into noise.
     *
     * @param class-string<RentalApplicationIncomeItem>|class-string<RentalApplicationExpenseItem> $modelClass
     */
    private function syncItems(RentalApplicationAssessment $assessment, string $modelClass, array $items): void
    {
        $keptIds = [];
        foreach (array_values($items) as $sortOrder => $item) {
            $attributes = [
                'agency_id' => $assessment->agency_id,
                'rental_application_assessment_id' => $assessment->id,
                'description' => ($item['description'] ?? '') === '' ? null : $item['description'],
                'amount' => ($item['amount'] ?? '') === '' ? null : $item['amount'],
                'sort_order' => $sortOrder,
            ];

            $row = ! empty($item['id'])
                ? $modelClass::where('rental_application_assessment_id', $assessment->id)->find($item['id'])
                : null;

            if ($row) {
                $row->update($attributes);
            } else {
                $row = $modelClass::create($attributes);
            }

            $keptIds[] = $row->id;
        }

        $modelClass::where('rental_application_assessment_id', $assessment->id)
            ->whereNotIn('id', $keptIds ?: [0])
            ->delete();
    }

    // highlightFirstPage(), highlightRemainingPages(), applyHighlight() —
    // 2026-09-08, moved to the shared HandlesRentalApplicationDocumentMarks
    // trait (see its docblock) once the authoriser screen also needed to
    // mark up documents — this controller's own copies would otherwise
    // have been the second, independently-maintainable implementation of
    // the exact partial-save protection the critical fix built.

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
