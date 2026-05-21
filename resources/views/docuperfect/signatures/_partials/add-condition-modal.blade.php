{{-- E-Sign V3 Phase 1B.5 — Add Condition modal partial.
     Listens for the 'open-add-condition-modal' window event dispatched by
     the "+ Add condition" buttons rendered inside insertable-block partials.

     Required Blade locals:
       $token — recipient signing token (mounted route /sign/{token})

     Spec: .ai/specs/esign-v3-complete-spec.md §7.5.4 --}}

<div x-data="addConditionModalAlpine()" x-init="init()"
     x-show="open" x-cloak
     style="position: fixed; inset: 0; z-index: 9999; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.5);">
    <div style="background: #fff; border-radius: 8px; max-width: 640px; width: 92%; max-height: 88vh; overflow-y: auto; padding: 1.5rem;">

        <h3 style="margin: 0 0 1rem; color: #111827; font-size: 1.05rem; font-weight: 700;"
            x-text="'Add condition to ' + (blockLabel || 'block')"></h3>

        <div style="display: flex; gap: 0.5rem; border-bottom: 1px solid #e5e7eb; margin-bottom: 1rem;">
            <button type="button" @click="tab = 'library'"
                    :style="tab === 'library' ? 'color: #0ea5e9; border-bottom: 2px solid #0ea5e9; margin-bottom: -1px;' : 'color: #6b7280;'"
                    style="background: none; border: none; padding: 0.5rem 0.8rem; font-weight: 600; cursor: pointer; font-size: 0.85rem;">
                From clause library
            </button>
            <button type="button" @click="tab = 'custom'"
                    :style="tab === 'custom' ? 'color: #0ea5e9; border-bottom: 2px solid #0ea5e9; margin-bottom: -1px;' : 'color: #6b7280;'"
                    style="background: none; border: none; padding: 0.5rem 0.8rem; font-weight: 600; cursor: pointer; font-size: 0.85rem;">
                Write custom
            </button>
        </div>

        {{-- Library tab --}}
        <div x-show="tab === 'library'">
            <input type="text" x-model="search" @input.debounce.300ms="fetchClauses()"
                   placeholder="Search clauses…"
                   style="width: 100%; padding: 0.6rem; border: 1px solid #d1d5db; border-radius: 4px; margin-bottom: 0.75rem; font-size: 0.9rem;">
            <div style="max-height: 18rem; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 4px;">
                <template x-if="!clauses.length">
                    <div style="padding: 1rem; color: #6b7280; font-size: 0.85rem; text-align: center;"
                         x-text="loadingClauses ? 'Loading…' : 'No clauses match. Try Write custom.'"></div>
                </template>
                <template x-for="c in clauses" :key="c.id">
                    <button type="button" @click="selectClause(c)"
                            style="display: block; width: 100%; text-align: left; padding: 0.7rem 0.85rem; border: none; border-bottom: 1px solid #f3f4f6; background: #fff; cursor: pointer;">
                        <div style="font-weight: 600; color: #111827; font-size: 0.9rem;" x-text="c.name"></div>
                        <div style="font-size: 0.8rem; color: #6b7280; margin-top: 0.25rem;"
                             x-text="(c.text || '').substring(0, 140)"></div>
                    </button>
                </template>
            </div>
        </div>

        {{-- Custom tab --}}
        <div x-show="tab === 'custom'">
            <textarea x-model="customText" rows="6"
                      placeholder="Type the condition wording…"
                      style="width: 100%; padding: 0.7rem; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.9rem; resize: vertical;"></textarea>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.25rem;">
            <button type="button" @click="close()"
                    style="padding: 0.6rem 1.2rem; background: #f3f4f6; color: #111827; border: none; border-radius: 4px; font-weight: 600; cursor: pointer; font-size: 0.9rem;">
                Cancel
            </button>
            <button type="button" @click="submit()"
                    :disabled="submitting || (!customText && !selectedClauseId)"
                    :style="(submitting || (!customText && !selectedClauseId)) ? 'opacity: 0.4; cursor: not-allowed;' : ''"
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
        tab: 'library',
        blockId: '',
        blockPurpose: '',
        blockLabel: '',
        search: '',
        clauses: [],
        loadingClauses: false,
        customText: '',
        selectedClauseId: null,
        submitting: false,
        error: '',
        clausesUrl: @json(route('docuperfect.clauses.json')),
        storeUrl:   @json(route('signatures.external.addCondition', ['token' => $token])),
        init() {
            window.addEventListener('open-add-condition-modal', (e) => {
                const d = e.detail || {};
                this.blockId = d.blockId || '';
                this.blockPurpose = d.purpose || 'other_conditions';
                this.blockLabel = d.label || 'Other Conditions';
                this.tab = 'library';
                this.search = '';
                this.clauses = [];
                this.customText = '';
                this.selectedClauseId = null;
                this.error = '';
                this.open = true;
                this.fetchClauses();
            });
        },
        async fetchClauses() {
            this.loadingClauses = true;
            try {
                const url = this.clausesUrl + (this.search ? '?q=' + encodeURIComponent(this.search) : '');
                const r = await fetch(url, { headers: { 'Accept': 'application/json' } });
                if (r.ok) {
                    const data = await r.json();
                    this.clauses = Array.isArray(data) ? data : (data.data || data.clauses || []);
                }
            } catch (e) { console.warn(e); }
            this.loadingClauses = false;
        },
        selectClause(c) {
            this.selectedClauseId = c.id;
            this.customText = c.text || c.content || '';
            this.tab = 'custom';
        },
        close() { this.open = false; },
        async submit() {
            this.error = '';
            const content = (this.customText || '').trim();
            if (!content) { this.error = 'Please enter or select a condition.'; return; }
            this.submitting = true;
            try {
                const csrf = document.querySelector('meta[name=csrf-token]').content;
                const r = await fetch(this.storeUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({
                        block_id:          this.blockId,
                        block_purpose:     this.blockPurpose,
                        content:           content,
                        source:            this.selectedClauseId ? 'library' : 'custom',
                        library_clause_id: this.selectedClauseId,
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
</script>
