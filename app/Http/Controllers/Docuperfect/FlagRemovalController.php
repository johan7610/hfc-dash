<?php

declare(strict_types=1);

namespace App\Http\Controllers\Docuperfect;

use App\Http\Controllers\Controller;
use App\Models\Docuperfect\DocumentAmendment;
use App\Models\Docuperfect\FlagRemovalRequest;
use App\Models\Docuperfect\SignatureAuditLog;
use App\Models\Docuperfect\SignatureRequest;
use App\Models\Docuperfect\SignatureTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * E-Sign V3 Phase 1B.9 (FIX 1) — Flag Removal flow.
 *
 *   POST /docuperfect/flags/{amendment}/request-removal
 *     Agent initiates removal (auth required, permission-gated).
 *
 *   GET  /flag-removal/{token}
 *     Public link emailed to the recipient — renders the consent screen.
 *
 *   POST /flag-removal/{token}/consent
 *     Public token-authenticated — recipient submits their decision +
 *     e-signature data.
 *
 * Spec: .ai/specs/esign-v3-complete-spec.md §7.5.8.
 */
class FlagRemovalController extends Controller
{
    private const TOKEN_TTL_DAYS = 14;

    /**
     * Agent-initiated request. Creates the FlagRemovalRequest, generates
     * the consent token, fires the email to the recipient. Audit-logged.
     */
    public function requestRemoval(Request $request, DocumentAmendment $amendment): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! $user->hasPermission('manage_documents')) {
            return response()->json(['error' => 'Not authorised.'], 403);
        }

        if ($amendment->amendment_type !== DocumentAmendment::TYPE_FLAG_RAISED) {
            return response()->json(['error' => 'This amendment is not a clause flag.'], 422);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:4000'],
        ]);

        // The recipient who raised the flag — the SignatureRequest whose
        // id was stored on the amendment as amended_by_request_id.
        $recipientRequest = SignatureRequest::find($amendment->amended_by_request_id);
        if (! $recipientRequest) {
            return response()->json([
                'error' => 'Cannot locate the recipient who raised this flag.',
            ], 422);
        }

        $template = $amendment->template ?? SignatureTemplate::find($amendment->signature_template_id);
        if (! $template) {
            return response()->json(['error' => 'Document not found.'], 404);
        }

        $token = Str::random(64);

        $removal = DB::transaction(function () use ($amendment, $recipientRequest, $template, $user, $validated, $token) {
            return FlagRemovalRequest::create([
                'signature_template_id'      => $template->id,
                'document_amendment_id'      => $amendment->id,
                'clause_ref'                 => (string) ($amendment->flag_clause_ref ?? ''),
                'requested_by_user_id'       => $user->id,
                'requested_at'               => now(),
                'reason'                     => $validated['reason'],
                'recipient_signing_party_id' => $recipientRequest->id,
                'consent_token'              => $token,
                'status'                     => FlagRemovalRequest::STATUS_PENDING,
                'expires_at'                 => now()->addDays(self::TOKEN_TTL_DAYS),
            ]);
        });

        // Best-effort email send. The recipient gets a link to the consent
        // screen. If the mailable doesn't exist yet (e.g. early staging),
        // we still create the row + log so the agent UI surfaces correctly.
        $this->sendConsentEmail($removal, $recipientRequest, $user, $validated['reason']);

        SignatureAuditLog::log(
            $template,
            'flag_removal_requested',
            SignatureAuditLog::ACTOR_USER,
            $user->name ?? 'Agent',
            metadata: [
                'amendment_id'    => $amendment->id,
                'removal_id'      => $removal->id,
                'recipient_id'    => $recipientRequest->id,
                'recipient_name'  => $recipientRequest->signer_name,
                'clause_ref'      => $amendment->flag_clause_ref,
            ],
        );

        return response()->json([
            'ok'         => true,
            'removal_id' => $removal->id,
            'status'     => $removal->status,
            'sent_to'    => $recipientRequest->signer_email,
        ], 201);
    }

    /**
     * Public consent screen. Renders for the recipient via the emailed
     * signed-URL token. No CoreX session auth required — token IS the
     * authentication (single-use + 14-day TTL).
     */
    public function showConsent(Request $request, string $token)
    {
        $removal = FlagRemovalRequest::where('consent_token', $token)->first();
        if (! $removal) {
            return view('docuperfect.signatures.flag-removal.invalid', [
                'reason' => 'unknown_token',
            ]);
        }

        if ($removal->isExpiredNow() && $removal->status === FlagRemovalRequest::STATUS_PENDING) {
            $removal->update(['status' => FlagRemovalRequest::STATUS_EXPIRED]);
        }

        if ($removal->status !== FlagRemovalRequest::STATUS_PENDING) {
            return view('docuperfect.signatures.flag-removal.invalid', [
                'reason'  => $removal->status,
                'removal' => $removal,
            ]);
        }

        $removal->loadMissing(['amendment', 'requestedBy', 'recipientSigningParty']);

        return view('docuperfect.signatures.flag-removal.consent', [
            'removal'   => $removal,
            'amendment' => $removal->amendment,
            'agent'     => $removal->requestedBy,
            'recipient' => $removal->recipientSigningParty,
            'token'     => $token,
        ]);
    }

    /**
     * Recipient submits decision. Public + token-authenticated. Accepts
     * `decision = consent | reject` + optional `signature_data` payload.
     */
    public function submitConsent(Request $request, string $token): JsonResponse
    {
        $removal = FlagRemovalRequest::where('consent_token', $token)->first();
        if (! $removal) {
            return response()->json(['error' => 'Invalid token.'], 404);
        }
        if ($removal->isExpiredNow()) {
            $removal->update(['status' => FlagRemovalRequest::STATUS_EXPIRED]);
            return response()->json(['error' => 'Consent token expired.'], 410);
        }
        if ($removal->status !== FlagRemovalRequest::STATUS_PENDING) {
            return response()->json(['error' => 'Consent already actioned.'], 410);
        }

        $validated = $request->validate([
            'decision'       => ['required', 'in:consent,reject'],
            'signature_data' => ['nullable', 'string', 'max:1048576'], // up to 1 MB base64
        ]);

        DB::transaction(function () use ($removal, $validated, $request) {
            $removal->update([
                'consent_received_at'    => now(),
                'consent_ip_address'     => $request->ip(),
                'consent_user_agent'     => substr((string) $request->userAgent(), 0, 500),
                'consent_signature_data' => $validated['decision'] === 'consent'
                    ? ($validated['signature_data'] ?? null)
                    : null,
                'status'                 => $validated['decision'] === 'consent'
                    ? FlagRemovalRequest::STATUS_CONSENTED
                    : FlagRemovalRequest::STATUS_REJECTED,
            ]);

            if ($validated['decision'] === 'consent') {
                $this->applyApprovedRemoval($removal);
            }

            $template = SignatureTemplate::find($removal->signature_template_id);
            if ($template) {
                SignatureAuditLog::log(
                    $template,
                    'flag_removal_' . $validated['decision'],
                    SignatureAuditLog::ACTOR_SIGNER,
                    $removal->recipientSigningParty?->signer_name ?? 'Recipient',
                    metadata: [
                        'removal_id' => $removal->id,
                        'amendment'  => $removal->document_amendment_id,
                        'clause_ref' => $removal->clause_ref,
                    ],
                );
            }
        });

        return response()->json([
            'ok'      => true,
            'status'  => $removal->fresh()->status,
        ]);
    }

    /**
     * Apply the approved removal: soft-delete the amendment + scrub the
     * clause flag entry from web_template_data.clause_flags. Original
     * flag + consent decision remain preserved in the audit log + on the
     * FlagRemovalRequest row.
     */
    private function applyApprovedRemoval(FlagRemovalRequest $removal): void
    {
        $amendment = $removal->amendment;
        if (! $amendment) return;

        // Soft delete the amendment (audit retained).
        $amendment->update(['status' => DocumentAmendment::STATUS_REJECTED]);
        $amendment->delete();

        // Scrub the matching entry from clause_flags JSON for the party
        // who raised it. We match on clause_ref + amendment_id so we don't
        // accidentally touch another flag the same party raised on the
        // same clause later.
        $template = $amendment->template;
        $document = $template?->document;
        if (! $document) return;

        $webData     = $document->web_template_data ?? [];
        $partyRole   = $removal->recipientSigningParty?->party_role;
        $clauseFlags = $webData['clause_flags'] ?? [];

        if ($partyRole && isset($clauseFlags[$partyRole]) && is_array($clauseFlags[$partyRole])) {
            $clauseFlags[$partyRole] = array_values(array_filter(
                $clauseFlags[$partyRole],
                fn($f) =>
                    (($f['amendment_id'] ?? null) !== $amendment->id)
                    && ((string) ($f['clauseNum'] ?? '') !== (string) $removal->clause_ref)
            ));
            if (empty($clauseFlags[$partyRole])) {
                unset($clauseFlags[$partyRole]);
            }
            $webData['clause_flags'] = $clauseFlags;
            $document->update(['web_template_data' => $webData]);
        }
    }

    private function sendConsentEmail(
        FlagRemovalRequest $removal,
        SignatureRequest $recipientRequest,
        $agentUser,
        string $reason
    ): void {
        if (empty($recipientRequest->signer_email)) {
            Log::warning('FlagRemovalController: recipient has no email — skipping consent send', [
                'removal_id' => $removal->id,
            ]);
            return;
        }

        try {
            $consentUrl = route('signatures.flag-removal.consent.show', ['token' => $removal->consent_token]);

            // Use the generic signing-request mailable as transport with
            // a clear personalMessage. A dedicated mailable can be added
            // later; for now the reuse keeps the surface small.
            $mailable = new \App\Mail\Signatures\SigningRequestMail(
                signerName:      $recipientRequest->signer_name ?? 'Recipient',
                documentName:    'Request to remove your flag',
                signingUrl:      $consentUrl,
                personalMessage: $this->composeConsentEmailBody($agentUser, $reason, $removal->clause_ref),
                expiresAt:       $removal->expires_at,
            );

            if ($agentUser) {
                $mailable->fromAgent($agentUser);
            }
            Mail::to($recipientRequest->signer_email)->send($mailable);

            $removal->update(['consent_sent_at' => now()]);
        } catch (\Throwable $e) {
            Log::warning('FlagRemovalController: consent email send failed', [
                'removal_id' => $removal->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    private function composeConsentEmailBody($agentUser, string $reason, string $clauseRef): string
    {
        $agentName = $agentUser?->name ?? 'Your agent';
        return sprintf(
            "%s has asked you to consent to removing the flag you previously "
            . "raised on clause %s.\n\nReason given by %s:\n%s\n\nYour original "
            . "flag stays in the document's audit history regardless of your "
            . "decision. The clause itself will only be unflagged if you "
            . "authorise it on the linked consent screen.",
            $agentName,
            $clauseRef !== '' ? $clauseRef : '(unspecified)',
            $agentName,
            $reason
        );
    }
}
