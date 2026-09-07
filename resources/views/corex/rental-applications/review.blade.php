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
                    {{-- Viewer, 2026-09-07 — no longer an <iframe> over the raw PDF stream. That
                         handed the document to the BROWSER's own native PDF viewer, whose toolbar,
                         page-thumbnail panel and "changes may be lost" warning all belong to the
                         browser, not CoreX — so nothing drawn in it could ever be saved for the
                         next viewer. Replaced with CoreX's own proven mechanism (same as document
                         redaction — see highlightTool() below): server-rasterized page images in an
                         owned modal, so we own every pixel, and marks persist. --}}
                    <div class="space-y-2">
                        @foreach($documents as $row)
                            @php $document = $row['document']; @endphp
                            <div class="rounded-md border flex items-center justify-between px-3 py-2 text-xs" style="border-color: var(--border);">
                                <span>{{ $document->original_name }}</span>
                                <span class="flex items-center gap-2">
                                    @if($row['has_highlights'])
                                        <span class="ds-badge ds-badge-success" title="This document has saved highlight marks — visible to anyone who opens it next.">Highlighted</span>
                                    @endif
                                    @if($row['inline_viewable'])
                                        <button type="button" style="color: var(--ds-blue, #2563eb); font-weight: 600;"
                                                @click="$dispatch('open-highlighter', {
                                                    dataUrl: '{{ route('corex.rental-applications.documents.highlight-data', [$rentalApplication, $document]) }}',
                                                    postUrl: '{{ route('corex.rental-applications.documents.highlight', [$rentalApplication, $document]) }}',
                                                    documentId: {{ $document->id }},
                                                    label: {{ Js::from($document->original_name) }},
                                                })">View &amp; Highlight</button>
                                    @else
                                        <span class="ds-badge ds-badge-default" title="This file type cannot be previewed on screen — download it to view it.">No preview</span>
                                    @endif
                                    <a href="{{ route('corex.rental-applications.documents.download', [$rentalApplication, $document]) }}" style="color: var(--text-muted);">Download</a>
                                </span>
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

