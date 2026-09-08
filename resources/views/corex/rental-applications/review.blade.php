{{-- DESIGN SYSTEM COMPLIANCE: UI_DESIGN_SYSTEM.md v 2026-04-20 --}}
{{-- AT-392 Phase 2 — agent review split-screen. Johan's own words: "application
     gets returned, agent open application - sees application and supporting docs
     on left panel of screen... then have a place on the right panel to input
     things like - income, salary / etc etc... doing the calcs to the bottom to
     see if tenant qualifies." --}}
@extends('layouts.corex')

@php
    // Computed here, not inline inside the x-data string below — a
    // multi-line arrow-function/array literal nested inside a Blade
    // expression has already caused one real outage on this feature
    // (rental-applications/public/show.blade.php's @json() incident,
    // 2026-09-08) — never assume a closure inline in a Blade echo is safe
    // without proving the compiled output first.
    $initialIncomeItems = $assessment->incomeItems->map(fn ($i) => [
        'id' => $i->id, 'description' => $i->description, 'amount' => $i->amount,
    ])->values();
    $initialExpenseItems = $assessment->expenseItems->map(fn ($i) => [
        'id' => $i->id, 'description' => $i->description, 'amount' => $i->amount,
    ])->values();
@endphp

@section('corex-content')
<div class="w-full"
     x-data="rentalReview({
         saveUrl: '{{ route('corex.rental-applications.review.assessment', $rentalApplication) }}',
         initial: {
             notes: {{ Js::from($assessment->notes) }},
             statement_months: {{ Js::from($assessment->statement_months) }},
         },
         initialIncomeItems: {{ Js::from($initialIncomeItems) }},
         initialExpenseItems: {{ Js::from($initialExpenseItems) }},
         initialResult: {{ Js::from($result) }},
         initialSavedAt: {{ $assessment->exists ? Js::from($assessment->updated_at->toIso8601String()) : 'null' }},
         initialMarkedUpDocIds: {{ Js::from($documents->filter(fn ($row) => $row['has_highlights'])->pluck('document.id')->values()) }},
         requestMoreInfoUrl: '{{ route('corex.rental-applications.review.request-more-info', $rentalApplication) }}',
         submitForApprovalUrl: '{{ route('corex.rental-applications.review.submit-for-approval', $rentalApplication) }}',
     })">

    {{-- Sticky header, 2026-09-08 — second time today the same fault: controls
         stranded off-screen while the user scrolls a long surface. Same fix as
         the rental application form (resources/views/corex/rental-applications/
         show.blade.php) — the shared x-sticky-action-bar component, not a new
         invention. Its right slot swaps between "Back to application" (normal)
         and the highlighter's own toolbar + Save button (while a document is
         open for marking) — Johan: "place them in a header so the agent can
         scroll and see the highlighter as well as the save buttons at all
         times." --}}
    <x-sticky-action-bar>
        <x-slot name="left">
            <div class="min-w-0">
                <h1 class="text-sm font-bold leading-tight truncate" style="color: var(--text-primary);">
                    Application Review — {{ $rentalApplication->contact->full_name ?? $rentalApplication->contact->first_name . ' ' . $rentalApplication->contact->last_name }}
                </h1>
                <p class="text-xs truncate" style="color: var(--text-muted);">
                    {{ $rentalApplication->property_address_override ?? optional($rentalApplication->property)->address ?? 'No property linked' }}
                </p>
            </div>
        </x-slot>
        <x-slot name="right">
            {{-- 2026-09-08 — Johan, three times now: "same fight with placement
                 of buttons - submit to auth should be on the header at the
                 top, not off screen at the bottom... every primary action on
                 this screen belongs in the header where it is always
                 visible." Moved from the aside's own "Next step" block —
                 same actions, same guard (hidden once a decision exists),
                 just reachable regardless of scroll position now. The note
                 textarea became a plain prompt() so the whole flow fits a
                 single header row without inventing a dropdown/popover for
                 one short line of text. --}}
            <template x-if="activeDocId === null">
                <div class="flex items-center gap-2 flex-wrap justify-end">
                    {{-- 2026-09-08 — Johan: "request more info only appears if
                         a document is not open... I have to write down
                         somewhere else because its only available once the
                         doc is closed." The header prompt() was still a
                         version of that same mistake — reachable only when
                         no document was open, and even once open it was a
                         one-line interruption, not somewhere he could
                         actually compose a real request while reading. Moved
                         to the aside (see below) — always visible, a real
                         textarea, never covered by a document. "Submit to
                         authoriser" stays here: a single decisive click once
                         he's ready, not something composed while reading. --}}
                    @unless(in_array($rentalApplication->status, ['approved', 'declined'], true))
                        <button type="button" class="corex-btn-primary text-xs" :disabled="submittingForApproval"
                                @click="submitForApproval()" x-text="submittingForApproval ? 'Submitting…' : ({{ $isPendingAuthorisation ? 'true' : 'false' }} ? 'Re-submit to authoriser' : 'Submit to authoriser')"></button>
                    @endunless
                    <a href="{{ route('corex.rental-applications.show', $rentalApplication) }}" class="corex-btn-outline text-xs">Back to application</a>
                </div>
            </template>
            <template x-if="activeDocId !== null">
                <div class="flex items-center gap-3 flex-wrap justify-end">
                    <span class="text-xs font-medium truncate max-w-[160px]" style="color: var(--text-secondary);" x-text="label"></span>
                    {{-- Tool picker — Highlight (default) or Note. --}}
                    <div class="flex items-center gap-1">
                        <button type="button" class="text-xs px-2 py-1 rounded-md" @click="activeTool = 'highlight'"
                                :style="{ border:'1px solid var(--border)', background: activeTool === 'highlight' ? 'var(--ds-blue-soft, #eff6ff)' : 'transparent', fontWeight: activeTool === 'highlight' ? '700' : '400' }">Highlight</button>
                        <button type="button" class="text-xs px-2 py-1 rounded-md" @click="activeTool = 'note'"
                                :style="{ border:'1px solid var(--border)', background: activeTool === 'note' ? 'var(--ds-blue-soft, #eff6ff)' : 'transparent', fontWeight: activeTool === 'note' ? '700' : '400' }">Note</button>
                    </div>
                    {{-- Colour picker — yellow default, agent can reselect. Applies to both tools. --}}
                    <div class="flex items-center gap-1" x-show="!loading && !loadError">
                        <template x-for="c in colors" :key="c.key">
                            <button type="button" :title="c.label" @click="activeColor = c.key"
                                    :style="{ width:'16px', height:'16px', borderRadius:'9999px', background: c.css, cursor:'pointer', border: activeColor === c.key ? '2px solid var(--text-primary,#111)' : '1px solid rgba(0,0,0,0.2)' }"></button>
                        </template>
                    </div>
                    {{-- Highlighter size, 2026-09-08 — Johan: "we need a way
                         to adjust the highlighter smaller or larger. current
                         on highlights too much lines on bank statement -
                         lines are small there." Three presets, not a slider
                         — "medium" is today's unchanged default. Remembered
                         in localStorage (set in init()) so it survives both
                         switching documents in one sitting and coming back
                         tomorrow, not just this page view. Only meaningful
                         for the highlight tool — a note's marker size never
                         varies. --}}
                    <div class="flex items-center gap-1" x-show="!loading && !loadError && activeTool === 'highlight'">
                        <template x-for="s in strokeSizes" :key="s.key">
                            <button type="button" class="text-xs px-2 py-1 rounded-md" :title="s.label" @click="setStrokeSize(s.key)"
                                    :style="{ border:'1px solid var(--border)', background: strokeSizeKey === s.key ? 'var(--ds-blue-soft, #eff6ff)' : 'transparent', fontWeight: strokeSizeKey === s.key ? '700' : '400' }" x-text="s.label"></button>
                        </template>
                    </div>
                    <div class="flex items-center gap-1" x-show="!loading && !loadError">
                        <button type="button" class="text-xs px-2 py-1 rounded-md" title="Undo (Ctrl+Z)"
                                @click="undo()" :disabled="!canUndo()"
                                :style="{ border:'1px solid var(--border)', color: canUndo() ? 'var(--text-secondary)' : 'var(--text-muted)', opacity: canUndo() ? '1' : '0.5', cursor: canUndo() ? 'pointer' : 'default' }">Undo</button>
                        <button type="button" class="text-xs px-2 py-1 rounded-md" title="Redo (Ctrl+Shift+Z)"
                                @click="redo()" :disabled="!canRedo()"
                                :style="{ border:'1px solid var(--border)', color: canRedo() ? 'var(--text-secondary)' : 'var(--text-muted)', opacity: canRedo() ? '1' : '0.5', cursor: canRedo() ? 'pointer' : 'default' }">Redo</button>
                    </div>
                    <span class="text-xs font-semibold hidden sm:inline" style="color: var(--text-secondary);" x-show="!loading">
                        <span x-text="markCount()"></span> mark<span x-show="markCount() !== 1">s</span>
                    </span>
                    <button type="button" class="corex-btn-primary text-xs" x-show="!loading && !loadError"
                            :disabled="applying || pagesLoading" :title="pagesLoading ? 'Still loading the rest of this document' : ''"
                            x-text="applying ? 'Saving…' : (pagesLoading ? 'Loading…' : 'Save')" @click="applyHighlights()"></button>
                    <button type="button" class="corex-btn-outline text-xs" @click="closeHighlighter()">Done</button>
                </div>
            </template>
        </x-slot>
    </x-sticky-action-bar>

    {{-- Save confirmation, 2026-09-08 — Johan: "if I edit / highlight anything on
         the pdf will it automatically save?" It did not autosave, and answering
         that ambiguity is the fix: highlighting/notes are EXPLICIT-save only,
         same as the viewing-pack redaction tool (command-center/viewing-packs/
         show.blade.php's redactionTool() has no autosave either — "Apply
         redaction" is the only way a box is ever persisted). This toast is
         OUTSIDE the "document open" template on purpose — the old badge lived
         inside x-if="activeDocId !== null" and got hidden the same instant
         Done closed the panel, so a save-then-close never actually showed
         anything. Page-level, so it survives the panel closing. --}}
    <div x-show="justSaved" x-cloak x-transition
         class="fixed z-40 rounded-md px-3 py-2 text-xs font-medium flex items-center gap-1.5"
         style="top: 72px; right: 20px; background: var(--ds-emerald, #059669); color: #fff; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
        &check; Marks saved — the next person to open this document sees them.
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
         below 1280px, same breakpoint family as the signature review screen.

         IN-PLACE ANNOTATION, 2026-09-08 — Johan: "you lose the right hand panel
         to capture income etc. until you have finished the highlighting... think
         thats a problem as you have both but on separate screens." He was right
         — a full-screen modal covered review-aside entirely, defeating the whole
         point of a split screen. Fixed by removing the modal: the highlighter now
         renders INLINE inside review-main's own document row (still its own
         independently-scrolling column — see below), so review-aside is a plain
         flex SIBLING that was never covered and stays visible and usable the
         entire time an agent is highlighting a payslip. This was a pure
         presentational change — the mark-persistence backend never knew or cared
         whether its UI was a modal or inline; only the wrapping markup moved. --}}
    {{-- Independent scrolling, 2026-09-07/08 — Johan: "the right and left
         panels should scroll independently... essentially everything should
         fit that the whole screen doesnt scroll." The original fixed
         "100vh - 88px" guess didn't account for the QA/env banner
         (partials._demo-watermark / _env-banner, real height on QA1 — visible
         in a screenshot as the "QA · 127.0.0.1" bar) or the sticky header's
         real margins, so the panels were taller than the space actually left
         inside #appScroll (layouts/corex.blade.php's own scroll container) —
         #appScroll then had to scroll too, exactly the double-scroll Johan
         flagged. A bigger hardcoded guess would only be right for one
         viewport/banner combination and wrong for the next. Fixed by
         MEASURING the real available space at runtime (rentalReviewLayout()
         below) instead of guessing it — stable across banner/viewport
         changes because it reads the actual rendered header height and
         #appScroll's actual box, not an assumption about them. --}}
    <style>
        .rental-review-columns { display: flex; flex-direction: column; gap: 20px; }
        .rental-review-main    { flex: 1 1 auto; min-width: 0; }
        .rental-review-aside   { width: 100%; }
        @media (min-width: 1280px) {
            .rental-review-columns { flex-direction: row; gap: 16px; align-items: stretch; }
            .rental-review-main    { height: var(--rr-panel-h, calc(100vh - 160px)); max-height: var(--rr-panel-h, calc(100vh - 160px)); overflow-y: auto; }
            .rental-review-aside   { flex: 0 0 260px; width: 260px; align-self: stretch; position: sticky; top: 72px; height: var(--rr-panel-h, calc(100vh - 160px)); max-height: var(--rr-panel-h, calc(100vh - 160px)); overflow-y: auto; overflow-x: hidden; }
        }
    </style>

    <div class="rental-review-columns mt-5" x-data="rentalReviewLayout()">

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
                    <div class="space-y-2">
                        @foreach($documents as $row)
                            @php $document = $row['document']; @endphp
                            <div class="rounded-md border" style="border-color: var(--border);">
                                <div class="flex items-center justify-between px-3 py-2 text-xs">
                                    <span>{{ $document->original_name }}
                                        {{-- Agent-added-documents, 2026-09-08 — cc4's backend
                                             (RentalApplicationController::uploadDocument()), markup
                                             handed to me since I own this file. --}}
                                        <span style="color: var(--text-muted); font-size: 11px;">— {{ $document->uploaded_by ? 'added by ' . ($document->uploader->name ?? 'an agent') : 'from applicant' }}</span>
                                        {{-- RA-03 (cc5 re-test, Round 8) — this badge existed on
                                             show.blade.php but was never handed over here, the
                                             screen an agent actually reviews a returned
                                             application on. Same condition, same wording. --}}
                                        @if($rentalApplication->submitted_at && $document->created_at->greaterThanOrEqualTo($rentalApplication->submitted_at))
                                            <span class="ds-badge ds-badge-warning" title="This document was added after the application was submitted">Added after submission</span>
                                            <span style="color: var(--text-muted); font-size: 11px;">{{ $document->created_at->format('d M Y H:i') }}</span>
                                        @endif
                                    </span>
                                    <span class="flex items-center gap-2">
                                        <span class="ds-badge ds-badge-success" x-show="markedUpDocIds.includes({{ $document->id }})" x-cloak title="This document has saved marks — visible to anyone who opens it next.">Marked up</span>
                                        @if($row['inline_viewable'])
                                            <button type="button" style="color: var(--ds-blue, #2563eb); font-weight: 600;"
                                                    @click="openHighlighter({
                                                        documentId: {{ $document->id }},
                                                        firstPageUrl: '{{ route('corex.rental-applications.documents.highlight-data.first', [$rentalApplication, $document]) }}',
                                                        remainingPagesUrl: '{{ route('corex.rental-applications.documents.highlight-data.remaining', [$rentalApplication, $document]) }}',
                                                        postUrl: '{{ route('corex.rental-applications.documents.highlight', [$rentalApplication, $document]) }}',
                                                        label: {{ Js::from($document->original_name) }},
                                                    })"
                                                    x-text="activeDocId === {{ $document->id }} ? 'Close' : 'View & Mark Up'"></button>
                                        @else
                                            <span class="ds-badge ds-badge-default" title="This file type cannot be previewed on screen — download it to view it.">No preview</span>
                                        @endif
                                        <a href="{{ route('corex.rental-applications.documents.download', [$rentalApplication, $document]) }}" style="color: var(--text-muted);">Download</a>
                                    </span>
                                </div>

                                {{-- IN-PLACE viewer — was a fixed full-screen modal that covered
                                     review-aside entirely; now a plain in-flow block inside this
                                     row, inside review-main's own independently-scrolling column.
                                     review-aside (the assessment panel) is a flex sibling that was
                                     never covered by either shape — this change is presentational
                                     only. --}}
                                <div x-show="activeDocId === {{ $document->id }}" x-cloak class="px-3 pb-3 border-t" style="border-color: var(--border);">
                                    <template x-if="loading">
                                        <p class="text-sm py-4" style="color: var(--text-secondary);">Loading document…</p>
                                    </template>
                                    <template x-if="loadError">
                                        <p class="text-sm py-4" style="color: var(--ds-crimson, #dc2626);" x-text="loadError"></p>
                                    </template>
                                    <p class="text-xs py-2" style="color: var(--text-muted);" x-show="!loading && !loadError">
                                        <span x-show="activeTool === 'highlight'">Click and drag across the document, like a marker pen, to highlight.</span>
                                        <span x-show="activeTool === 'note'">Click anywhere on the document to pin a note.</span>
                                        Marks are saved for this document — anyone who opens it next sees the same marks.
                                    </p>

                                    {{-- Progressive load, 2026-09-08 — Johan: "the agent must be able to
                                         SEE that more pages are still coming, and roughly how many. A
                                         page 1 that looks like the whole document is worse than a slow
                                         load." Sharpness kept at full quality per his decision — this is
                                         a one-time cost per document, made LESS painful by showing page 1
                                         immediately, not made invisible. --}}
                                    <div class="flex items-center gap-2 text-xs py-2 px-3 rounded-md mb-2" x-show="pagesLoading" x-cloak
                                         style="background: var(--ds-blue-soft, #eff6ff); color: var(--ds-blue, #2563eb);">
                                        <span>Page 1 of <span x-text="totalPages"></span> shown — loading the remaining <span x-text="totalPages - pages.length"></span> pages. You can start marking up page 1 now.</span>
                                    </div>

                                    <div class="space-y-4 pt-2" x-show="!loading && !loadError">
                                        <template x-for="page in pages" :key="page.index">
                                            <div>
                                                <div class="flex items-center justify-between mb-1">
                                                    <span class="text-xs font-semibold" style="color: var(--text-muted);">Page <span x-text="page.index + 1"></span></span>
                                                    <button type="button" class="text-xs" style="color: var(--ds-crimson, #dc2626);" @click="clearPage(page.index)">Clear marks on this page</button>
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
                                                        {{-- Highlight strokes — connected line segments following the
                                                             actual drag path, an SVG polyline with a thick translucent
                                                             stroke (a real marker-pen gesture, not a rectangle).
                                                             2026-09-08 — Johan: "highlighter dont work - just shows a
                                                             little black x but no colour applied." Root cause found by
                                                             actually loading the screen (real browser console, not a
                                                             markup check): a browser parses <template x-for>/<template
                                                             x-if> INSIDE an <svg> as foreign SVG content, so it never
                                                             gets the special "content is a cloneable fragment"
                                                             treatment real HTML <template> gets elsewhere on this same
                                                             page — Alpine's clone step threw a real, reproducible
                                                             "Failed to execute 'importNode'" error on every draw,
                                                             silently leaving every polyline's points/stroke/width
                                                             blank. The little black x (the remove-mark button below)
                                                             is a plain HTML button OUTSIDE the svg, so it rendered
                                                             fine and was the only visible sign anything existed. Fixed
                                                             by building the polyline markup as a STRING and binding it
                                                             with x-html directly on the <svg> — no <template> inside
                                                             SVG at all, still fully reactive since x-html re-evaluates
                                                             on every dependency change same as x-text/x-show. --}}
                                                        <svg class="absolute inset-0" style="pointer-events:none; width:100%; height:100%;"
                                                             x-html="strokesSvgFor(page.index)"></svg>
                                                        {{-- Remove-stroke handles (one per stroke, at its first point). --}}
                                                        <template x-for="(mark, mi) in strokesFor(page.index)" :key="'r'+mi">
                                                            <button type="button" title="Remove this mark"
                                                                    @pointerdown.stop.prevent="removeMark(page.index, mi, 'highlight')"
                                                                    :style="{ position:'absolute', left:(mark.points[0].x-9)+'px', top:(mark.points[0].y-9)+'px', width:'18px', height:'18px', borderRadius:'9999px', background:'#475569', color:'#fff', fontSize:'12px', lineHeight:'16px', textAlign:'center', border:'1px solid #fff', padding:'0', pointerEvents:'auto', cursor:'pointer' }">&times;</button>
                                                        </template>
                                                        {{-- Notes — a pinned marker + its text, visible inline.
                                                             2026-09-08 — Johan: "note does not work - clicked, shows
                                                             small modal but cannot type anything in it." Root cause,
                                                             found the same way as the highlighter bug above (a real
                                                             browser, not a markup check): the draw-surface div this
                                                             sits inside has @pointerdown.prevent="startDraw(...)" with
                                                             no .stop, so a pointerdown that starts on the textarea
                                                             (clicking into it to type) bubbles up and gets
                                                             preventDefault()'d there — which cancels the browser's own
                                                             default focus behaviour for that pointerdown. The textarea
                                                             was never actually receiving focus, so every keystroke
                                                             went nowhere; @click.stop on the textarea couldn't help
                                                             because click fires AFTER pointerdown, once the damage was
                                                             already done. Fixed by stopping the pointerdown itself
                                                             from ever reaching the draw surface, on every interactive
                                                             element here (not just the textarea — the marker dot and
                                                             its popover buttons had the same latent exposure). --}}
                                                        <template x-for="(note, ni) in notesFor(page.index)" :key="'n'+ni">
                                                            <div @pointerdown.stop :style="{ position:'absolute', left:note.x+'px', top:note.y+'px', transform:'translate(-50%,-50%)', pointerEvents:'auto' }">
                                                                <div class="rounded-full" :style="{ width:'16px', height:'16px', background: colorCss(note.color), border:'2px solid #fff', boxShadow:'0 0 0 1px rgba(0,0,0,0.3)', cursor:'pointer' }"
                                                                     @click="toggleNotePopover(page.index, ni)"></div>
                                                                <div x-show="openNote && openNote.page === page.index && openNote.index === ni" x-cloak
                                                                     class="rounded-md p-2 text-xs" style="position:absolute; top:20px; left:0; width:220px; background: var(--surface); border:1px solid var(--border); box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 10;">
                                                                    <p class="mb-2" style="white-space:pre-wrap; color: var(--text-primary);" x-text="note.text"></p>
                                                                    <div class="flex justify-end gap-2">
                                                                        <button type="button" style="color: var(--ds-crimson, #dc2626);" @click="removeMark(page.index, ni, 'note')">Remove</button>
                                                                        <button type="button" style="color: var(--text-muted);" @click="openNote = null">Close</button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </template>
                                                        {{-- Pending note being typed (note tool, awaiting text). --}}
                                                        <div x-show="pendingNote && pendingNote.page === page.index" x-cloak @pointerdown.stop
                                                             :style="{ position:'absolute', left:(pendingNote ? pendingNote.x : 0)+'px', top:(pendingNote ? pendingNote.y : 0)+'px', transform:'translate(-50%,-50%)', pointerEvents:'auto' }">
                                                            <div class="rounded-md p-2" style="width:220px; background: var(--surface); border:1px solid var(--border); box-shadow: 0 4px 12px rgba(0,0,0,0.2);">
                                                                <textarea x-model="pendingNoteText" rows="3" class="corex-input text-xs w-full" placeholder="Note text…" @click.stop :data-pending-note-page="page.index"></textarea>
                                                                <div class="flex justify-end gap-2 mt-1">
                                                                    <button type="button" class="text-xs" style="color: var(--text-muted);" @click.stop="pendingNote = null; pendingNoteText = ''">Cancel</button>
                                                                    <button type="button" class="text-xs font-semibold" style="color: var(--ds-blue, #2563eb);" @click.stop="commitNote()">Add note</button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Agent-added-documents, 2026-09-08 — Johan: "agent should
                     in any case be able to add docs as client can be in the
                     office so agent scans docs to themselves, or even
                     receive via whatsapp etc." Backend built by cc4
                     (RentalApplicationController::uploadDocument(), same
                     allowlist/scoping the applicant path already uses).
                     Widget itself handed to me since I own this file —
                     own nested x-data, zero shared state with the root
                     rentalReview() component. Reloads on success (server-
                     rendered $documents list, not x-for) — safe here since
                     the assessment panel autosaves on blur, so nothing is
                     ever sitting unsaved when an agent clicks away to pick
                     a file. --}}
                <div class="mt-3 pt-3" style="border-top: 1px solid var(--border);" x-data="agentDocumentUploadReview()">
                    <template x-for="u in uploading" :key="u.tempId">
                        <p class="text-xs mb-1" :class="u.error ? 'text-red-600' : ''" style="color: var(--text-muted);" x-text="u.error ? (u.name + ': ' + u.error) : ('Uploading ' + u.name + '…')"></p>
                    </template>
                    <label class="text-xs font-medium cursor-pointer" style="color: var(--brand-icon, #2563eb);">
                        + Add document
                        <input type="file" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" class="hidden" @change="onFilesSelected($event.target.files); $event.target.value = ''">
                    </label>
                </div>
            </div>
        </div>

        {{-- ASIDE — the agent's own affordability capture + suggestive calculation.
             Narrow, fixed 260px working column. Never covered by anything —
             see the in-place-annotation note above the layout <style> block. --}}
        <div class="rental-review-aside rounded-md p-4" style="background: var(--surface); border: 1px solid var(--border);">
            <div class="flex items-center justify-between gap-2 mb-1">
                <h2 class="text-sm font-semibold" style="color: var(--text-primary);">Affordability Assessment</h2>
                {{-- 2026-09-08 — Johan: "how do I save the work on the right hand
                     panel? does it auto save?" He'd already been told once in
                     body text below (easy to miss, and not visible until the
                     panel scrolls past it); this is now a standing, permanent
                     badge next to the title itself, visible from the moment the
                     screen loads — before he has typed anything, not only after
                     — the same visual weight as "Marked up" and the highlighter's
                     own "Saved" toast elsewhere on this screen. He should never
                     need to ask this again by looking at the screen. --}}
                <span class="ds-badge ds-badge-info flex-shrink-0" title="Every field below saves the moment you click away from it — no button needed.">Autosaves</span>
            </div>
            <p class="text-xs mb-4" style="color: var(--text-muted);">
                You type these — nothing here is pre-filled from the application, and nothing here is
                sent to the applicant or shown anywhere else.
            </p>

            <div class="space-y-4">
                {{-- Round 12 — Johan, plainly: "whatever the agent captured
                     get averaged by the months selected... so the avg
                     income is? 11000? THAT monthly average is the gross
                     monthly income the 30% affordability rule runs
                     against." This is now REQUIRED to get a result at
                     all — a lump sum over an unstated number of months
                     means nothing against a monthly legal threshold, so
                     the guideline check below reads "incomplete" until
                     this is filled in, never a wrong answer or a pass
                     based on the raw total. Placed above both lists since
                     it applies to the whole capture, not just income. --}}
                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">
                        Number of months this bank statement covers
                    </label>
                    <input type="number" step="1" min="1" max="36" class="corex-input text-sm w-20"
                           x-model="statementMonths" @blur="save()" placeholder="e.g. 3">
                    <p class="text-[11px] mt-1" style="color: var(--text-muted);">
                        Required to turn the totals below into the monthly figure the affordability
                        guideline actually runs against — the raw total alone is not enough.
                    </p>
                </div>
                {{-- Round 9 (item 5) — Johan: "filling the last row auto-adds
                     a fresh empty one, income and expenses both, total
                     recalculating live." Growable lists, not fixed fields —
                     see incomeItems/expenseItems in rentalReview() below.
                     "Gross" stated once at the section heading (Round 9 item
                     2/3) rather than per-row, since a row's own label is
                     free text the agent chooses ("Salary", "Side income"),
                     unlike the applicant/agent-detail-page forms' single
                     fixed field. --}}
                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">
                        Income (gross, before deductions)
                    </label>
                    <p class="text-[11px] mb-2" style="color: var(--text-muted);">
                        What's on the payslip before tax and other deductions — not take-home pay.
                    </p>
                    <div class="space-y-2" x-ref="incomeRows">
                        <template x-for="(item, index) in incomeItems" :key="index">
                            <div class="flex items-center gap-1.5">
                                <input type="text" class="corex-input text-sm flex-1" placeholder="e.g. Salary"
                                       x-model="item.description" @input="onIncomeRowInput(false)" @blur="save()">
                                <input type="text" inputmode="decimal" class="corex-input text-sm w-24" placeholder="0.00"
                                       data-role="amount" x-model="item.amount" @input="onIncomeRowInput(true)" @blur="save()">
                            </div>
                        </template>
                    </div>
                    <p class="text-xs mt-2" style="color: var(--text-secondary);">
                        Total captured (all lines): <strong x-text="formatR(incomeTotal())"></strong>
                    </p>
                    {{-- Round 12 — THIS is now the figure the guideline
                         check uses; formatR() sourced from result.gross_income
                         (the server's own, authoritative figure) once
                         saved, matching the live client-side division
                         exactly in the meantime so the two can never
                         meaningfully disagree. --}}
                    <p class="text-xs mt-1" x-show="statementMonths" style="color: var(--text-secondary);">
                        Monthly average (÷ <span x-text="statementMonths"></span> months —
                        <strong>used in the affordability check below</strong>):
                        <strong x-text="formatR(monthlyAverage(incomeTotal()))"></strong>
                    </p>
                    <p class="text-xs mt-1" x-show="!statementMonths" style="color: var(--ds-amber, #b45309);">
                        Enter the number of months above to calculate the monthly figure the
                        guideline check needs.
                    </p>
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Expenses / existing debt</label>
                    <div class="space-y-2" x-ref="expenseRows">
                        <template x-for="(item, index) in expenseItems" :key="index">
                            <div class="flex items-center gap-1.5">
                                <input type="text" class="corex-input text-sm flex-1" placeholder="e.g. Car payment"
                                       x-model="item.description" @input="onExpenseRowInput(false)" @blur="save()">
                                <input type="text" inputmode="decimal" class="corex-input text-sm w-24" placeholder="0.00"
                                       data-role="amount" x-model="item.amount" @input="onExpenseRowInput(true)" @blur="save()">
                            </div>
                        </template>
                    </div>
                    <p class="text-xs mt-2" style="color: var(--text-secondary);">
                        Total captured (all lines): <strong x-text="formatR(expenseTotal())"></strong>
                    </p>
                    <p class="text-xs mt-1" x-show="statementMonths" style="color: var(--text-secondary);">
                        Monthly average (÷ <span x-text="statementMonths"></span> months):
                        <strong x-text="formatR(monthlyAverage(expenseTotal()))"></strong>
                    </p>
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
                    <p class="text-sm" style="color: var(--text-muted);">Enter income above, fill in the number of months the statement covers, and make sure "Current rental amount" is filled in on the application itself, to see a suggestion here.</p>
                </template>
                {{-- 2026-09-08 — Johan: "right hand panel? is that filled in
                     from where?" This calculation's RENT figure was never
                     shown — an agent could see a suggestion without knowing
                     what rent it was measured against. Naming the source
                     (the applicant's own self-reported current rent, from
                     the application form) here, not just in a tooltip. --}}
                {{-- Round 9 — the rule is now stated as the law states it:
                     rent as a percentage of GROSS income, checked directly
                     against the agency's configured ceiling (default 30%,
                     the legal guideline) — not a rent multiplier. Same
                     wording style as authorisation/show.blade.php, so an
                     agent and an authoriser see the identical arithmetic. --}}
                <template x-if="result.label !== 'incomplete'">
                    <div class="text-sm space-y-1">
                        <p>Monthly gross income (bank statement total ÷ <span x-text="result.statement_months"></span> months):
                           <strong x-text="formatR(result.gross_income)"></strong></p>
                        {{-- Round 12 — Johan: "make sure the screen shows
                             both clearly so the agent can see the
                             difference, since that difference is exactly
                             what they are assessing" — the applicant's own
                             claimed figure next to the agent's derived,
                             bank-statement-verified one. Only the derived
                             figure above feeds the check. --}}
                        <p x-show="result.applicant_reported_income !== null">
                            Applicant's own stated income (from their application form):
                            <strong x-text="formatR(result.applicant_reported_income)"></strong>
                        </p>
                        <p>Rent (applicant's self-reported current rent, from the application): <strong x-text="formatR(result.rent)"></strong></p>
                        <p>Rent must not exceed <span x-text="result.max_rent_percent"></span>% of monthly gross income
                           (<strong x-text="formatR(result.max_affordable_rent)"></strong>).
                           Actual rent is <span x-text="result.rent_as_percent_of_gross"></span>% of monthly gross income.</p>
                        <p class="mt-2">
                            <span class="ds-badge" :class="result.meets_threshold ? 'ds-badge-success' : 'ds-badge-warning'"
                                  x-text="result.meets_threshold ? 'Within the affordability guideline — worth a closer look either way' : 'Exceeds the affordability guideline — worth a closer look'"></span>
                        </p>
                    </div>
                </template>
                {{-- Round 9 (item 1) — cc5: "an agent sees a net figure
                     sitting next to a pass or fail badge and reasonably
                     assumes net is what was tested. It is not." Deliberately
                     NOT inside the box above, with its own heading and
                     colour so it can never be read as part of the
                     guideline check — net income plays no part in
                     meets_threshold (see RentalApplicationAssessment::
                     qualifyingResult()'s own docblock). Kept, not removed
                     (Johan's call) — genuinely useful context for the agent,
                     what's left after the expenses typed in above. --}}
                <template x-if="result.label !== 'incomplete'">
                    <div class="rounded-md p-2 mt-3" style="background: var(--ds-slate-soft, #f1f5f9); border: 1px dashed var(--border);">
                        <p class="text-[10px] font-semibold uppercase tracking-wide" style="color: var(--text-muted);">
                            For your reference only — does not affect the guideline check above
                        </p>
                        <p class="text-sm mt-1">
                            Income left after expenses: <strong x-text="formatR(result.net_income)"></strong>
                        </p>
                    </div>
                </template>
            </div>

            {{-- Authoriser flow, 2026-09-08 — the agent's own two actions.
                 Johan: "agent only gets submit? get application, maybe a way
                 to request more info from applicant? then once everything
                 received checked etc they submit to auth." Reuses the
                 applicant's EXISTING token link (no new token/route) — the
                 applicant can already add documents at any status. --}}
            <div class="rounded-md p-3 mt-4" style="border: 1px solid var(--border);">
                {{-- Authoriser decision, 2026-09-08 — "update the agent's
                     screen to show the applicant approved for that amount."
                     An override (a CO changing an earlier decision) simply
                     shows the CURRENT decision here — the full history,
                     including the override, is on the authoriser's own
                     screen and the audit trail. --}}
                @if($rentalApplication->status === 'approved')
                    <div class="rounded-md px-3 py-2 text-xs mb-3" style="background: var(--ds-emerald-soft, #ecfdf5); color: var(--ds-emerald, #059669);">
                        &check; Approved for R{{ number_format($rentalApplication->approved_rental_amount, 2) }} a month. The applicant has been notified — you can now start matching them to a property.
                    </div>
                @elseif($rentalApplication->status === 'declined')
                    <div class="rounded-md px-3 py-2 text-xs mb-3" style="background: var(--ds-red-soft, #fef2f2); color: var(--ds-red, #dc2626);">
                        Declined. The applicant has been notified.
                    </div>
                @elseif($isPendingAuthorisation)
                    <div class="rounded-md px-3 py-2 text-xs mb-3" style="background: var(--ds-blue-soft, #eff6ff); color: var(--ds-blue, #2563eb);">
                        Submitted for approval {{ $rentalApplication->submitted_for_approval_at->format('d M Y H:i') }} — awaiting the authoriser's decision.
                    </div>
                @endif

                {{-- Request more information, 2026-09-08 — Johan, verbatim:
                     "request more info only appears if a document is not
                     open... I have to write down somewhere else because its
                     only available once the doc is closed?" and "the simple
                     modal that loads on request extra info will not
                     suffice. Allow a free text box - I can go and fill in
                     numbered points." Both are the same underlying design
                     mistake — the request lived somewhere that wasn't
                     reachable, or wasn't sized for real writing, WHILE he
                     was looking at the evidence he was writing about. Fixed
                     by putting it here instead: the aside is never covered
                     by an open document (same reason the highlighter went
                     inline instead of a modal originally), so this is
                     reachable and fillable the entire time a document is
                     open. The draft surviving a document being opened or
                     closed needs no extra code — `moreInfoNote` (bound
                     below) was never part of openHighlighter()/
                     closeHighlighter()'s reset list, so it was already
                     independent of the document lifecycle; the fix here is
                     purely that this UI now actually stays on screen to be
                     typed into. rows="6" + a monospace-ish plain textarea
                     preserves line breaks/numbering as typed — no
                     reformatting on the way in, and the mail template
                     already renders with white-space:pre-wrap so numbered
                     points survive to the applicant unchanged (verified —
                     see spec). --}}
                @unless(in_array($rentalApplication->status, ['approved', 'declined'], true))
                    <p class="text-xs font-semibold uppercase tracking-wide mb-2" style="color: var(--text-muted);">Request more information</p>
                    <textarea x-model="moreInfoNote" rows="6" class="corex-input text-xs w-full mb-2" placeholder="What do you need from the applicant? e.g.&#10;1. Three months' bank statements&#10;2. Payslip for August&#10;3. Proof of the R12,000 deposit on 14 August"></textarea>
                    <button type="button" class="corex-btn-outline text-xs w-full" :disabled="moreInfoSending || !moreInfoNote.trim()" @click="requestMoreInfo()" x-text="moreInfoSending ? 'Sending…' : 'Send to applicant'"></button>
                    <p class="text-xs mt-2" x-show="agentActionStatus" x-text="agentActionStatus" :style="agentActionError ? 'color: var(--ds-red, #dc2626);' : 'color: var(--ds-emerald, #059669);'"></p>
                @endunless
            </div>
        </div>
    </div>
</div>

<script>
function rentalReview({ saveUrl, initial, initialIncomeItems, initialExpenseItems, initialResult, initialSavedAt, initialMarkedUpDocIds, requestMoreInfoUrl, submitForApprovalUrl }) {
    return {
        // 2026-09-08 — Johan: "clicking back to application shows a changes
        // may be lost popup but there's no save button visible anywhere." No
        // beforeunload guard existed at all, so that warning (wherever it
        // came from) was never paired with a real, reachable way to act on
        // it. This is the real pairing: the SAME native browser warning,
        // wired to the highlighter's own `dirty` flag, with the sticky
        // header's Save button (see (g) above) visible the entire time the
        // warning could possibly fire — never one without the other.
        init() {
            window.addEventListener('beforeunload', (e) => {
                if (this.dirty) { e.preventDefault(); e.returnValue = ''; }
            });
            this.compactAndEnsureTrailing(this.incomeItems);
            this.compactAndEnsureTrailing(this.expenseItems);
            // Highlighter size, 2026-09-08 — restore whatever size the
            // agent last used, on THIS device, across documents and across
            // sessions — not just this page view.
            try {
                const saved = localStorage.getItem('rahStrokeSizeKey');
                if (saved && this.strokeSizes.some(s => s.key === saved)) {
                    this.strokeSizeKey = saved;
                    this.strokeWidth = this.strokeSizes.find(s => s.key === saved).px;
                }
            } catch (_) {} // localStorage unavailable (private browsing etc.) — default size is fine
        },
        // ── Income/expense line items — Round 9 (item 5). Johan: "filling
        // the last row auto-adds a fresh empty one, income and expenses
        // both, total recalculating live." A row carries an `id` once the
        // server has persisted it (used to match it on the next autosave,
        // never re-created); a row typed fresh has no `id` yet. ──────────
        incomeItems: (initialIncomeItems && initialIncomeItems.length) ? initialIncomeItems : [{ id: null, description: '', amount: '' }],
        expenseItems: (initialExpenseItems && initialExpenseItems.length) ? initialExpenseItems : [{ id: null, description: '', amount: '' }],
        rowIsBlank(row) {
            return (!row.description || !row.description.trim()) && (row.amount === '' || row.amount === null || row.amount === undefined);
        },
        // Removes any blank row that isn't the last one (how an agent
        // "deletes" a row — clear both its fields), then guarantees exactly
        // one blank trailing row is always available to type into. Returns
        // true only on the call that actually pushed a fresh row, so the
        // caller can react exactly once, not on every subsequent keystroke
        // into that new row.
        compactAndEnsureTrailing(list) {
            for (let i = list.length - 2; i >= 0; i--) {
                if (this.rowIsBlank(list[i])) list.splice(i, 1);
            }
            if (!list.length || !this.rowIsBlank(list[list.length - 1])) {
                list.push({ id: null, description: '', amount: '' });
                return true;
            }
            return false;
        },
        // 2026-09-08 — Johan, live on QA1: "added values in right hand
        // panel totals do not populate." Root cause, found on the real
        // record: the typed amounts had landed in the DESCRIPTION column,
        // amount left null — an agent racing down a bank statement, typing
        // one amount after another, has no reason to click/tab into each
        // new row's amount box specifically; a new row appearing next to
        // whatever they're already doing is not the same as the cursor
        // actually being IN that row's amount field. Fixed by moving focus
        // there automatically the instant a fresh row appears — but ONLY
        // when the AMOUNT field is what triggered the row (fromAmountField),
        // never the description field, so someone deliberately typing a
        // longer description never has their cursor ripped away mid-word.
        onIncomeRowInput(fromAmountField) {
            const added = this.compactAndEnsureTrailing(this.incomeItems);
            if (added && fromAmountField) this.focusLastAmountField('incomeRows');
            this.save();
        },
        onExpenseRowInput(fromAmountField) {
            const added = this.compactAndEnsureTrailing(this.expenseItems);
            if (added && fromAmountField) this.focusLastAmountField('expenseRows');
            this.save();
        },
        focusLastAmountField(ref) {
            this.$nextTick(() => {
                const inputs = this.$refs[ref].querySelectorAll('[data-role="amount"]');
                inputs[inputs.length - 1]?.focus();
            });
        },
        // Sums exactly what the server will sum (RentalApplicationAssessment::
        // qualifyingResult() sums the same persisted amounts) — this MUST
        // never be allowed to disagree with result.gross_income, so it uses
        // the identical rows, the identical filter, and plain addition.
        incomeTotal() {
            return this.incomeItems.filter(r => !this.rowIsBlank(r)).reduce((sum, r) => sum + (parseFloat(r.amount) || 0), 0);
        },
        expenseTotal() {
            return this.expenseItems.filter(r => !this.rowIsBlank(r)).reduce((sum, r) => sum + (parseFloat(r.amount) || 0), 0);
        },
        // Round 11 — display only (see the field's own comment above).
        statementMonths: initial.statement_months ?? '',
        monthlyAverage(total) {
            const months = parseInt(this.statementMonths, 10);
            if (!months || months < 1) return null;

            return total / months;
        },
        // ── Affordability assessment (unchanged) ──────────────────────────
        fields: initial,
        markedUpDocIds: initialMarkedUpDocIds || [],
        result: initialResult ?? { label: 'incomplete' },
        // ── Authoriser flow — the agent's two actions ─────────────────────
        moreInfoNote: '',
        moreInfoSending: false,
        submittingForApproval: false,
        agentActionStatus: '',
        agentActionError: false,
        async requestMoreInfo() {
            if (this.moreInfoSending || !this.moreInfoNote.trim()) return;
            this.moreInfoSending = true;
            this.agentActionStatus = '';
            try {
                const res = await fetch(requestMoreInfoUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ note: this.moreInfoNote }),
                });
                const data = await res.json().catch(() => ({}));
                if (res.ok && data.ok) {
                    this.agentActionError = false;
                    this.agentActionStatus = data.mail_sent ? 'Sent to the applicant.' : 'Logged, but the email could not be sent — check their email address.';
                    this.moreInfoNote = '';
                } else {
                    this.agentActionError = true;
                    this.agentActionStatus = data.error || 'Could not send — try again.';
                }
            } catch (e) {
                this.agentActionError = true;
                this.agentActionStatus = 'Could not send — check your connection.';
            }
            this.moreInfoSending = false;
        },
        async submitForApproval() {
            if (this.submittingForApproval) return;
            this.submittingForApproval = true;
            this.agentActionStatus = '';
            try {
                const res = await fetch(submitForApprovalUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                        'Accept': 'application/json',
                    },
                });
                const data = await res.json().catch(() => ({}));
                if (res.ok && data.ok) {
                    this.agentActionError = false;
                    this.agentActionStatus = 'Submitted to the authoriser.';
                    setTimeout(() => window.location.reload(), 900);
                } else {
                    this.agentActionError = true;
                    this.agentActionStatus = data.error || 'Could not submit — try again.';
                }
            } catch (e) {
                this.agentActionError = true;
                this.agentActionStatus = 'Could not submit — check your connection.';
            }
            this.submittingForApproval = false;
        },
        saveStatus: initialSavedAt ? ('Saved at ' + formatTime(initialSavedAt)) : '',
        saveError: false,
        saveTimer: null,
        // 2026-09-08 — the SECOND real bug behind "totals do not populate"
        // on the live record: typing at real bank-statement-entry speed
        // fires more than one debounced save before the first one's
        // response comes back. Each of those in-flight requests still
        // carries id: null for a row whose real id the FIRST request's
        // response hasn't delivered yet, so the server (correctly, given
        // what it was told) creates a SECOND new row and soft-deletes the
        // first — over and over, one throwaway row per keystroke that
        // outran the network, never a "the total went to zero" scenario
        // by itself, but exactly the kind of hidden fragility that makes
        // the field-focus bug above so easy to write off as isolated.
        // Fixed by strict serialisation: never more than one save in
        // flight; a save requested while one is already running is
        // queued, not fired — and by the time the queued one actually
        // goes out, the row ids from the request just ahead of it have
        // already landed, so every row keeps the SAME database id across
        // its whole edit history instead of leaving a trail of orphans.
        saveInFlight: false,
        saveQueued: false,
        save() {
            clearTimeout(this.saveTimer);
            this.saveTimer = setTimeout(() => this.performSave(), 150);
        },
        performSave() {
            if (this.saveInFlight) {
                this.saveQueued = true;
                return;
            }
            this.saveInFlight = true;
            this.saveStatus = 'Saving…';
            this.saveError = false;
            // Capture the actual row OBJECTS being sent (not a copy) so
            // the response's ids can be patched back onto them by
            // position after the round trip, without disturbing any
            // blank row the agent has started typing into since —
            // wholesale-replacing the array here would drop that.
            const sentIncomeRows = this.incomeItems.filter(r => !this.rowIsBlank(r));
            const sentExpenseRows = this.expenseItems.filter(r => !this.rowIsBlank(r));
            fetch(saveUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    income_items: sentIncomeRows,
                    expense_items: sentExpenseRows,
                    notes: this.fields.notes,
                    statement_months: this.statementMonths,
                }),
            }).then(r => r.json()).then(data => {
                if (data.ok) {
                    this.result = data.result;
                    (data.income_items || []).forEach((saved, i) => { if (sentIncomeRows[i]) sentIncomeRows[i].id = saved.id; });
                    (data.expense_items || []).forEach((saved, i) => { if (sentExpenseRows[i]) sentExpenseRows[i].id = saved.id; });
                    this.saveStatus = data.saved_at ? ('Saved at ' + formatTime(data.saved_at)) : 'Saved';
                } else {
                    this.saveError = true;
                    this.saveStatus = 'Could not save — try again';
                }
            }).catch(() => {
                this.saveError = true;
                this.saveStatus = 'Could not save — check your connection';
            }).finally(() => {
                this.saveInFlight = false;
                if (this.saveQueued) {
                    this.saveQueued = false;
                    this.performSave();
                }
            });
        },
        formatR(v) {
            return v === null || v === undefined ? '—' : 'R ' + Number(v).toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        // ── Document marks (highlights + notes) — IN-PLACE, one root scope
        // shared with the sticky header and the aside so neither loses access
        // to the other while a document is open. Adapted from viewing-packs/
        // show.blade.php's redactionTool(): same rasterized-page-image render,
        // same Pointer-Events drag capture, same undo/redo, same scale-to-
        // raster-on-submit. Two behavioural changes from that source: (1)
        // rendered INLINE, not in a fixed modal — see the comment above the
        // layout <style> block; (2) a highlight is a STROKE PATH (the actual
        // drag points), not a rectangle — Johan: "click and drag to mark...
        // the way a marker pen works." ────────────────────────────────────
        activeDocId: null,
        activeTool: 'highlight', // default tool = highlighter (Johan)
        loading: false,
        loadError: '',
        applyError: '',
        applying: false,
        justSaved: false,
        label: '',
        firstPageUrl: '',
        remainingPagesUrl: '',
        postUrl: '',
        pages: [],
        totalPages: 0,
        // Progressive load, 2026-09-08 (Johan's decision on the measured 9.2s
        // cold-open cost) — page 1 loads first, the rest load behind it.
        // `pagesLoading` drives the "N more pages loading" banner; `_savedByPage`
        // holds ALL saved marks (raster px, keyed by page index) from the
        // first-page response so marks for not-yet-loaded pages can still be
        // restored the moment their page actually arrives — never dropped.
        pagesLoading: false,
        _savedByPage: {},
        marks: [],   // FLAT array: {type:'highlight', page, points:[{x,y}], width, color} | {type:'note', page, x, y, text, color}
        dirty: false,
        colors: [
            { key: 'yellow', label: 'Yellow', css: '#ffeb3b' },
            { key: 'green',  label: 'Green',  css: '#4cd964' },
            { key: 'pink',   label: 'Pink',   css: '#ff69b4' },
            { key: 'blue',   label: 'Blue',   css: '#5ac8fa' },
        ],
        activeColor: 'yellow',
        // Highlighter size, 2026-09-08 — Johan: "current on highlights too
        // much lines on bank statement - lines are small there." Three
        // presets, not a slider. 'medium' (22) is the unchanged prior
        // default, so nothing changes for an agent who never touches this.
        strokeSizes: [
            { key: 'thin',   label: 'Thin',   px: 10 },
            { key: 'medium', label: 'Medium', px: 22 },
            { key: 'thick',  label: 'Thick',  px: 36 },
        ],
        strokeSizeKey: 'medium',
        strokeWidth: 22,
        setStrokeSize(key) {
            const s = this.strokeSizes.find(s => s.key === key);
            if (!s) return;
            this.strokeSizeKey = key;
            this.strokeWidth = s.px;
            try { localStorage.setItem('rahStrokeSizeKey', key); } catch (_) {}
        },
        drag: { active: false, page: null, points: [] },
        openNote: null,       // {page, index} — an existing note's popover open
        pendingNote: null,    // {page, x, y} — a new note being typed
        pendingNoteText: '',
        undoStack: [],
        redoStack: [],

        async openHighlighter(detail) {
            // Switching documents while unsaved marks exist — 2026-09-08: this
            // USED to auto-save silently here, which is exactly the ambiguity
            // Johan flagged ("will it automatically save?" — he could not
            // tell). Explicit-save only now, matching the viewing-pack
            // redaction tool: ask, in words, rather than act silently. Never
            // lose the marks either way — declining just keeps the agent on
            // the current document with their marks intact.
            if (this.activeDocId !== null && this.activeDocId !== detail.documentId && this.dirty) {
                if (! confirm('You have unsaved highlights or notes on this document. Save them before switching?')) {
                    return;
                }
                await this.applyHighlights();
                if (this.applyError) return; // save failed — stay put, don't lose the marks by switching away
            }
            if (this.activeDocId === detail.documentId) {
                this.activeDocId = null; // toggle closed
                return;
            }

            this.activeDocId = detail.documentId;
            this.firstPageUrl = detail.firstPageUrl;
            this.remainingPagesUrl = detail.remainingPagesUrl;
            this.postUrl = detail.postUrl;
            this.label = detail.label || '';
            this.activeTool = 'highlight';
            this.pages = [];
            this.totalPages = 0;
            this.marks = [];
            this._savedByPage = {};
            this.dirty = false;
            this.undoStack = [];
            this.redoStack = [];
            this.loadError = '';
            this.applyError = '';
            this.justSaved = false;
            this.pagesLoading = false;
            this.openNote = null;
            this.pendingNote = null;
            this.activeColor = 'yellow';
            this.loading = true;
            try {
                const res = await fetch(this.firstPageUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
                if (!res.ok) {
                    let msg = '';
                    try { msg = (await res.json()).error || ''; } catch (_) {}
                    this.loadError = msg || ('This document could not be opened (HTTP ' + res.status + ').');
                    this.loading = false;
                    return;
                }
                const data = await res.json();
                if (!data.page) { this.loadError = 'The document opened but produced no pages.'; this.loading = false; return; }
                this.pages = [data.page];
                this.totalPages = data.total_pages || 1;
                // Existing saved marks come back in ONE blob (RASTER px, keyed by
                // page index) — never split per page, so a mark for a page that
                // hasn't loaded yet is never at risk of being dropped; it's just
                // restored later, the moment its page actually arrives (see
                // fetchRemainingPages() below).
                this._savedByPage = data.marks || {};
                this.restoreSavedMarksForPages([0]);
                this.loading = false;

                if (this.totalPages > 1) {
                    this.fetchRemainingPages();
                }
            } catch (e) {
                this.loadError = 'This document could not be opened: ' + (e && e.message ? e.message : 'network error') + '.';
                this.loading = false;
            }
        },
        // Progressive load, 2026-09-08 — runs AFTER page 1 is already on
        // screen; deliberately not awaited by openHighlighter() so the agent
        // can start reading/marking page 1 immediately. `pagesLoading` drives
        // the on-screen "N more pages loading" banner (Johan: the agent must
        // be able to SEE more pages are coming, not just guess).
        async fetchRemainingPages() {
            this.pagesLoading = true;
            try {
                const res = await fetch(this.remainingPagesUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
                if (!res.ok) {
                    let msg = '';
                    try { msg = (await res.json()).error || ''; } catch (_) {}
                    this.loadError = msg || ('The rest of this document could not be loaded (HTTP ' + res.status + ').');
                    this.pagesLoading = false;
                    return;
                }
                const data = await res.json();
                const newPages = data.pages || [];
                this.pages = this.pages.concat(newPages);
                this.restoreSavedMarksForPages(newPages.map(p => p.index));
            } catch (e) {
                this.loadError = 'The rest of this document could not be loaded: ' + (e && e.message ? e.message : 'network error') + '.';
            }
            this.pagesLoading = false;
        },
        // Shared by both the first-page load and the remaining-pages load —
        // converts saved marks (RASTER px) to DISPLAY px for the given page
        // indexes, once their images have actually laid out.
        restoreSavedMarksForPages(pageIndexes) {
            this.$nextTick(() => {
                for (const pageIndex of pageIndexes) {
                    const saved = this._savedByPage[String(pageIndex)] || this._savedByPage[pageIndex];
                    if (!saved) continue;
                    const page = this.pages.find(p => p.index === pageIndex);
                    const img = document.querySelector('img.rah-page-img[data-page="' + pageIndex + '"]');
                    if (!page || !img || !img.clientWidth) continue;
                    const scaleX = img.clientWidth / page.width;
                    const scaleY = img.clientHeight / page.height;
                    saved.forEach(m => {
                        if (m.type === 'note') {
                            this.marks.push({ type: 'note', page: pageIndex, x: m.x * scaleX, y: m.y * scaleY, text: m.text, color: m.color || 'yellow' });
                        } else {
                            this.marks.push({
                                type: 'highlight', page: pageIndex,
                                points: (m.points || []).map(p => ({ x: p.x * scaleX, y: p.y * scaleY })),
                                width: (m.width || 26) * scaleX,
                                color: m.color || 'yellow',
                            });
                        }
                    });
                }
            });
        },
        async closeHighlighter() {
            // Same explicit-confirm rule as openHighlighter() above — "Done"
            // must never silently save-or-discard without the agent knowing
            // which happened.
            if (this.dirty) {
                if (! confirm('You have unsaved highlights or notes on this document. Save them before closing?')) {
                    return;
                }
                await this.applyHighlights();
                if (this.applyError) return; // save failed — stay open so nothing is lost
            }
            this.activeDocId = null;
        },
        colorCss(key) {
            const c = this.colors.find(c => c.key === key);
            return c ? c.css : this.colors[0].css;
        },
        strokesFor(p) { return this.marks.filter(m => m.type === 'highlight' && m.page === p); },
        notesFor(p) { return this.marks.filter(m => m.type === 'note' && m.page === p); },
        // 2026-09-08 — built as a markup STRING and bound via x-html on the
        // <svg> itself, deliberately NOT <template x-for>/<template x-if>
        // inside the svg (see the comment above the <svg> tag for why that
        // silently failed in every real browser). Still fully reactive:
        // x-html re-evaluates this expression on every dependency change
        // exactly like x-text/x-show does, and every value here comes from
        // this component's own numeric drag/mark state, never free-typed
        // text, so there is nothing here that needs HTML-escaping.
        strokesSvgFor(p) {
            const poly = (points, color, width) =>
                '<polyline points="' + points.map(pt => Number(pt.x) + ',' + Number(pt.y)).join(' ') + '" fill="none" stroke="' + this.colorCss(color) + '" stroke-opacity="0.4" stroke-width="' + Number(width) + '" stroke-linecap="round" stroke-linejoin="round"></polyline>';
            let svg = this.strokesFor(p).map(m => poly(m.points, m.color, m.width)).join('');
            if (this.drag.active && this.drag.page === p && this.activeTool === 'highlight') {
                svg += poly(this.drag.points, this.activeColor, this.strokeWidth);
            }
            return svg;
        },
        clearPage(p) { this.pushHistory(); this.marks = this.marks.filter(m => m.page !== p); this.dirty = true; },
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
            this.dirty = true;
        },
        redo() {
            if (!this.redoStack.length) return;
            this.undoStack.push(this._snapshot());
            this.marks = this.redoStack.pop();
            this.dirty = true;
        },
        removeMark(page, idxWithinType, type) {
            const list = type === 'note' ? this.notesFor(page) : this.strokesFor(page);
            const target = list[idxWithinType];
            if (!target) return;
            const idx = this.marks.indexOf(target);
            if (idx === -1) return;
            this.pushHistory();
            this.marks.splice(idx, 1);
            this.dirty = true;
            this.openNote = null;
        },
        toggleNotePopover(page, idx) {
            if (this.openNote && this.openNote.page === page && this.openNote.index === idx) {
                this.openNote = null;
            } else {
                this.openNote = { page, index: idx };
            }
        },
        onHighlightKey(e) {
            if (this.activeDocId === null) return;
            const mod = e.ctrlKey || e.metaKey;
            if (!mod) return;
            const k = (e.key || '').toLowerCase();
            if (k === 'z' && !e.shiftKey) { e.preventDefault(); this.undo(); }
            else if ((k === 'z' && e.shiftKey) || k === 'y') { e.preventDefault(); this.redo(); }
        },

        // Highlight drag: capture the ACTUAL path (a real marker-pen gesture),
        // not just a start/end rectangle.
        startDraw(e, page) {
            if (this.activeTool === 'note') { return; }
            try { e.currentTarget.setPointerCapture(e.pointerId); } catch (_) {}
            const r = e.currentTarget.getBoundingClientRect();
            const x = e.clientX - r.left, y = e.clientY - r.top;
            this.drag = { active: true, page, points: [{ x, y }] };
        },
        moveDraw(e, page) {
            if (!this.drag.active || this.drag.page !== page) return;
            const r = e.currentTarget.getBoundingClientRect();
            const x = e.clientX - r.left, y = e.clientY - r.top;
            const last = this.drag.points[this.drag.points.length - 1];
            // Only add a point once the cursor has actually moved a few px —
            // keeps the stored path small without losing the drawn shape.
            if (!last || Math.hypot(x - last.x, y - last.y) > 2) {
                this.drag.points.push({ x, y });
            }
        },
        endDraw(e, page) {
            if (this.activeTool === 'note') {
                if (this.pendingNote) return; // one pending note at a time
                const r = e.currentTarget.getBoundingClientRect();
                const x = e.clientX - r.left, y = e.clientY - r.top;
                this.pendingNote = { page, x, y };
                this.pendingNoteText = '';
                // 2026-09-08 — cc1 caught this: focus silently never landed.
                // Root cause found by re-testing exactly the way cc1 did (a
                // real click, checking document.activeElement immediately,
                // no manual click of my own in between — my earlier "proof"
                // had quietly clicked the textarea myself before checking,
                // which tested "can this element be focused" instead of
                // "does opening a note focus it automatically", and masked
                // the real bug). This markup lives inside x-for="page in
                // pages" — one note textarea per loaded page, ALL sharing
                // the ref name x-ref="pendingNoteInput". With N pages loaded,
                // there were N elements answering to that one name, so
                // $refs.pendingNoteInput resolved unreliably — not
                // necessarily the page actually visible — and calling
                // .focus() on a hidden (display:none) element does nothing,
                // silently, no error. Fixed by never relying on a ref shared
                // across loop iterations: query the ONE textarea tagged with
                // THIS page's own index instead.
                this.$nextTick(() => {
                    const el = document.querySelector('textarea[data-pending-note-page="' + page + '"]');
                    if (el) el.focus();
                });
                return;
            }
            if (!this.drag.active || this.drag.page !== page) return;
            try { e.currentTarget.releasePointerCapture(e.pointerId); } catch (_) {}
            if (this.drag.points.length >= 2) {
                this.pushHistory();
                this.marks.push({ type: 'highlight', page, points: this.drag.points, width: this.strokeWidth, color: this.activeColor });
                this.dirty = true;
            }
            this.drag = { active: false, page: null, points: [] };
        },
        commitNote() {
            if (!this.pendingNote) return;
            const text = this.pendingNoteText.trim();
            if (text !== '') {
                this.pushHistory();
                this.marks.push({ type: 'note', page: this.pendingNote.page, x: this.pendingNote.x, y: this.pendingNote.y, text, color: this.activeColor });
                this.dirty = true;
            }
            this.pendingNote = null;
            this.pendingNoteText = '';
        },

        async applyHighlights() {
            if (this.applying || this.activeDocId === null) return;
            // Progressive load, 2026-09-08 — the save payload is built from
            // `this.pages` (only the pages loaded so far) and REPLACES the
            // document's whole mark set server-side. Saving while the
            // remaining pages are still loading would silently wipe out any
            // already-saved marks on those not-yet-loaded pages — exactly
            // the "silently lose it" Johan ruled out. Refuse, don't guess.
            if (this.pagesLoading) {
                this.applyError = 'Still loading the rest of this document — wait a moment, then save.';
                return;
            }
            this.applyError = '';
            this.justSaved = false;
            this.applying = true;
            try {
                const marksByPage = {};
                for (const page of this.pages) {
                    const img = document.querySelector('img.rah-page-img[data-page="' + page.index + '"]');
                    if (!img || !img.clientWidth) continue;
                    const scaleX = page.width / img.clientWidth;
                    const scaleY = page.height / img.clientHeight;

                    const strokes = this.strokesFor(page.index).map(m => ({
                        type: 'highlight',
                        points: m.points.map(p => ({ x: Math.round(p.x * scaleX), y: Math.round(p.y * scaleY) })),
                        width: Math.round(m.width * scaleX),
                        color: m.color,
                    }));
                    const notes = this.notesFor(page.index).map(m => ({
                        type: 'note',
                        x: Math.round(m.x * scaleX), y: Math.round(m.y * scaleY),
                        text: m.text, color: m.color,
                    }));
                    // 2026-09-08 — ALWAYS assign, even an empty array. The
                    // server now requires the payload to name every one of
                    // the document's pages (see applyHighlight()'s
                    // completeness check) so it can tell "this page
                    // genuinely has zero marks" apart from "this page was
                    // never mentioned" — omitting empty pages here would
                    // make every real, complete save look incomplete and
                    // get refused.
                    marksByPage[page.index] = [...strokes, ...notes];
                }

                const res = await fetch(this.postUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ marks: marksByPage }),
                });
                if (!res.ok) {
                    let msg = '';
                    try { msg = (await res.json()).error || ''; } catch (_) {}
                    this.applyError = msg || ('Saving failed (HTTP ' + res.status + ').');
                    this.applying = false;
                    return;
                }
                this.applying = false;
                this.dirty = false;
                // Keep the document list's "Marked up" badge in sync without a full
                // reload — a reload would collapse the inline viewer we just fixed
                // Johan's "separate screen" complaint by removing.
                const hasAny = this.markCount() > 0;
                const idx = this.markedUpDocIds.indexOf(this.activeDocId);
                if (hasAny && idx === -1) this.markedUpDocIds.push(this.activeDocId);
                if (!hasAny && idx !== -1) this.markedUpDocIds.splice(idx, 1);
                // Visible proof it saved (Johan: "a silent save is indistinguishable
                // from no save") — a persistent header badge, not a message that can
                // vanish unnoticed while the agent is scrolled away from it.
                this.justSaved = true;
                setTimeout(() => { this.justSaved = false; }, 3000);
            } catch (err) {
                this.applyError = 'Saving failed: ' + (err && err.message ? err.message : 'network error') + '.';
                this.applying = false;
            }
        },
    };
}

