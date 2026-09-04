<?php

namespace App\Http\Controllers;

use App\Models\RentalApplication;
use App\Models\RentalApplicationSignature;
use App\Models\Scopes\AgencyScope;
use App\Services\RentalApplications\RentalApplicationPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * AT-392 spec §4b — the public tokenised route, modelled on the existing
 * /sign/{token} mechanism (SigningController) but for a document the agent
 * never signs. Reused, not reinvented: same token shape, same 14-day expiry
 * convention, same no-identity-leak treatment of an expired/used link.
 */
class RentalApplicationSigningController extends Controller
{
    private function findByToken(string $token): RentalApplication
    {
        return RentalApplication::withoutGlobalScope(AgencyScope::class)
            ->where('token', $token)
            ->with(['contact', 'property', 'signatures', 'agency', 'branch'])
            ->firstOrFail();
    }

    public function show(string $token): View
    {
        $application = $this->findByToken($token);

        if ($application->token_expires_at && $application->token_expires_at->isPast()) {
            return view('rental-applications.public.unavailable', ['reason' => 'expired']);
        }

        if (in_array($application->status, ['returned', 'under_assessment', 'approved', 'declined', 'withdrawn'], true)) {
            return view('rental-applications.public.already-submitted', compact('application'));
        }

        return view('rental-applications.public.show', compact('application'));
    }

    /**
     * AT-392 spec §3 — the applicant signs twice here (declaration +
     * TPN consent) in one sitting. Every field optional; only the two
     * signature captures are required to submit online.
     */
    public function submit(Request $request, string $token)
    {
        $application = $this->findByToken($token);

        if ($application->token_expires_at && $application->token_expires_at->isPast()) {
            return redirect()->route('rental-applications.public.show', $token)
                ->with('error', 'This link has expired.');
        }

        if (in_array($application->status, ['returned', 'under_assessment', 'approved', 'declined', 'withdrawn'], true)) {
            return redirect()->route('rental-applications.public.show', $token);
        }

        // BUILD_STANDARD §2 — every field is optional (nullable passes on
        // empty/absent), but a MALFORMED value must be rejected with a clear
        // message, never allowed through to crash the date/decimal cast on
        // save(). This is a public, unauthenticated endpoint — validation
        // here is the only thing standing between a tampered field and a 500.
        $validated = $request->validate(array_merge(RentalApplication::fieldValidationRules(), [
            'declaration_signature' => ['required', 'string'],
            'tpn_consent_signature' => ['required', 'string'],
        ]));

        $fields = collect($validated)->except(['declaration_signature', 'tpn_consent_signature'])->all();
        // Optional-and-empty must never error (BUILD_STANDARD §2) — a blank
        // string is stored as NULL, never coerced into breaking a date/decimal cast.
        $fields = array_map(fn ($v) => $v === '' ? null : $v, $fields);

        $application->fill($fields);
        $application->delivery_mode = 'online';
        $application->status = 'returned';
        $application->submitted_at = now();
        $application->save();

        $this->storeSignature($application, 'declaration', $validated['declaration_signature'], $request);
        $this->storeSignature($application, 'tpn_consent', $validated['tpn_consent_signature'], $request);

        return redirect()->route('rental-applications.public.show', $token)
            ->with('success', 'Thank you — your application has been submitted.');
    }

    /**
     * AT-392 spec §5 — reuses the SAME allowlist/size-cap contract as
     * SigningController::uploadSupportingDocuments() (pdf,jpg,jpeg,png,doc,docx,
     * 15MB/file, max 10 files), filed through the shared `documents` table.
     */
    public function uploadDocuments(Request $request, string $token)
    {
        $application = $this->findByToken($token);

        if ($application->token_expires_at && $application->token_expires_at->isPast()) {
            return redirect()->route('rental-applications.public.show', $token)
                ->with('error', 'This link has expired.');
        }

        $request->validate([
            'supporting_files' => ['required', 'array', 'min:1', 'max:10'],
            'supporting_files.*' => ['file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:15360'],
            'document_type_id' => ['nullable', 'integer', 'exists:document_types,id'],
        ]);

        $filed = 0;
        foreach ($request->file('supporting_files') as $file) {
            $path = $file->store("rental-applications/{$application->id}/documents", 'local');

            // withoutAgencyStamping() — this route has no authenticated user in
            // the normal case, but an agent previewing their own sent link
            // while still logged in (possibly switched into a different agency
            // context) must never cause the document to be mis-tenanted; it
            // always files against the APPLICATION's own agency, explicitly.
            $document = \App\Models\Document::withoutAgencyStamping(fn () => \App\Models\Document::create([
                'original_name' => $file->getClientOriginalName(),
                'storage_path' => $path,
                'disk' => 'local',
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'document_type_id' => $request->input('document_type_id'),
                'source_type' => 'rental_application',
                'source_id' => $application->id,
                'agency_id' => $application->agency_id,
                'branch_id' => $application->branch_id,
            ]));

            $document->contacts()->syncWithoutDetaching([$application->contact_id]);
            if ($application->property_id) {
                $document->properties()->syncWithoutDetaching([$application->property_id]);
            }

            $filed++;
        }

        if ($application->status === 'sent') {
            $application->status = 'in_progress';
            $application->save();
        }

        return redirect()->route('rental-applications.public.show', $token)
            ->with('success', $filed === 1
                ? 'Your document was uploaded.'
                : "Your {$filed} documents were uploaded.");
    }

    public function pdf(string $token)
    {
        $application = $this->findByToken($token);

        $path = app(RentalApplicationPdfService::class)->generate($application);

        return response()->download($path, 'Rental Application.pdf')->deleteFileAfterSend(true);
    }

    private function storeSignature(RentalApplication $application, string $kind, string $dataUrl, Request $request): void
    {
        // Signature pad payload is a data: URI (image/png;base64,...) — same
        // capture shape the e-sign signing view already uses.
        if (! preg_match('/^data:image\/png;base64,(.+)$/', $dataUrl, $m)) {
            return;
        }

        $binary = base64_decode($m[1]);
        $path = "rental-applications/{$application->id}/signatures/" . $kind . '-' . Str::random(8) . '.png';
        Storage::disk('local')->put($path, $binary);

        RentalApplicationSignature::updateOrCreate(
            ['rental_application_id' => $application->id, 'kind' => $kind],
            [
                'signature_path' => $path,
                'signed_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]
        );
    }
}