{{-- Highlight modal, 2026-09-07 — CoreX already owns a proven "render pages
     server-side, draw marks, persist, play back to the next viewer"
     mechanism: the document-redaction tool
     (resources/views/command-center/viewing-packs/show.blade.php's
     redactionTool(), backed by ViewingPackRedactionService). This is that
     SAME mechanism — same rasterization technique, same Pointer-Events
     drag-to-draw interaction, same undo/redo, same apply/persist/playback
     shape — adapted for a translucent, agent-selectable colour instead of
     opaque destructive black (a highlight must stay non-destructive; see
     .ai/specs/rental-applications.md for why redaction's exact behaviour
     doesn't transfer 1:1). Default tool is the only tool (a rectangle mark,
     matching the proven interaction) with a small colour picker defaulting
     to bright yellow — Johan: "set the default to highlighter with a
     bright yellow colour as default. agents can reselect if they want
     something else." --}}
<div x-data="highlightTool('{{ csrf_token() }}')"
     x-on:open-highlighter.window="openHighlighter($event.detail)"
     x-on:keydown.window="onHighlightKey($event)"
     x-show="isOpen" x-cloak
     class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto"
     style="background: rgba(0,0,0,0.6); padding: 24px;">
    <div class="w-full max-w-4xl rounded-md" style="background: var(--surface); border: 1px solid var(--border);" @click.outside="close()">
        <div class="flex items-center justify-between px-5 py-3" style="border-bottom: 1px solid var(--border);">
            <div>
                <h3 class="text-base font-bold" style="color: var(--text-primary);">Highlight document</h3>
                <p class="text-xs" style="color: var(--text-muted);" x-text="label"></p>
            </div>
            <button type="button" @click="close()" class="corex-btn-outline">Close</button>
        </div>

        <div class="px-5 py-4">
            <div class="flex items-center justify-between mb-3 gap-3 flex-wrap">
                <p class="text-xs" style="color: var(--text-muted);">
                    Drag on the document to draw a highlight. Marks are saved for this document —
                    anyone who opens it next sees the same highlights.
                </p>
                <div class="flex items-center gap-3 flex-shrink-0" x-show="!loading && !loadError" x-cloak>
                    {{-- Colour picker — yellow default, agent can reselect. --}}
                    <div class="flex items-center gap-1">
                        <template x-for="c in colors" :key="c.key">
                            <button type="button" :title="c.label" @click="activeColor = c.key"
                                    :style="{ width:'18px', height:'18px', borderRadius:'9999px', background: c.css, cursor:'pointer', border: activeColor === c.key ? '2px solid var(--text-primary,#111)' : '1px solid rgba(0,0,0,0.2)' }"></button>
                        </template>
                    </div>
                    <div class="flex items-center gap-1">
                        <button type="button" class="text-xs px-2 py-1 rounded-md" title="Undo (Ctrl+Z)"
                                @click="undo()" :disabled="!canUndo()"
                                :style="{ border:'1px solid var(--border)', color: canUndo() ? 'var(--text-secondary)' : 'var(--text-muted)', opacity: canUndo() ? '1' : '0.5', cursor: canUndo() ? 'pointer' : 'default' }">Undo</button>
                        <button type="button" class="text-xs px-2 py-1 rounded-md" title="Redo (Ctrl+Shift+Z)"
                                @click="redo()" :disabled="!canRedo()"
                                :style="{ border:'1px solid var(--border)', color: canRedo() ? 'var(--text-secondary)' : 'var(--text-muted)', opacity: canRedo() ? '1' : '0.5', cursor: canRedo() ? 'pointer' : 'default' }">Redo</button>
                    </div>
                    <span class="text-xs font-semibold" style="color: var(--text-secondary);">
                        <span x-text="markCount()"></span> mark<span x-show="markCount() !== 1">s</span>
                    </span>
                </div>
            </div>

            <template x-if="loading">
                <p class="text-sm" style="color: var(--text-secondary);">Loading document…</p>
            </template>
            <template x-if="loadError">
                <p class="text-sm" style="color: var(--ds-crimson, #dc2626);" x-text="loadError"></p>
            </template>

            <div class="space-y-4" x-show="!loading && !loadError">
                <template x-for="page in pages" :key="page.index">
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs font-semibold" style="color: var(--text-muted);">Page <span x-text="page.index + 1"></span></span>
                            <button type="button" class="text-xs" style="color: var(--ds-crimson, #dc2626);" @click="clearPage(page.index)">Clear marks</button>
                        </div>
                        <div class="relative inline-block select-none" style="max-width:100%;">
                            <img :src="page.data_uri" class="rah-page-img block" :data-page="page.index"
                                 style="max-width:100%; height:auto; border:1px solid var(--border);"
                                 draggable="false" @dragstart.prevent>
                            <div class="absolute inset-0" style="cursor:crosshair; touch-action:none;"
                                 :data-page="page.index"
                                 @pointerdown.prevent="startDraw($event, page.index)"
                                 @pointermove.prevent="moveDraw($event, page.index)"
                                 @pointerup.prevent="endDraw($event, page.index)"
                                 @pointercancel.prevent="endDraw($event, page.index)"
                                 @dragstart.prevent>
                                <template x-for="(mark, mi) in marksFor(page.index)" :key="mi">
                                    <div :style="{ position:'absolute', left:mark.x+'px', top:mark.y+'px', width:mark.w+'px', height:mark.h+'px', background: colorCss(mark.color), opacity:'0.4', outline:'1px solid ' + colorCss(mark.color), pointerEvents:'none' }">
                                        <button type="button" title="Remove this mark"
                                                @pointerdown.stop.prevent="removeMark(page.index, mi)"
                                                :style="{ position:'absolute', top:'-9px', right:'-9px', width:'18px', height:'18px', borderRadius:'9999px', background:'#475569', color:'#fff', fontSize:'12px', lineHeight:'16px', textAlign:'center', border:'1px solid #fff', padding:'0', pointerEvents:'auto', cursor:'pointer' }">&times;</button>
                                    </div>
                                </template>
                                <div x-show="drag.active && drag.page === page.index"
                                     :style="{ position:'absolute', left:drag.x+'px', top:drag.y+'px', width:drag.w+'px', height:drag.h+'px', background: colorCss(activeColor), opacity:'0.4', border:'2px dashed #fff', pointerEvents:'none' }"></div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <div class="flex items-center justify-between gap-3 px-5 py-3" style="border-top: 1px solid var(--border);">
            <p class="text-xs flex-1" style="color: var(--ds-crimson, #dc2626);" x-show="applyError" x-cloak x-text="applyError"></p>
            <p class="text-xs flex-1" style="color: var(--ds-emerald, #059669);" x-show="justSaved" x-cloak>&check; Highlights saved — visible to the next person who opens this document.</p>
            <button type="button" class="corex-btn-primary" x-show="!loading && !loadError"
                    :disabled="applying" x-text="applying ? 'Saving…' : 'Save highlights'" @click="applyHighlights()"></button>
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

// Adapted from viewing-packs/show.blade.php's redactionTool() — same
// rasterized-page-image render, same Pointer-Events drag-to-draw, same
// undo/redo, same scale-to-raster-on-submit — with a translucent
// agent-selectable colour instead of opaque black, and JSON persistence
// (not FormData) matching this file's other endpoint (saveAssessment).
function highlightTool(csrf) {
    return {
        isOpen: false,
        loading: false,
        loadError: '',
        applyError: '',
        applying: false,
        justSaved: false,
        label: '',
        dataUrl: '',
        postUrl: '',
        pages: [],
        marks: [],   // FLAT array: [{page, x, y, w, h, color} display px]
        colors: [
            { key: 'yellow', label: 'Yellow', css: '#ffeb3b' },
            { key: 'green',  label: 'Green',  css: '#4cd964' },
            { key: 'pink',   label: 'Pink',   css: '#ff69b4' },
            { key: 'blue',   label: 'Blue',   css: '#5ac8fa' },
        ],
        activeColor: 'yellow', // default tool = highlighter, bright yellow (Johan)
        drag: { active: false, page: null, startX: 0, startY: 0, x: 0, y: 0, w: 0, h: 0 },
        undoStack: [],
        redoStack: [],

        async openHighlighter(detail) {
            this.dataUrl = detail.dataUrl;
            this.postUrl = detail.postUrl;
            this.label = detail.label || '';
            this.pages = [];
            this.marks = [];
            this.undoStack = [];
            this.redoStack = [];
            this.loadError = '';
            this.applyError = '';
            this.justSaved = false;
            this.activeColor = 'yellow';
            this.isOpen = true;
            this.loading = true;
            try {
                const res = await fetch(this.dataUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
                if (!res.ok) {
                    let msg = '';
                    try { msg = (await res.json()).error || ''; } catch (_) {}
                    this.loadError = msg || ('This document could not be opened for highlighting (HTTP ' + res.status + ').');
                    this.loading = false;
                    return;
                }
                const data = await res.json();
                this.pages = data.pages || [];
                if (!this.pages.length) { this.loadError = 'The document opened but produced no pages to highlight.'; }
                // Existing saved marks come back in RASTER px (page.width space) — convert
                // to DISPLAY px once the images have laid out so drag/remove coords match.
                const savedByPage = data.marks || {};
                this.$nextTick(() => {
                    for (const pageIndexStr of Object.keys(savedByPage)) {
                        const pageIndex = parseInt(pageIndexStr, 10);
                        const page = this.pages.find(p => p.index === pageIndex);
                        const img = document.querySelector('img.rah-page-img[data-page="' + pageIndex + '"]');
                        if (!page || !img || !img.clientWidth) continue;
                        const scaleX = img.clientWidth / page.width;
                        const scaleY = img.clientHeight / page.height;
                        (savedByPage[pageIndexStr] || []).forEach(m => {
                            this.marks.push({
                                page: pageIndex,
                                x: m.x * scaleX, y: m.y * scaleY, w: m.w * scaleX, h: m.h * scaleY,
                                color: m.color || 'yellow',
                            });
                        });
                    }
                });
            } catch (e) {
                this.loadError = 'This document could not be opened for highlighting: ' + (e && e.message ? e.message : 'network error') + '.';
            }
            this.loading = false;
        },
        close() { this.isOpen = false; },
        colorCss(key) {
            const c = this.colors.find(c => c.key === key);
            return c ? c.css : this.colors[0].css;
        },
        marksFor(p) { return this.marks.filter(m => m.page === p); },
        clearPage(p) { this.pushHistory(); this.marks = this.marks.filter(m => m.page !== p); },
        markCount() { return this.marks.length; },

        _snapshot() { return JSON.parse(JSON.stringify(this.marks)); },
        pushHistory() {
            this.undoStack.push(this._snapshot());
            if (this.undoStack.length > 100) this.undoStack.shift();
            this.redoStack = [];
        },
        canUndo() { return this.undoStack.length > 0; },
        canRedo() { return this.redoStack.length > 0; },
        undo() {
            if (!this.undoStack.length) return;
            this.redoStack.push(this._snapshot());
            this.marks = this.undoStack.pop();
        },
        redo() {
            if (!this.redoStack.length) return;
            this.undoStack.push(this._snapshot());
            this.marks = this.redoStack.pop();
        },
        removeMark(page, mi) {
            const target = this.marksFor(page)[mi];
            if (!target) return;
            const idx = this.marks.indexOf(target);
            if (idx === -1) return;
            this.pushHistory();
            this.marks.splice(idx, 1);
        },
        onHighlightKey(e) {
            if (!this.isOpen) return;
            const mod = e.ctrlKey || e.metaKey;
            if (!mod) return;
            const k = (e.key || '').toLowerCase();
            if (k === 'z' && !e.shiftKey) { e.preventDefault(); this.undo(); }
            else if ((k === 'z' && e.shiftKey) || k === 'y') { e.preventDefault(); this.redo(); }
        },

        startDraw(e, page) {
            try { e.currentTarget.setPointerCapture(e.pointerId); } catch (_) {}
            const r = e.currentTarget.getBoundingClientRect();
            const x = e.clientX - r.left, y = e.clientY - r.top;
            this.drag = { active: true, page, startX: x, startY: y, x, y, w: 0, h: 0 };
        },
        moveDraw(e, page) {
            if (!this.drag.active || this.drag.page !== page) return;
            const r = e.currentTarget.getBoundingClientRect();
            const cx = e.clientX - r.left, cy = e.clientY - r.top;
            this.drag.x = Math.min(cx, this.drag.startX);
            this.drag.y = Math.min(cy, this.drag.startY);
            this.drag.w = Math.abs(cx - this.drag.startX);
            this.drag.h = Math.abs(cy - this.drag.startY);
        },
        endDraw(e, page) {
            if (!this.drag.active || this.drag.page !== page) return;
            try { e.currentTarget.releasePointerCapture(e.pointerId); } catch (_) {}
            if (this.drag.w > 3 && this.drag.h > 3) {
                this.pushHistory();
                this.marks.push({ page, x: this.drag.x, y: this.drag.y, w: this.drag.w, h: this.drag.h, color: this.activeColor });
            }
            this.drag = { active: false, page: null, startX: 0, startY: 0, x: 0, y: 0, w: 0, h: 0 };
        },

        async applyHighlights() {
            if (this.applying) return;
            this.applyError = '';
            this.justSaved = false;
            this.applying = true;
            try {
                const marksByPage = {};
                for (const page of this.pages) {
                    const marks = this.marksFor(page.index);
                    if (!marks.length) continue;
                    const img = document.querySelector('img.rah-page-img[data-page="' + page.index + '"]');
                    if (!img || !img.clientWidth) continue;
                    const scaleX = page.width / img.clientWidth;
                    const scaleY = page.height / img.clientHeight;
                    marksByPage[page.index] = marks.map(m => ({
                        x: Math.round(m.x * scaleX), y: Math.round(m.y * scaleY),
                        w: Math.round(m.w * scaleX), h: Math.round(m.h * scaleY),
                        color: m.color,
                    }));
                }

                const res = await fetch(this.postUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ marks: marksByPage }),
                });
                if (!res.ok) {
                    let msg = '';
                    try { msg = (await res.json()).error || ''; } catch (_) {}
                    this.applyError = msg || ('Saving highlights failed (HTTP ' + res.status + ').');
                    this.applying = false;
                    return;
                }
                this.applying = false;
                // Visible proof it saved (Johan: "a silent save is indistinguishable from
                // no save"), THEN reload so the "Highlighted" badge reflects real state.
                this.justSaved = true;
                setTimeout(() => window.location.reload(), 700);
            } catch (err) {
                this.applyError = 'Saving highlights failed: ' + (err && err.message ? err.message : 'network error') + '.';
                this.applying = false;
            }
        },
    };
}
</script>
@endsection