function formatTime(iso) {
    return new Date(iso).toLocaleTimeString('en-ZA', { hour: '2-digit', minute: '2-digit' });
}

// 2026-09-08 — Johan: "essentially everything should fit that the whole
// screen doesnt scroll, but the left and right panels scroll in the
// screen." MEASURES the real space left inside #appScroll (layouts/corex.
// blade.php's own scroll container) rather than guessing a fixed vh
// number — the guess was wrong because it never accounted for the real,
// variable height of the QA/env banner above the app, so the panels ended
// up taller than the space actually available and #appScroll itself had
// to scroll too. All values read here (header height, its margin, this
// element's own margin) are independent of #appScroll's OWN scroll
// position, so this stays correct however the page is scrolled.
function rentalReviewLayout() {
    return {
        init() {
            const recalc = () => {
                const scrollEl = document.getElementById('appScroll');
                const header = document.querySelector('.sticky.top-0.z-50');
                if (!scrollEl || !header) return;
                const headerSpace = header.offsetHeight + parseFloat(getComputedStyle(header).marginBottom || 0);
                const columnsMarginTop = parseFloat(getComputedStyle(this.$el).marginTop || 0);
                const available = scrollEl.clientHeight - headerSpace - columnsMarginTop - 8;
                this.$el.style.setProperty('--rr-panel-h', Math.max(300, available) + 'px');
            };
            recalc();
            window.addEventListener('resize', recalc);
            // Fonts/images can still shift real heights slightly after the
            // first paint — one more pass once layout has settled.
            setTimeout(recalc, 300);
        },
    };
}

