<?php

namespace App\Http\Controllers\CoreX;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Document;
use App\Models\Property;
use App\Models\RentalApplication;
use App\Models\RentalApplicationStatusHistory;
use App\Services\RentalApplications\RentalApplicationMailer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * AT-392 — Rental Applications, Phase 1. Spec: .ai/specs/rental-applications.md
 *
 * Dedicated page, deliberately NOT the e-sign wizard: the agent never signs
 * this document, and every field is optional (contact is the only required
 * link). See the spec's "Why" section for the full reasoning.
 */
class RentalApplicationController extends Controller
{
    use \App\Http\Controllers\Concerns\AuthorizesRentalApplicationAccess;

    /**
     * Search fields (spec §"Agent-side hardening"): contact full name,
     * contact email, property address (both the linked Property's own
     * address and the free-text property_address_override), and the
     * application id itself as the "reference" (an agent quoting "#42").
     * Sortable columns: contact name, property, status, sent date. Default
     * sort: created_at desc (newest first) — unchanged from before this
     * standard, now explicit and user-controllable.
     */
    private function applySearchSortAndDateRange($query, Request $request, string $dateColumn, string $defaultSort)
    {
        if ($request->filled('q')) {
            $q = trim((string) $request->string('q'));
            $query->where(function ($w) use ($q) {
                $w->where('id', 'like', "%{$q}%")
                    ->orWhere('property_address_override', 'like', "%{$q}%")
                    ->orWhereHas('contact', fn ($c) => $c->where('first_name', 'like', "%{$q}%")
                        ->orWhere('last_name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%"))
                    ->orWhereHas('property', fn ($p) => $p->where('address', 'like', "%{$q}%")
                        ->orWhere('title', 'like', "%{$q}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate($dateColumn, '>=', $request->date('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate($dateColumn, '<=', $request->date('date_to'));
        }

        $sortable = ['contact' => 'contacts.last_name', 'property' => 'properties.address', 'status' => 'status', 'date' => $dateColumn];
        $sort = $sortable[$request->string('sort')->toString()] ?? $defaultSort;
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';

        if (in_array($sort, ['contacts.last_name', 'properties.address'], true)) {
            $query->leftJoin($sort === 'contacts.last_name' ? 'contacts' : 'properties', $sort === 'contacts.last_name' ? 'contacts.id' : 'properties.id', '=', $sort === 'contacts.last_name' ? 'rental_applications.contact_id' : 'rental_applications.property_id')
                ->orderBy($sort, $direction)
                ->select('rental_applications.*');
        } else {
            $query->orderBy($sort, $direction);
        }

        return $query;
    }

    public function index(Request $request): View
    {
        $query = RentalApplication::visibleTo($request->user())
            ->with(['contact', 'property'])
            ->whereNotIn('status', ['returned', 'under_assessment', 'approved', 'declined']);

        $this->applySearchSortAndDateRange($query, $request, 'created_at', 'created_at');

        $applications = $query->paginate(25)->withQueryString();

        $archived = null;
        if ($request->boolean('archived')) {
            $archived = RentalApplication::visibleTo($request->user())
                ->onlyTrashed()
                ->with(['contact', 'property'])
                ->orderByDesc('deleted_at')
                ->paginate(25, ['*'], 'archived_page')
                ->withQueryString();
        }

        return view('corex.rental-applications.index', compact('applications', 'archived'));
    }

    /**
     * Johan, 2026-09-07 — real-use bug: uploadDocuments() only ever advances
     * status sent -> in_progress (it never reaches 'returned', which requires
     * the full sign-both-declarations submit — see
     * RentalApplicationSigningController::submit()). This screen's status
     * filter excluded 'in_progress' entirely, so an applicant who uploaded a
     * real document without finishing the full signature flow was invisible
     * here — not because the document was broken (it was correctly filed,
     * linked, and rendered on show()), but because the APPLICATION never
     * surfaced on the one screen named for reviewing incoming applicant
     * activity. 'in_progress' also still shows on index() — deliberately
     * left there too rather than removed, so nothing an agent currently
     * relies on seeing disappears as a side effect of this fix.
     */
    public function returned(Request $request): View
    {
        $query = RentalApplication::visibleTo($request->user())
            ->with(['contact', 'property', 'signatures'])
            ->whereIn('status', ['in_progress', 'returned', 'under_assessment', 'approved', 'declined', 'withdrawn']);

        // Status filtering is centralised in applySearchSortAndDateRange()
        // now that index() needs it too — removed the duplicate here.
        $this->applySearchSortAndDateRange($query, $request, 'submitted_at', 'submitted_at');

        $applications = $query->paginate(25)->withQueryString();

        return view('corex.rental-applications.returned', compact('applications'));
    }

    /**
     * AT-392 — Johan, QA1: "no user action... may EVER discard typed
     * input." A failed store() (e.g. a stale contact/property id) redirects
     * back here with old() flashed — but the contact/property picker is
     * Alpine state seeded from nothing, so the agent's search-and-select
     * work was silently wiped even though old() had the ids all along.
     * Resolved server-side (through the same agency-scoped models, so a
     * stale/foreign id just resolves to null rather than leaking anything)
     * and handed to the view to seed the Alpine component's initial state.
     */
    public function create(): View
    {
        $oldContact = old('contact_id') ? Contact::find(old('contact_id')) : null;
        $oldProperty = old('property_id') ? Property::find(old('property_id')) : null;

        return view('corex.rental-applications.create', compact('oldContact', 'oldProperty'));
    }

    /**
     * Lightweight property picker for the create page — deliberately its
     * own endpoint under this feature's own permission, rather than
     * borrowing another feature's search route (e.g. the filing register's,
     * gated on a different permission an agent here may not hold).
     */
    public function searchProperties(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $properties = Property::query()
            ->where('listing_type', 'rental')
            ->when($q !== '', fn ($query) => $query->where(function ($w) use ($q) {
                $w->where('address', 'like', "%{$q}%")->orWhere('title', 'like', "%{$q}%");
            }))
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'address', 'title', 'suburb']);

        return response()->json($properties->map(fn (Property $p) => [
            'id' => $p->id,
            'label' => $p->title ?: trim($p->address . ', ' . $p->suburb, ', '),
        ]));
    }

    /**
     * AT-392 spec §1 — contact required, everything else optional. Nothing
     * here may block a send (BUILD_STANDARD §2, the input-space rule).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'contact_id' => ['required', 'integer', 'exists:contacts,id'],
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
        ]);

        $contact = Contact::findOrFail($validated['contact_id']);

        // Johan, QA1 — "have not even sent anything, yet top left shows
        // sent?" status starts 'draft' (true starting state — nothing has
        // been sent yet) and the token/link are generated here, not inside
        // send(): the online/download link must exist immediately (so an
        // agent can copy and share it manually even for a contact with no
        // email — the spec's own "share the links below directly" route),
        // independent of whether the email-gated Send button is ever used.
        $application = RentalApplication::create(array_merge(
            $this->prefillFromContact($contact),
            [
                'contact_id' => $contact->id,
                'property_id' => $validated['property_id'] ?? null,
                'branch_id' => $request->user()->effectiveBranchId(),
                'created_by_user_id' => $request->user()->id,
                'status' => 'draft',
                'token' => $this->generateToken(),
                'token_expires_at' => now()->addDays(14),
            ],
        ));

        return redirect()
            ->route('corex.rental-applications.show', $application)
            ->with('success', 'Rental application created. Review it, then send.');
    }

    public function show(Request $request, RentalApplication $rentalApplication): View
    {
        $this->guardRentalApplication($rentalApplication);
        $rentalApplication->load(['contact', 'property', 'signatures', 'documents.documentType', 'documents.uploader', 'statusHistory.changedBy']);

        return view('corex.rental-applications.show', compact('rentalApplication'));
    }

    public function update(Request $request, RentalApplication $rentalApplication)
    {
        $this->guardRentalApplication($rentalApplication);

        $validated = $request->validate(array_merge(RentalApplication::fieldValidationRules(), [
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
        ]));

        $fields = collect($validated)->except(['property_id'])->all();
        $fields = array_map(fn ($v) => $v === '' ? null : $v, $fields);

        DB::transaction(function () use ($rentalApplication, $validated, $fields) {
            $rentalApplication->update(array_merge(
                ['property_id' => $validated['property_id'] ?? $rentalApplication->property_id],
                $fields,
            ));

            $this->backfillContactEmail($rentalApplication);
        });

        return redirect()
            ->route('corex.rental-applications.show', $rentalApplication)
            ->with('success', 'Saved.');
    }

    /**
     * Johan, QA1 — "once we have an email for a contact we update the
     * contact." Fill-only, never overwrite: if the contact already has a
     * DIFFERENT email on file, that's real CRM data an agent entered
     * deliberately elsewhere — a document-edit screen must never silently
     * rewrite it. Only fires when the contact's own email is genuinely
     * empty. Uses Contact::auditedQuietUpdate() (the sanctioned "meaningful
     * quiet write" path, AT-321-C) so this shows up in the contact's own
     * audit trail rather than looking like it appeared from nowhere.
     * Agency scoping: $rentalApplication->contact is already resolved
     * through the model relationship, which is itself agency-scoped via
     * Contact's global AgencyScope — this can never reach a contact
     * outside the application's own agency.
     */
    private function backfillContactEmail(RentalApplication $rentalApplication): void
    {
        $email = $rentalApplication->email;
        $contact = $rentalApplication->contact;

        if (! $email || ! $contact || $contact->email) {
            return;
        }

        $contact->auditedQuietUpdate(
            ['email' => $email],
            eventType: 'contact_updated',
            summary: 'Email filled in from a rental application (contact had none on file).',
        );
    }

    /**
     * AT-392 — Johan, QA1: "on returned applications theres statuses at the
     * top, but theres no way to mark application status to what it is?"
     * Only the agent's own judgement calls are settable by hand
     * (RentalApplication::AGENT_SETTABLE_STATUSES) — draft/sent/in_progress/
     * returned are system-recorded facts and stay off this endpoint's
     * allow-list entirely, so there is no way to fake them even with a
     * crafted request. Only reachable once the application has actually
     * been returned (POST_RETURN_STATUSES) — assessing something the
     * applicant hasn't submitted yet makes no sense. Every change is
     * recorded via RentalApplicationStatusHistory::record() — who, when,
     * from what to what — inside the same transaction as the status write.
     */
    public function updateStatus(Request $request, RentalApplication $rentalApplication)
    {
        $this->guardRentalApplication($rentalApplication);

        $validated = $request->validate([
            'status' => ['required', Rule::in(RentalApplication::AGENT_SETTABLE_STATUSES)],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        if (! in_array($rentalApplication->status, RentalApplication::POST_RETURN_STATUSES, true)) {
            return back()->withInput()->with('error', "This application hasn't been submitted yet — there's nothing to assess.");
        }

        $from = $rentalApplication->status;
        $to = $validated['status'];

        if ($from === $to) {
            return back()->with('success', 'Status unchanged.');
        }

        DB::transaction(function () use ($rentalApplication, $from, $to, $validated) {
            $rentalApplication->update(['status' => $to]);

            RentalApplicationStatusHistory::record(
                $rentalApplication,
                $from,
                $to,
                auth()->user(),
                $validated['note'] ?? null,
            );
        });

        return back()->with('success', 'Status updated to ' . str_replace('_', ' ', $to) . '.');
    }

    /**
     * AT-392 spec §4 — one send, two return routes, applicant's choice.
     * Token/link generation now happens in store() for anything created
     * through the normal flow — but this action must not assume that: a
     * legacy record (created before this fix) or any other creation path
     * can still reach here with no token. Self-healing it here, exactly
     * as this method always did, is cheap and removes a real failure mode
     * — found the hard way: with the fallback removed, sending for a
     * token-less record threw building the public link inside the mail
     * content, silently swallowed by sendInvite()'s own catch block, so
     * "could not send" was reported with no clue why.
     *
     * Johan, QA1 — "no email present and resets the form" / "status says
     * sent on something never sent": Send is disabled client-side without
     * a saved email (show.blade.php), but a direct POST must be refused
     * the same way server-side — never trust a disabled attribute alone.
     * And status only ever becomes 'sent' when mail genuinely left
     * (`$mailSent === true`); a failed/refused send must never claim it.
     */
    public function send(Request $request, RentalApplication $rentalApplication)
    {
        $this->guardRentalApplication($rentalApplication);

        $recipientEmail = $rentalApplication->recipientEmail();

        if (! $recipientEmail) {
            return redirect()
                ->route('corex.rental-applications.show', $rentalApplication)
                ->with('error', 'Add an email address and save before sending.');
        }

        if (! $rentalApplication->token) {
            $rentalApplication->token = $this->generateToken();
            $rentalApplication->token_expires_at = now()->addDays(14);
            $rentalApplication->save();
        }

        $mailSent = app(RentalApplicationMailer::class)->sendInvite($rentalApplication);

        if (! $mailSent) {
            return redirect()
                ->route('corex.rental-applications.show', $rentalApplication)
                ->with('error', 'Could not send — please try again.');
        }

        // A resend of an application the applicant has already progressed
        // (in_progress/returned/etc.) must not regress its status back to
        // 'sent' — only the FIRST successful send moves it off 'draft'.
        if ($rentalApplication->status === 'draft') {
            $rentalApplication->status = 'sent';
            $rentalApplication->save();
        }

        return redirect()
            ->route('corex.rental-applications.show', $rentalApplication)
            ->with('success', 'Sent to ' . $recipientEmail . '. Both the download link and the online link are on this page too, if you want to share them another way.');
    }

    public function pdf(RentalApplication $rentalApplication)
    {
        $this->guardRentalApplication($rentalApplication);

        $service = app(\App\Services\RentalApplications\RentalApplicationPdfService::class);
        $path = $service->generate($rentalApplication);

        return response()->download($path, 'Rental Application - ' . ($rentalApplication->full_name ?: $rentalApplication->contact->full_name) . '.pdf')
            ->deleteFileAfterSend(true);
    }

    /**
     * BUILD_STANDARD §1 — full CRUD is the floor. No hard deletes anywhere in
     * CoreX (STANDARDS.md) — RentalApplication already has SoftDeletes, this
     * is the archive action the index/show screens were missing.
     */
    public function destroy(RentalApplication $rentalApplication)
    {
        $this->guardRentalApplication($rentalApplication);

        $rentalApplication->delete();

        return redirect()
            ->route('corex.rental-applications.index')
            ->with('success', 'Rental application archived.');
    }

    /**
     * Johan, 2026-09-07 — full CRUD includes "restore from archive," not
     * just archive. Route-model-binding on a soft-deleted row 404s by
     * default, so the {rentalApplication} parameter is bound explicitly
     * withTrashed() here — the only action in this controller that needs to.
     */
    public function restore(Request $request, int $rentalApplication)
    {
        $application = RentalApplication::withTrashed()->findOrFail($rentalApplication);
        $this->guardRentalApplication($application);

        $application->restore();

        return redirect()
            ->route('corex.rental-applications.index', ['archived' => 1])
            ->with('success', 'Rental application restored.');
    }

    /**
     * Supporting-document download for the agent side (spec §5). Both
     * $rentalApplication and $document implicitly scope via their own
     * BelongsToAgency global scope (a cross-agency id 404s at route-model-
     * binding, before this method ever runs) — the explicit source_type/
     * source_id check below is defense-in-depth against a same-agency
     * agent guessing a document id that belongs to a DIFFERENT application.
     * The own/branch/agency guard below covers the finer-grained visibility
     * tier on top of that agency-level check.
     */
    public function downloadDocument(RentalApplication $rentalApplication, Document $document)
    {
        $this->guardRentalApplication($rentalApplication);

        abort_unless(
            $document->source_type === 'rental_application' && (int) $document->source_id === $rentalApplication->id,
            404
        );

        return $document->downloadResponse();
    }

    /**
     * AT-392 — Johan: "agent should in any case be able to add docs as
     * client can be in the office so agent scans docs to themselves, or
     * even receive via whatsapp etc." The applicant's own upload path is
     * NOT reused directly (that's token-based, unauthenticated, public) —
     * but the exact same Document model, storage convention, allowlist,
     * and soft-delete rule are, so this is not a second document path,
     * just a second AUTHENTICATED entry point onto the one path.
     *
     * `uploaded_by` (already an existing column/relation on Document,
     * `uploader()`) is the ONLY thing that needs setting here — the
     * applicant's own upload never sets it (no authenticated user in that
     * public context), so it's already the natural "who added this"
     * signal with no new column needed. Screens distinguish "From
     * applicant" (uploaded_by null) from "Added by {agent}" (uploaded_by
     * set) purely by checking whether it's null.
     *
     * Agency/branch scoping: Document::create() auto-stamps agency_id from
     * the authenticated acting user via its own BelongsToAgency trait (no
     * withoutAgencyStamping() needed here — that escape hatch is only for
     * the public, unauthenticated upload path).
     */
    public function uploadDocument(Request $request, RentalApplication $rentalApplication)
    {
        $this->guardRentalApplication($rentalApplication);

        $request->validate([
            'supporting_files' => ['required', 'array', 'min:1', 'max:10'],
            'supporting_files.*' => ['file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:15360'],
        ]);

        $filedDocuments = [];
        foreach ($request->file('supporting_files') as $file) {
            $path = $file->store("rental-applications/{$rentalApplication->id}/documents", 'local');

            $document = Document::create([
                'original_name' => $file->getClientOriginalName(),
                'storage_path' => $path,
                'disk' => 'local',
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'source_type' => 'rental_application',
                'source_id' => $rentalApplication->id,
                'branch_id' => $rentalApplication->branch_id,
                'uploaded_by' => $request->user()->id,
            ]);

            $document->contacts()->syncWithoutDetaching([$rentalApplication->contact_id]);
            if ($rentalApplication->property_id) {
                $document->properties()->syncWithoutDetaching([$rentalApplication->property_id]);
            }

            $filedDocuments[] = $document;
        }

        if ($request->wantsJson()) {
            return response()->json([
                'documents' => collect($filedDocuments)->map(fn ($d) => [
                    'id' => $d->id,
                    'name' => $d->original_name,
                    'view_url' => route('corex.rental-applications.documents.download', [$rentalApplication, $d]),
                ]),
            ]);
        }

        return back()->with('success', count($filedDocuments) === 1 ? 'Document added.' : count($filedDocuments) . ' documents added.');
    }

    /**
     * A token must be unique ACROSS every agency, not just the acting user's
     * own — two different agencies' applications must never collide. Uses the
     * model's own sanctioned cross-tenant escape hatch (BelongsToAgency::
     * queryWithoutAgencyScope()), never a raw withoutGlobalScope() call in
     * request code (CLAUDE.md Non-negotiable #7).
     */
    private function generateToken(): string
    {
        do {
            $token = Str::random(64);
        } while (RentalApplication::queryWithoutAgencyScope()->where('token', $token)->exists());

        return $token;
    }

    /**
     * AT-392 spec §1 — prefill wherever a V8 field maps to a real contacts
     * column (see the AT-332 investigation: marital_status, spouse_name,
     * spouse_id, citizenship and a distinct work number do NOT exist on
     * contacts — only these five genuinely map).
     */
    private function prefillFromContact(Contact $contact): array
    {
        return [
            'full_name' => $contact->full_name,
            'id_number' => $contact->id_number,
            'email' => $contact->email,
            'cell' => $contact->phone,
            'current_residential_address' => trim(implode(', ', array_filter([
                $contact->address, $contact->suburb, $contact->city,
            ]))),
        ];
    }

}
