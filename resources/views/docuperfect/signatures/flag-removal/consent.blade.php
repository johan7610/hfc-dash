{{-- E-Sign V3 Phase 1B.9 (FIX 1) — Flag Removal consent screen.

     Rendered for the recipient via the emailed signed-URL token. The
     recipient sees what the agent is asking them to remove + the agent's
     reason, and submits an explicit consent decision + e-signature.

     Spec: .ai/specs/esign-v3-complete-spec.md §7.5.8. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Consent to remove flag — {{ $amendment->section_reference ?? 'Clause' }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet">
</head>
<body style="font-family: 'Figtree', Arial, sans-serif; background: #f4f6fb; margin: 0; padding: 2rem 1rem; color: #1f2937;">

<div style="max-width: 720px; margin: 0 auto;">

    <div style="background: #92400e; color: #fff; padding: 1.25rem 1.5rem; border-radius: 8px 8px 0 0;">
        <h1 style="margin: 0; font-size: 1.25rem; font-weight: 700;">
            Consent to remove your flag
        </h1>
        <p style="margin: 0.4rem 0 0; font-size: 0.9rem; opacity: 0.9;">
            Hi {{ $recipient->signer_name ?? 'Recipient' }} — your agent has asked you to
            confirm removal of the flag you raised on clause
            <strong>{{ $removal->clause_ref ?: '(unspecified)' }}</strong>.
        </p>
    </div>

    <div style="background: #fff; padding: 1.5rem; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 8px 8px;">

        <h2 style="margin: 0 0 0.5rem; font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280;">
            Your original flag (will stay in audit history)
        </h2>
        <div style="padding: 0.75rem; background: color-mix(in srgb, #d97706 8%, transparent); border-left: 3px solid #d97706; border-radius: 4px; margin-bottom: 1.25rem;">
            <div style="font-size: 0.7rem; color: #92400e; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600;">
                Original clause
            </div>
            <p style="margin: 0.3rem 0 0.6rem; color: #4b5563;">
                {{ $amendment->original_text ?: '(clause text not stored)' }}
            </p>
            <div style="font-size: 0.7rem; color: #92400e; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; margin-top: 0.4rem;">
                Your suggested change
            </div>
            <p style="margin: 0.3rem 0 0; color: #1f2937;">
                {{ $amendment->flag_reason ?: $amendment->new_text ?: '(no text)' }}
            </p>
        </div>

        <h2 style="margin: 0 0 0.5rem; font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280;">
            Why {{ $agent->name ?? 'the agent' }} is asking
        </h2>
        <div style="padding: 0.75rem; background: #f9fafb; border-left: 3px solid #6b7280; border-radius: 4px; margin-bottom: 1.5rem;">
            <p style="margin: 0; color: #1f2937; white-space: pre-wrap;">{{ $removal->reason }}</p>
        </div>

        <div style="padding: 0.85rem; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 6px; margin-bottom: 1.5rem; font-size: 0.85rem; color: #1e40af;">
            <strong>What happens next?</strong><br>
            By confirming, you authorise the agent to remove your flag from this document.
            Your original flag <em>and</em> this consent decision are both kept in the
            document's permanent audit history. The clause itself is not changed —
            only the flag (concern) on it is cleared. If you do not authorise,
            your flag stays in place.
        </div>

        <form id="consentForm" x-data="consentFormState()" x-init="init()">
            @csrf

            <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #111827; margin-bottom: 0.3rem;">
                Sign your decision (type your full name)
            </label>
            <input type="text" x-model="signatureName" required
                   placeholder="{{ $recipient->signer_name ?? 'Your full name' }}"
                   style="width: 100%; padding: 0.7rem; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.95rem; margin-bottom: 1rem;">

            <div style="display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 0.5rem;">
                <button type="button" @click="submit('reject')"
                        :disabled="submitting"
                        :style="submitting ? 'opacity:0.4; cursor:not-allowed;' : ''"
                        style="padding: 0.7rem 1.4rem; background: #f3f4f6; color: #111827; border: none; border-radius: 4px; font-weight: 600; cursor: pointer; font-size: 0.9rem;">
                    I do not authorise
                </button>
                <button type="button" @click="submit('consent')"
                        :disabled="submitting || !signatureName.trim()"
                        :style="(submitting || !signatureName.trim()) ? 'opacity:0.4; cursor:not-allowed;' : ''"
                        style="padding: 0.7rem 1.4rem; background: #047857; color: #fff; border: none; border-radius: 4px; font-weight: 600; cursor: pointer; font-size: 0.9rem;">
                    <span x-text="submitting ? 'Submitting…' : 'I authorise removal'"></span>
                </button>
            </div>

            <div x-show="error" x-cloak
                 style="margin-top: 1rem; padding: 0.7rem; background: #fee2e2; color: #991b1b; border-radius: 4px; font-size: 0.85rem;"
                 x-text="error"></div>
        </form>
    </div>

    <p style="text-align: center; margin-top: 1rem; font-size: 0.8rem; color: #9ca3af;">
        This link expires on {{ $removal->expires_at->format('d M Y') }}.
        It can only be used once.
    </p>
</div>

<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script>
function consentFormState() {
    return {
        signatureName: '',
        submitting: false,
        error: '',
        init() {},
        async submit(decision) {
            this.error = '';
            if (decision === 'consent' && !this.signatureName.trim()) {
                this.error = 'Type your full name to authorise.';
                return;
            }
            this.submitting = true;
            try {
                const csrf = document.querySelector('meta[name=csrf-token]').content;
                const r = await fetch('{{ route('signatures.flag-removal.consent.submit', ['token' => $token]) }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({
                        decision: decision,
                        signature_data: decision === 'consent' ? this.signatureName : null,
                    }),
                });
                if (r.ok) {
                    document.body.innerHTML = '<div style="max-width:640px;margin:4rem auto;padding:2rem;background:#fff;border-radius:8px;text-align:center;">'
                        + '<h1 style="color:#047857;">Thanks — your decision was recorded.</h1>'
                        + '<p style="color:#4b5563;">You can close this page.</p></div>';
                } else {
                    const j = await r.json().catch(() => ({}));
                    this.error = j.error || ('Submit failed (' + r.status + ')');
                }
            } catch (e) {
                this.error = 'Network error: ' + e.message;
            }
            this.submitting = false;
        },
    };
}
</script>
</body>
</html>
