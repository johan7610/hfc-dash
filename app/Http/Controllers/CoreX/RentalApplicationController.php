<?php

namespace App\Http\Controllers\CoreX;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Property;
use App\Models\RentalApplication;
use App\Services\RentalApplications\RentalApplicationMailer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
    public function index(Request $request): View
    {
        $applications = RentalApplication::with(['contact', 'property'])
            ->whereNotIn('status', ['returned', 'under_assessment', 'approved', 'declined'])
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('corex.rental-applications.index', compact('applications'));
    }

    public function returned(Request $request): View
    {
        $query = RentalApplication::with(['contact', 'property', 'signatures'])
            ->whereIn('status', ['returned', 'under_assessment', 'approved', 'declined', 'withdrawn'])
            ->orderByDesc('submitted_at');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $applications = $query->paginate(25)->withQueryString();

        return view('corex.rental-applications.returned', compact('applications'));
    }

    public function create(): View
    {
        return view('corex.rental-applications.create');
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

        $application = RentalApplication::create(array_merge(
            $this->prefillFromContact($contact),
            [
                'contact_id' => $contact->id,
                'property_id' => $validated['property_id'] ?? null,
                'branch_id' => $request->user()->effectiveBranchId(),
                'created_by_user_id' => $request->user()->id,
                'status' => 'sent',
            ],
        ));

        return redirect()
            ->route('corex.rental-applications.show', $application)
            ->with('success', 'Rental application created. Review it, then send.');
    }

    public function show(RentalApplication $rentalApplication): View
    {
        $rentalApplication->load(['contact', 'property', 'signatures', 'documents.documentType']);

        return view('corex.rental-applications.show', compact('rentalApplication'));
    }

    public function update(Request $request, RentalApplication $rentalApplication)
    {
        $validated = $request->validate(array_merge(RentalApplication::fieldValidationRules(), [
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
        ]));

        $fields = collect($validated)->except(['property_id'])->all();
        $fields = array_map(fn ($v) => $v === '' ? null : $v, $fields);

        $rentalApplication->update(array_merge(
            ['property_id' => $validated['property_id'] ?? $rentalApplication->property_id],
            $fields,
        ));

        return redirect()
            ->route('corex.rental-applications.show', $rentalApplication)
            ->with('success', 'Saved.');
    }

    /**
     * AT-392 spec §4 — one send, two return routes, applicant's choice. The
     * token is generated unconditionally (needed either way: route (a)'s
     * upload-back link and route (b)'s online link are the SAME token) —
     * mirrors SignatureService::generateToken()'s shape (Str::random(64),
     * uniqueness loop) and its 14-day expiry convention.
     */
    public function send(Request $request, RentalApplication $rentalApplication)
    {
        if (! $rentalApplication->token) {
            $rentalApplication->token = $this->generateToken();
            $rentalApplication->token_expires_at = now()->addDays(14);
        }
        $rentalApplication->status = 'sent';
        $rentalApplication->save();

        $mailSent = false;
        if ($rentalApplication->contact->email) {
            $mailSent = app(RentalApplicationMailer::class)->sendInvite($rentalApplication);
        }

        $message = $mailSent
            ? 'Sent to ' . $rentalApplication->contact->email . '. Both the download link and the online link are on this page too, if you want to share them another way.'
            : 'This contact has no email on file — share the links below directly (WhatsApp, print, etc).';

        return redirect()
            ->route('corex.rental-applications.show', $rentalApplication)
            ->with('success', $message);
    }

    public function pdf(RentalApplication $rentalApplication)
    {
        $service = app(\App\Services\RentalApplications\RentalApplicationPdfService::class);
        $path = $service->generate($rentalApplication);

        return response()->download($path, 'Rental Application - ' . ($rentalApplication->full_name ?: $rentalApplication->contact->full_name) . '.pdf')
            ->deleteFileAfterSend(true);
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
