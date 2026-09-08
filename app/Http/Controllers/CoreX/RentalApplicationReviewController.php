<?php

namespace App\Http\Controllers\CoreX;

use App\Http\Controllers\Concerns\AuthorizesRentalApplicationAccess;
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

    /**
     * Progressive load, 2026-09-08 — Johan's decision on the measured 9.2s
     * cold-open cost: page 1 + total page count, fast, so the agent can
     * start reading/marking immediately. Any marks already saved for this
     * document come back here too (not split per page) — cheap, and avoids
     * any risk of a mark for a not-yet-loaded page being dropped.
     */
    public function highlightFirstPage(RentalApplication $rentalApplication, Document $document, RentalApplicationDocumentHighlightService $highlights)
    {
        $this->guardRentalApplication($rentalApplication);
        $this->guardDocumentBelongsToApplication($rentalApplication, $document);

        try {
            return response()->json($highlights->firstPagePreview($document));
        } catch (\Throwable $e) {
            \Log::error('Rental application document first-page preview failed', ['document' => $document->id, 'error' => $e->getMessage()]);

            return response()->json(['error' => 'This document could not be opened for highlighting.'], 422);
        }
    }

    /**
     * Progressive load, 2026-09-08 — the remaining pages behind
     * highlightFirstPage() above. Called by the frontend right after the
     * first page renders; the agent can already be reading/marking page 1
     * while this is in flight.
     */
    public function highlightRemainingPages(RentalApplication $rentalApplication, Document $document, RentalApplicationDocumentHighlightService $highlights)
    {
        $this->guardRentalApplication($rentalApplication);
        $this->guardDocumentBelongsToApplication($rentalApplication, $document);

        try {
            return response()->json($highlights->remainingPagePreviews($document));
        } catch (\Throwable $e) {
            \Log::error('Rental application document remaining-pages preview failed', ['document' => $document->id, 'error' => $e->getMessage()]);

            return response()->json(['error' => 'The rest of this document could not be loaded.'], 422);
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

        // RA-04, 2026-09-08 — cc5 found (and I reproduced at real HTTP level:
        // real login, real CSRF, real curl, real DB read) that a malformed or
        // stale-shape mark — e.g. the OLD box shape {x,y,w,h,color} from
        // before this tool was redesigned from rectangles to marker-pen
        // strokes — passed this rule (it's still technically an array) and
        // was then SILENTLY DROPPED by normalizeForStorage() (no 'type', no
        // 'points', no 'text' → fails the shape check → skipped, no error
        // surfaced). Real HTTP 200, has_highlights:false, mark_count:0 —
        // exactly the "nothing typed may ever be lost" failure this codebase
        // exists to prevent, just silent instead of loud. Fixed here: every
        // mark must now explicitly declare a valid type and the fields that
        // type requires, or the WHOLE request is rejected with a real 422
        // naming which mark and why — never a quiet no-op. The shape the
        // CURRENT front-end (review.blade.php's stroke/note marks) actually
        // sends already satisfies this and was re-verified end to end after
        // this change (real HTTP POST → real DB row → real playback file).
        $validated = $request->validate([
            'marks' => ['nullable', 'array', function (string $attribute, $value, \Closure $fail) {
                foreach ((array) $value as $page => $marksOnPage) {
                    if (! is_array($marksOnPage)) {
                        $fail("Page {$page}'s marks must be a list.");
                        continue;
                    }
                    foreach ($marksOnPage as $i => $mark) {
                        if (! is_array($mark)) {
                            $fail("Mark {$page}.{$i} must be an object.");
                            continue;
                        }
                        $type = $mark['type'] ?? null;
                        if ($type === 'note') {
                            if (! isset($mark['text']) || trim((string) $mark['text']) === '') {
                                $fail("Mark {$page}.{$i} is a note but has no text.");
                            }
                        } elseif ($type === 'highlight') {
                            if (! isset($mark['points']) || ! is_array($mark['points']) || count($mark['points']) < 2) {
                                $fail("Mark {$page}.{$i} is a highlight but has fewer than 2 points.");
                            }
                        } else {
                            $fail("Mark {$page}.{$i} has a missing or unrecognised type — expected 'highlight' or 'note'.");
                        }
                    }
                }
            }],
            'marks.*' => ['array'],
            'marks.*.*' => ['array'],
        ]);

        // 2026-09-08 — cc1 and an independent second agent both reproduced:
        // POSTing marks for only SOME of a document's pages returns 200 and
        // SILENTLY WIPES the other pages' already-saved marks, because
        // applyMarks() REPLACES marks_json wholesale with whatever it's
        // given. This is not specific to progressive loading — a stale tab,
        // a slow connection, a double submit, or a retry can all reach this
        // endpoint with an incomplete view of the document, with no button
        // or client-side guard in the way at all. My own earlier
        // verification checked that the rendered PAGE IMAGES came back
        // complete; it never checked whether the SAVED MARKS survived —
        // those are different questions, and this is exactly the class of
        // mistake BUILD_STANDARD.md §5a (written earlier tonight, from this
        // same module's RA-06 defect) exists to name.
        //
        // Fixed here, not client-side: a save must account for EVERY page
        // of the document or it is refused outright — no merge, no partial
        // acceptance. Chosen over merging because the invariant is provable
        // ("this payload is either the complete truth or it's rejected")
        // rather than requiring perfect merge semantics (correctly telling
        // "page 3 has zero marks" apart from "page 3 was never mentioned")
        // to be right in every caller, forever. This also doesn't cost the
        // legitimate progressive-load flow anything new: the frontend
        // already refuses to let an agent save before every page has
        // loaded (see review.blade.php's pagesLoading guard) — this makes
        // that a real server-side guarantee instead of a suggestion a
        // stale tab or a crafted request could simply skip.
        $totalPages = $highlights->totalPageCount($document);
        $providedPages = array_map('intval', array_keys((array) ($validated['marks'] ?? [])));
        sort($providedPages);
        if ($providedPages !== range(0, $totalPages - 1)) {
            return response()->json([
                'error' => 'This document hasn\'t fully finished loading yet — wait for every page to load, then try saving again.',
            ], 422);
        }

        try {
            $highlight = $highlights->applyMarks(
                $document,
                (int) $rentalApplication->agency_id,
                $request->user()->id,
                (array) ($validated['marks'] ?? []),
            );
        } catch (\Throwable $e) {
            // RA-06, 2026-09-08 — this previously interpolated $e->getMessage()
            // straight into the JSON response. For a QueryException that is the
            // raw SQL and driver error text (a real SQLSTATE reached the
            // browser). No exception detail is ever user-facing, regardless of
            // cause — only the log gets the real message.
            \Log::error('Rental application document highlight apply failed', ['document' => $document->id, 'error' => $e->getMessage()]);

            return response()->json(['error' => 'Could not save highlights on this document. Please try again.'], 422);
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
