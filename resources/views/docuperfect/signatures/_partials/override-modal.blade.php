{{-- E-Sign V3 Phase 1B.5 — Strikethrough Override modal partial.
     Listens for the 'open-override-modal' window event dispatched by clicks
     on numbered clauses inside the rendered signing document.

     Required Blade locals:
       $token — recipient signing token

     Spec: .ai/specs/esign-v3-complete-spec.md §7.5.5 --}}

<div x-data="overrideModalAlpine()" x-init="init()"
     x-show="open" x-cloak
     style="position: fixed; inset: 0; z-index: 9999; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.5);">
    <div style="background: #fff; border-radius: 8px; max-width: 640px; width: 92%; max-height: 88vh; overflow-y: auto; padding: 1.5rem;">

        <h3 style="margin: 0 0 0.4rem; color: #111827; font-size: 1.05rem; font-weight: 700;">
            Override clause <span x-text="clauseRef"></span>
        </h3>
        <p style="margin: 0 0 1rem; color: #6b7280; font-size: 0.85rem;">
            The original clause stays visible but is struck through. Your replacement
            wording appears in Other Conditions and goes to the agent for review.
        </p>

        <div style="padding: 0.8rem; background: color-mix(in srgb, #dc2626 8%, transparent); border-left: 3px solid #dc2626; border-radius: 4px; margin-bottom: 1rem;">
            <div style="font-size: 0.7rem; color: #dc2626; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600;">
                Original clause (will be struck through)
            </div>
            <p style="margin: 0.4rem 0 0; color: #4b5563; text-decoration: line-through;"
               x-text="clauseOriginalText"></p>
        </div>

        <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #111827; margin-bottom: 0.3rem;">
            Replacement wording
        </label>
        <textarea x-model="replacement" rows="5"
                  placeholder="Type the new wording for this clause…"
                  style="width: 100%; padding: 0.7rem; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.9rem; resize: vertical;"></textarea>

        <details style="margin-top: 0.8rem;">
            <summary style="cursor: pointer; color: #6b7280; font-size: 0.85rem;">
                Or pick from the clause library
            </summary>
            <input type="text" x-model="search" @input.debounce.300ms="fetchClauses()"
                   placeholder="Search clauses…"
                   style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 4px; margin-top: 0.5rem; font-size: 0.85rem;">
            <div style="max-height: 12rem; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 4px; margin-top: 0.4rem;">
                <template x-for="c in clauses" :key="c.id">
                    <button type="button" @click="selectClause(c)"
                            style="display: block; width: 100%; text-align: left; padding: 0.55rem 0.75rem; border: none; border-bottom: 1px solid #f3f4f6; background: #fff; cursor: pointer; font-size: 0.85rem;">
                        <div style="font-weight: 600; color: #111827;" x-text="c.name"></div>
                        <div style="font-size: 0.78rem; color: #6b7280;" x-text="(c.text || '').substring(0, 100)"></div>
                    </button>
                </template>
            </div>
        </details>

        <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.25rem;">
            <button type="button" @click="close()"
                    style="padding: 0.6rem 1.2rem; background: #f3f4f6; color: #111827; border: none; border-radius: 4px; font-weight: 600; cursor: pointer; font-size: 0.9rem;">
                Cancel
            </button>
            <button type="button" @click="submit()"
                    :disabled="submitting || !replacement"
                    :style="(submitting || !replacement) ? 'opacity: 0.4; cursor: not-allowed;' : ''"
                    style="padding: 0.6rem 1.4rem; background: #dc2626; color: #fff; border: none; border-radius: 4px; font-weight: 600; cursor: pointer; font-size: 0.9rem;">
                <span x-text="submitting ? 'Saving…' : 'Strike through &amp; replace'"></span>
            </button>
        </div>

        <div x-show="error" x-cloak
             style="margin-top: 0.8rem; padding: 0.6rem; background: #fee2e2; color: #991b1b; border-radius: 4px; font-size: 0.85rem;"
             x-text="error"></div>
    </div>
</div>

