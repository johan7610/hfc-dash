{{-- DESIGN SYSTEM COMPLIANCE: UI_DESIGN_SYSTEM.md v 2026-04-20 --}}
{{-- AT-392 Phase 2 — agent review split-screen. Johan's own words: "application
     gets returned, agent open application - sees application and supporting docs
     on left panel of screen... then have a place on the right panel to input
     things like - income, salary / etc etc... doing the calcs to the bottom to
     see if tenant qualifies." --}}
@extends('layouts.corex')

@section('corex-content')
<div class="w-full"
     x-data="rentalReview({
         saveUrl: '{{ route('corex.rental-applications.review.assessment', $rentalApplication) }}',
         initial: {
             monthly_income: {{ $assessment->monthly_income !== null ? $assessment->monthly_income : 'null' }},
             other_monthly_income: {{ $assessment->other_monthly_income !== null ? $assessment->other_monthly_income : 'null' }},
             monthly_expenses: {{ $assessment->monthly_expenses !== null ? $assessment->monthly_expenses : 'null' }},
             notes: {{ Js::from($assessment->notes) }},
         },
         initialResult: {{ Js::from($result) }},
         initialSavedAt: {{ $assessment->exists ? Js::from($assessment->updated_at->toIso8601String()) : 'null' }},
     })">

    <div class="rounded-md px-6 py-4 corex-page-banner flex items-center justify-between">
        <div>
            <h1 class="text-base font-bold leading-tight" style="color: var(--text-primary);">
                Application Review — {{ $rentalApplication->contact->full_name ?? $rentalApplication->contact->first_name . ' ' . $rentalApplication->contact->last_name }}
            </h1>
            <p class="text-xs" style="color: var(--text-muted);">
                {{ $rentalApplication->property_address_override ?? optional($rentalApplication->property)->address ?? 'No property linked' }}
            </p>
        </div>
        <a href="{{ route('corex.rental-applications.show', $rentalApplication) }}" class="corex-btn-outline text-xs">Back to application</a>
    </div>

    {{-- Layout, 2026-09-07 — Johan rejected the original 50/50 grid ("makes the
         document unreadable, defeats the purpose"). CoreX already solves "dominant
         document + narrow working panel" twice — copied the proven pattern exactly
         rather than inventing new proportions:
           - resources/views/docuperfect/signatures/external/sign.blade.php
             (.recipient-doc-main flex:1 1 auto / .recipient-amend-col flex:0 0 260px)
           - resources/views/docuperfect/signatures/review.blade.php
             (.review-main flex:1 1 0% / .review-aside width:260px flex:0 0 260px,
             which itself notes "260px column matching cc6's recipient panel")
         Same two-region shape here: review-main (dominant, application + documents)
         and review-aside (fixed 260px, the agent's own working inputs). Stacks
         below 1280px, same breakpoint family as the signature review screen. --}}
    {{-- Independent scrolling, 2026-09-07 — Johan: "the right and left panels
         should scroll independently... to get more screen for the loaded pdf
         to show." Copied the same proven mechanism as
         docuperfect/signatures/review.blade.php's #agentAmendPanel: each
         column gets its own max-height (viewport minus a fixed margin) and
         its own scrollbar, so scrolling the document list never drags the
         assessment panel off-screen and vice versa. align-self:stretch is
         load-bearing there too — without it a sticky/capped column with no
         taller sibling has zero scroll travel. --}}
    <style>
        .rental-review-columns { display: flex; flex-direction: column; gap: 20px; }
        .rental-review-main    { flex: 1 1 auto; min-width: 0; }
        .rental-review-aside   { width: 100%; }
        @media (min-width: 1280px) {
            .rental-review-columns { flex-direction: row; gap: 16px; align-items: stretch; }
            .rental-review-main    { max-height: calc(100vh - 32px); overflow-y: auto; }
            .rental-review-aside   { flex: 0 0 260px; width: 260px; align-self: stretch; position: sticky; top: 16px; max-height: calc(100vh - 32px); overflow-y: auto; }
        }
    </style>

    <div class="rental-review-columns mt-5">

        {{-- MAIN — the submitted application + supporting documents, viewable on screen. Dominant column. --}}
        <div class="rental-review-main space-y-4">
            {{-- Collapsed by default, 2026-09-07 — Johan: "collapse on the submitted
                 application section to get extra screen to view on [for the PDF]."
                 Defaults CLOSED (not just collapsible) because every field here also
                 lives on the main application record one click away, whereas the
                 documents below are the one thing this screen adds — so the extra
                 height goes to the actual point of this screen by default. One click
                 re-opens it; nothing here is destroyed or hidden permanently. --}}
            <div class="rounded-md p-4" style="background: var(--surface); border: 1px solid var(--border);" x-data="{ summaryOpen: false }">
                <button type="button" class="w-full flex items-center justify-between text-left" @click="summaryOpen = !summaryOpen">
                    <h2 class="text-sm font-semibold" style="color: var(--text-primary);">Submitted Application</h2>
                    <span class="text-xs" style="color: var(--ds-blue, #2563eb);" x-text="summaryOpen ? 'Hide' : 'Show'"></span>
                </button>
                <div x-show="summaryOpen" x-cloak class="mt-3">
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-xs">
                        <dt style="color: var(--text-muted);">Employer</dt><dd>{{ $rentalApplication->employer_name ?? '—' }}</dd>
                        <dt style="color: var(--text-muted);">Position</dt><dd>{{ $rentalApplication->employer_position ?? '—' }}</dd>
                        <dt style="color: var(--text-muted);">Monthly salary (self-reported)</dt><dd>{{ $rentalApplication->monthly_salary !== null ? 'R ' . number_format($rentalApplication->monthly_salary, 2) : '—' }}</dd>
                        <dt style="color: var(--text-muted);">Current rental amount</dt><dd>{{ $rentalApplication->current_rental_amount !== null ? 'R ' . number_format($rentalApplication->current_rental_amount, 2) : '—' }}</dd>
                        <dt style="color: var(--text-muted);">Current landlord</dt><dd>{{ $rentalApplication->current_landlord_name ?? '—' }}</dd>
                        <dt style="color: var(--text-muted);">Adults / Children</dt><dd>{{ $rentalApplication->adults ?? '—' }} / {{ $rentalApplication->children ?? '—' }}</dd>
                    </dl>
                    <a href="{{ route('corex.rental-applications.show', $rentalApplication) }}" class="text-xs inline-block mt-3" style="color: var(--ds-blue, #2563eb);">View / edit full application &rarr;</a>
                </div>
            </div>

            <div class="rounded-md p-4" style="background: var(--surface); border: 1px solid var(--border);">
                <h2 class="text-sm font-semibold mb-3" style="color: var(--text-primary);">
                    Supporting Documents
                    <span class="ds-badge ds-badge-default">{{ $documents->count() }}</span>
                </h2>

                @if($documents->isEmpty())
                    <p class="text-xs" style="color: var(--text-muted);">No supporting documents have been uploaded yet.</p>
                @else
                    <div x-data="{ open: null }" class="space-y-2">
                        @foreach($documents as $row)
                            @php $document = $row['document']; @endphp
                            <div class="rounded-md border" style="border-color: var(--border);">
                                <button type="button" class="w-full flex items-center justify-between px-3 py-2 text-xs text-left"
                                        @click="open = (open === {{ $document->id }} ? null : {{ $document->id }})">
                                    <span>{{ $document->original_name }}</span>
                                    <span class="flex items-center gap-2">
                                        @if(!$row['inline_viewable'])
                                            <span class="ds-badge ds-badge-default" title="This file type cannot be previewed on screen — download it to view it.">No preview</span>
                                        @endif
                                        <span x-text="open === {{ $document->id }} ? 'Hide' : 'View'" style="color: var(--ds-blue, #2563eb);"></span>
                                    </span>
                                </button>
                                <div x-show="open === {{ $document->id }}" x-cloak class="px-3 pb-3">
                                    @if($row['inline_viewable'])
                                        {{-- #navpanes=0 — the browser's OWN native PDF viewer (Chrome/PDFium),
                                             not ours; this is the standard PDF open-parameter it honours to
                                             default its page-thumbnail sidebar closed. Johan: "left page panel
                                             should not load as default... agents will not find that [how to
                                             close it]." The panel is still reachable via the viewer's own
                                             hamburger/sidebar-toggle icon — this only changes its default
                                             state, nothing we control beyond that (see spec: this toolbar and
                                             its tools are entirely the browser's, not a CoreX surface). --}}
                                        <iframe src="{{ route('corex.rental-applications.documents.view', [$rentalApplication, $document]) }}#navpanes=0"
                                                title="{{ $document->original_name }}"
                                                style="width:100%;height:60vh;border:1px solid var(--border,#e3e8f0);border-radius:8px;background:#f8fafc;"></iframe>
                                    @else
                                        <p class="text-xs mb-2" style="color: var(--text-muted);">
                                            This file type ({{ $document->mime_type ?? 'unknown' }}) can't be shown on screen — download it to view it.
                                        </p>
                                    @endif
                                    <a href="{{ route('corex.rental-applications.documents.download', [$rentalApplication, $document]) }}" class="text-xs" style="color: var(--ds-blue, #2563eb);">Download &rarr;</a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- ASIDE — the agent's own affordability capture + suggestive calculation. Narrow, fixed 260px working column. --}}
        <div class="rental-review-aside rounded-md p-4" style="background: var(--surface); border: 1px solid var(--border);">
            <h2 class="text-sm font-semibold mb-1" style="color: var(--text-primary);">Affordability Assessment</h2>
            <p class="text-xs mb-4" style="color: var(--text-muted);">
                Your own notes — nothing here is sent to the applicant or shown anywhere else. Saved automatically as you type.
            </p>

            <div class="space-y-3">
                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Monthly income</label>
                    <input type="number" step="0.01" min="0" class="corex-input text-sm w-full"
                           x-model.number="fields.monthly_income" @blur="save()" placeholder="0.00">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Other monthly income</label>
                    <input type="number" step="0.01" min="0" class="corex-input text-sm w-full"
                           x-model.number="fields.other_monthly_income" @blur="save()" placeholder="0.00">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Monthly expenses / existing debt</label>
                    <input type="number" step="0.01" min="0" class="corex-input text-sm w-full"
                           x-model.number="fields.monthly_expenses" @blur="save()" placeholder="0.00">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Notes</label>
                    <textarea rows="3" class="corex-input text-sm w-full" x-model="fields.notes" @blur="save()"></textarea>
                </div>
            </div>

            {{-- Unmistakable save state, 2026-09-07 — Johan: "no save button visible
                 anywhere?" and "is that filled in from where? ... no save to save
                 this." Two real gaps, not one: (1) autosave-on-blur DOES fire and
                 DOES persist (confirmed against the DB — this is real agent-entered
                 data, not seeded), but a plain 12px text line with no background is
                 easy to miss; (2) the indicator only ever reflected THIS session's
                 own save events — on a fresh page load with already-saved data it
                 showed nothing at all, which looks identical to "never saved."
                 Fixed: seeded from the record's real updated_at on load, shown as a
                 persistent badge (icon + background), not a message that can vanish
                 unnoticed. --}}
            <div class="flex items-center gap-1.5 text-xs mt-2 px-2 py-1 rounded-md" x-show="saveStatus"
                 :style="saveError ? 'background: var(--ds-red-soft, #fef2f2); color: var(--ds-red, #dc2626);' : 'background: var(--ds-emerald-soft, #ecfdf5); color: var(--ds-emerald, #059669);'">
                <span x-show="!saveError && saveStatus !== 'Saving…'">&check;</span>
                <span x-text="saveStatus"></span>
            </div>

            {{-- The calculation — SUGGESTIVE ONLY. Johan: "The marking is only
                 suggestive to the agent to spot. not rule of thumb." Never a
                 blocking gate, never an auto-decision — text only. --}}
            <div class="rounded-md p-3 mt-4" style="background: var(--ds-slate-soft, #f1f5f9); border: 1px solid var(--border);">
                <p class="text-[11px] font-semibold uppercase tracking-wide mb-2" style="color: var(--text-muted);">Suggested check — not a rule</p>
                <template x-if="result.label === 'incomplete'">
                    <p class="text-sm" style="color: var(--text-muted);">Enter income and check the rent on the application to see a suggestion here.</p>
                </template>
                <template x-if="result.label !== 'incomplete'">
                    <div class="text-sm space-y-1">
                        <p>Total income: <strong x-text="formatR(result.total_income)"></strong></p>
                        <p>Rent required (income &times; <span x-text="result.multiplier"></span>): <strong x-text="formatR(result.required_income)"></strong></p>
                        <p class="mt-2">
                            <span class="ds-badge" :class="result.meets_threshold ? 'ds-badge-success' : 'ds-badge-warning'"
                                  x-text="result.meets_threshold ? 'Income appears to cover the rent — worth a closer look either way' : 'Income may not cover the rent — worth a closer look'"></span>
                        </p>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>

<script>
function rentalReview({ saveUrl, initial, initialResult, initialSavedAt }) {
    return {
        fields: initial,
        result: initialResult ?? { label: 'incomplete' },
        // Seeded from the record's real updated_at, not blank — a fresh page
        // load with already-saved data must say so immediately, not only
        // after the agent's next edit (see comment above the badge).
        saveStatus: initialSavedAt ? ('Saved at ' + formatTime(initialSavedAt)) : '',
        saveError: false,
        saveTimer: null,
        save() {
            clearTimeout(this.saveTimer);
            this.saveTimer = setTimeout(() => {
                this.saveStatus = 'Saving…';
                this.saveError = false;
                fetch(saveUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(this.fields),
                }).then(r => r.json()).then(data => {
                    if (data.ok) {
                        this.result = data.result;
                        this.saveStatus = data.saved_at ? ('Saved at ' + formatTime(data.saved_at)) : 'Saved';
                    } else {
                        this.saveError = true;
                        this.saveStatus = 'Could not save — try again';
                    }
                }).catch(() => {
                    this.saveError = true;
                    this.saveStatus = 'Could not save — check your connection';
                });
            }, 150);
        },
        formatR(v) {
            return v === null || v === undefined ? '—' : 'R ' + Number(v).toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
    };
}

function formatTime(iso) {
    return new Date(iso).toLocaleTimeString('en-ZA', { hour: '2-digit', minute: '2-digit' });
}
</script>
@endsection
