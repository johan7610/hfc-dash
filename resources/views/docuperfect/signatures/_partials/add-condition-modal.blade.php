{{-- E-Sign V3 Phase 1B.6 — Add Condition modal (rewritten).

     Recipient-side modal for adding a brand-new condition. No clause-
     library access (recipient writes are always free text). Optionally
     links to an existing numbered clause via `relates_to_clause_ref`.

     Listens for the 'open-add-condition-modal' window CustomEvent
     dispatched by the "+ Add condition" buttons rendered inside
     InsertableBlockRenderer.

     Required Blade locals:
       $token            — recipient signing token (mounted route /sign/{token})
       $numberedClauses  — array of [{ref, preview}] extracted from the body

     Spec: .ai/specs/esign-v3-complete-spec.md §7.5.4 (Phase 1B.6 revision) --}}

<div x-data="addConditionModalAlpine()" x-init="init()"
     x-show="open" x-cloak
     style="position: fixed; inset: 0; z-index: 9999; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.5);">
    <div style="background: #fff; border-radius: 8px; max-width: 640px; width: 92%; max-height: 88vh; overflow-y: auto; padding: 1.5rem;">

        <h3 style="margin: 0 0 0.5rem; color: #111827; font-size: 1.05rem; font-weight: 700;"
            x-text="'Add a condition' + (blockLabel ? ' to ' + blockLabel : '')"></h3>
        <p style="margin: 0 0 1rem; color: #6b7280; font-size: 0.85rem;">
            Describe any additional condition you want included. The agent will
            review your request before it&rsquo;s added to the document.
        </p>

        @if(!empty($numberedClauses))
            <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #111827; margin-bottom: 0.3rem;">
                Does this relate to an existing clause?
                <span style="color: #6b7280; font-weight: 400;">(optional)</span>
            </label>
            <select x-model="relatesToClause"
                    style="width: 100%; padding: 0.55rem; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.9rem; background: #fff; margin-bottom: 1rem;">
                <option value="">No &mdash; this is a new condition</option>
                @foreach($numberedClauses as $clause)
                    <option value="{{ $clause['ref'] }}">
                        Clause {{ $clause['ref'] }} &mdash; {{ $clause['preview'] }}
                    </option>
                @endforeach
            </select>
        @endif

        <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #111827; margin-bottom: 0.3rem;">
            Your proposed condition
        </label>
        <textarea x-model="customText" rows="6"
                  placeholder="Type the condition wording…"
                  style="width: 100%; padding: 0.7rem; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.9rem; resize: vertical;"></textarea>

        <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.25rem;">
            <button type="button" @click="close()"
                    style="padding: 0.6rem 1.2rem; background: #f3f4f6; color: #111827; border: none; border-radius: 4px; font-weight: 600; cursor: pointer; font-size: 0.9rem;">
                Cancel
            </button>
            <button type="button" @click="submit()"
                    :disabled="submitting || !customText.trim()"
                    :style="(submitting || !customText.trim()) ? 'opacity: 0.4; cursor: not-allowed;' : ''"
                    style="padding: 0.6rem 1.4rem; background: #0ea5e9; color: #fff; border: none; border-radius: 4px; font-weight: 600; cursor: pointer; font-size: 0.9rem;">
                <span x-text="submitting ? 'Saving…' : 'Save condition'"></span>
            </button>
        </div>

        <div x-show="error" x-cloak
             style="margin-top: 0.8rem; padding: 0.6rem; background: #fee2e2; color: #991b1b; border-radius: 4px; font-size: 0.85rem;"
             x-text="error"></div>
    </div>
</div>

<script>
function addConditionModalAlpine() {
    return {
        open: false,
        blockId: '',
        blockPurpose: '',
        blockLabel: '',
        customText: '',
        relatesToClause: '',
        submitting: false,
        error: '',
        storeUrl: @json(route('signatures.external.addCondition', ['token' => $token])),
        init() {
            window.addEventListener('open-add-condition-modal', (e) => {
                const d = e.detail || {};
                this.blockId = d.blockId || 'other_conditions';
                this.blockPurpose = d.purpose || 'other_conditions';
                this.blockLabel = d.label || '';
                this.customText = '';
                this.relatesToClause = '';
                this.error = '';
                this.open = true;
            });
        },
        close() { this.open = false; },
        async submit() {
            this.error = '';
            const content = (this.customText || '').trim();
            if (!content) { this.error = 'Please enter the condition wording.'; return; }
            this.submitting = true;
            try {
                const csrf = document.querySelector('meta[name=csrf-token]').content;
                const r = await fetch(this.storeUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({
                        block_id:              this.blockId,
                        block_purpose:         this.blockPurpose,
                        content:               content,
                        source:                'custom',
                        relates_to_clause_ref: this.relatesToClause || null,
                    }),
                });
                if (r.ok) {
                    location.reload();
                } else {
                    const j = await r.json().catch(() => ({}));
                    this.error = j.error || j.message || ('Save failed (' + r.status + ')');
                }
            } catch (e) {
                this.error = 'Network error: ' + e.message;
            }
            this.submitting = false;
        },
    };
}
</script>