<script>
function overrideModalAlpine() {
    return {
        open: false,
        clauseRef: '',
        clauseOriginalText: '',
        replacement: '',
        search: '',
        clauses: [],
        selectedClauseId: null,
        submitting: false,
        error: '',
        clausesUrl: @json(route('docuperfect.clauses.json')),
        storeUrl:   @json(route('signatures.external.proposeStrikethrough', ['token' => $token])),
        init() {
            window.addEventListener('open-override-modal', (e) => {
                const d = e.detail || {};
                this.clauseRef = d.clauseRef || '';
                this.clauseOriginalText = d.clauseText || '';
                this.replacement = '';
                this.selectedClauseId = null;
                this.search = '';
                this.clauses = [];
                this.error = '';
                this.open = true;
            });
        },
        async fetchClauses() {
            try {
                const url = this.clausesUrl + (this.search ? '?q=' + encodeURIComponent(this.search) : '');
                const r = await fetch(url, { headers: { 'Accept': 'application/json' } });
                if (r.ok) {
                    const data = await r.json();
                    this.clauses = Array.isArray(data) ? data : (data.data || data.clauses || []);
                }
            } catch (e) { console.warn(e); }
        },
        selectClause(c) {
            this.selectedClauseId = c.id;
            this.replacement = c.text || c.content || '';
        },
        close() { this.open = false; },
        async submit() {
            this.error = '';
            const replacement = (this.replacement || '').trim();
            if (!replacement) { this.error = 'Enter the replacement wording.'; return; }
            this.submitting = true;
            try {
                const csrf = document.querySelector('meta[name=csrf-token]').content;
                const r = await fetch(this.storeUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({
                        clause_ref:           this.clauseRef,
                        clause_original_text: this.clauseOriginalText,
                        replacement_content:  replacement,
                        library_clause_id:    this.selectedClauseId,
                    }),
                });
                if (r.ok) {
                    location.reload();
                } else {
                    const j = await r.json().catch(() => ({}));
                    this.error = j.error || ('Save failed (' + r.status + ')');
                }
            } catch (e) {
                this.error = 'Network error: ' + e.message;
            }
            this.submitting = false;
        },
    };
}

// Phase 1B.5 — recipient-signing wiring:
//   * "+ Add condition" buttons rendered by InsertableBlockRenderer
//     dispatch the modal event.
//   * Numbered clauses in the document body are scanned + wrapped at load
//     so clicking one opens the override modal.
(function () {
    function attachAddConditionHandlers() {
        document.querySelectorAll('.btn-add-condition').forEach((btn) => {
            if (btn.__handlerAttached) return;
            btn.__handlerAttached = true;
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                window.dispatchEvent(new CustomEvent('open-add-condition-modal', {
                    detail: {
                        blockId:  btn.dataset.blockId,
                        purpose:  btn.dataset.blockPurpose,
                        label:    btn.dataset.blockLabel,
                    },
                }));
            });
        });
    }

    function attachClauseClickHandlers() {
        // Scan the document body for paragraphs that begin with a clause
        // number like "1.", "5.2", or "12.3.4" — wrap each as a clickable
        // override surface. Skip nodes already inside an insertable-block
        // (those are structured, not subject to override) and skip nodes
        // already processed.
        const root = document.getElementById('signing-document-body')
                   || document.querySelector('.signing-document-body')
                   || document.querySelector('.merged-html')
                   || document.body;
        if (!root) return;

        const paragraphs = root.querySelectorAll('p, li, div.clause');
        const numberRegex = /^\s*(\d+(?:\.\d+)*)\b[\.\s]/;

        paragraphs.forEach((p) => {
            if (p.closest('.insertable-block')) return;
            if (p.dataset.clauseRefScan === 'done') return;
            p.dataset.clauseRefScan = 'done';

            const text = (p.innerText || p.textContent || '').trim();
            if (!text) return;
            const m = text.match(numberRegex);
            if (!m) return;

            const ref = m[1];
            p.setAttribute('data-clause-ref', ref);
            p.style.cursor = 'pointer';
            p.title = 'Click to strike through and replace clause ' + ref;
            p.addEventListener('click', (e) => {
                // Don't trigger if a link / button inside was clicked
                if (e.target.closest('a, button')) return;
                // Don't trigger if already struck through
                if (p.dataset.strikethroughApplied === '1') return;
                window.dispatchEvent(new CustomEvent('open-override-modal', {
                    detail: { clauseRef: ref, clauseText: text },
                }));
            });
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        attachAddConditionHandlers();
        attachClauseClickHandlers();
    });
    // Re-scan after Alpine re-renders (e.g. modal close + DOM update)
    document.addEventListener('alpine:initialized', () => {
        attachAddConditionHandlers();
        attachClauseClickHandlers();
    });
})();
</script>