// Agent-added-documents, 2026-09-08 — cc4's widget, handed to me for this
// file. Own component, zero shared state with rentalReview().
function agentDocumentUploadReview() {
    return {
        uploading: [],
        csrfToken() { return document.querySelector('meta[name="csrf-token"]').content; },
        async uploadFile(file) {
            const tempId = 'u' + Date.now() + Math.random();
            this.uploading.push({ tempId, name: file.name, error: null });
            const formData = new FormData();
            formData.append('supporting_files[]', file);
            try {
                const res = await fetch('{{ route('corex.rental-applications.documents.upload', $rentalApplication) }}', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken() },
                    body: formData,
                });
                const data = await res.json().catch(() => ({}));
                const item = this.uploading.find(u => u.tempId === tempId);
                if (!res.ok) { item.error = (data.errors && Object.values(data.errors)[0]?.[0]) || data.message || 'Upload failed.'; return; }
                this.uploading = this.uploading.filter(u => u.tempId !== tempId);
                window.location.reload();
            } catch (e) {
                const item = this.uploading.find(u => u.tempId === tempId);
                if (item) item.error = 'Network error — please try again.';
            }
        },
        async onFilesSelected(fileList) { await Promise.all(Array.from(fileList).map(file => this.uploadFile(file))); },
    };
}
</script>
@endsection
