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
            <template x-if="activeDocId === null">
                <a href="{{ route('corex.rental-applications.show', $rentalApplication) }}" class="corex-btn-outline text-xs">Back to application</a>
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
            .rental-review-main    { max-height: calc(100vh - 88px); overflow-y: auto; }
            .rental-review-aside   { flex: 0 0 260px; width: 260px; align-self: stretch; position: sticky; top: 72px; max-height: calc(100vh - 88px); overflow-y: auto; }
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
                                                             stroke (a real marker-pen gesture, not a rectangle). --}}
                                                        <svg class="absolute inset-0" style="pointer-events:none; width:100%; height:100%;">
                                                            <template x-for="(mark, mi) in strokesFor(page.index)" :key="'s'+mi">
                                                                <polyline :points="mark.points.map(p => p.x + ',' + p.y).join(' ')"
                                                                          fill="none" :stroke="colorCss(mark.color)" stroke-opacity="0.4"
                                                                          :stroke-width="mark.width" stroke-linecap="round" stroke-linejoin="round"></polyline>
                                                            </template>
                                                            <template x-if="drag.active && drag.page === page.index && activeTool === 'highlight'">
                                                                <polyline :points="drag.points.map(p => p.x + ',' + p.y).join(' ')"
                                                                          fill="none" :stroke="colorCss(activeColor)" stroke-opacity="0.4"
                                                                          :stroke-width="strokeWidth" stroke-linecap="round" stroke-linejoin="round"></polyline>
                                                            </template>
                                                        </svg>
                                                        {{-- Remove-stroke handles (one per stroke, at its first point). --}}
                                                        <template x-for="(mark, mi) in strokesFor(page.index)" :key="'r'+mi">
                                                            <button type="button" title="Remove this mark"
                                                                    @pointerdown.stop.prevent="removeMark(page.index, mi, 'highlight')"
                                                                    :style="{ position:'absolute', left:(mark.points[0].x-9)+'px', top:(mark.points[0].y-9)+'px', width:'18px', height:'18px', borderRadius:'9999px', background:'#475569', color:'#fff', fontSize:'12px', lineHeight:'16px', textAlign:'center', border:'1px solid #fff', padding:'0', pointerEvents:'auto', cursor:'pointer' }">&times;</button>
                                                        </template>
                                                        {{-- Notes — a pinned marker + its text, visible inline. --}}
                                                        <template x-for="(note, ni) in notesFor(page.index)" :key="'n'+ni">
                                                            <div :style="{ position:'absolute', left:note.x+'px', top:note.y+'px', transform:'translate(-50%,-50%)', pointerEvents:'auto' }">
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
                                                        <div x-show="pendingNote && pendingNote.page === page.index" x-cloak
                                                             :style="{ position:'absolute', left:(pendingNote ? pendingNote.x : 0)+'px', top:(pendingNote ? pendingNote.y : 0)+'px', transform:'translate(-50%,-50%)', pointerEvents:'auto' }">
                                                            <div class="rounded-md p-2" style="width:220px; background: var(--surface); border:1px solid var(--border); box-shadow: 0 4px 12px rgba(0,0,0,0.2);">
                                                                <textarea x-model="pendingNoteText" rows="3" class="corex-input text-xs w-full" placeholder="Note text…" @click.stop x-ref="pendingNoteInput"></textarea>
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
            <h2 class="text-sm font-semibold mb-1" style="color: var(--text-primary);">Affordability Assessment</h2>
            {{-- 2026-09-08 — Johan: "right hand panel? is that filled in from
                 where?" It wasn't filled in from anywhere — every field below
                 is blank until an agent types into it, and nothing here is
                 ever pre-populated from the application. Saying so plainly,
                 on screen, is the fix (not just in a code comment he'll never
                 see). --}}
            <p class="text-xs mb-4" style="color: var(--text-muted);">
                You type these — nothing here is pre-filled from the application, and nothing here is
                sent to the applicant or shown anywhere else. Saved automatically as you type.
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
                    <p class="text-sm" style="color: var(--text-muted);">Enter income above, and make sure "Current rental amount" is filled in on the application itself, to see a suggestion here.</p>
                </template>
                {{-- 2026-09-08 — Johan: "right hand panel? is that filled in
                     from where?" This calculation's RENT figure was never
                     shown — an agent could see a suggestion without knowing
                     what rent it was measured against. Naming the source
                     (the applicant's own self-reported current rent, from
                     the application form) here, not just in a tooltip. --}}
                <template x-if="result.label !== 'incomplete'">
                    <div class="text-sm space-y-1">
                        <p>Total income (your entries above): <strong x-text="formatR(result.total_income)"></strong></p>
                        <p>Rent (applicant's self-reported current rent, from the application): <strong x-text="formatR(result.rent)"></strong></p>
                        <p>Income needed at &times; <span x-text="result.multiplier"></span>: <strong x-text="formatR(result.required_income)"></strong></p>
                        <p class="mt-2">
                            <span class="ds-badge" :class="result.meets_threshold ? 'ds-badge-success' : 'ds-badge-warning'"
                                  x-text="result.meets_threshold ? 'Income appears to cover the rent — worth a closer look either way' : 'Income may not cover the rent — worth a closer look'"></span>
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

                @unless(in_array($rentalApplication->status, ['approved', 'declined'], true))
                    <p class="text-xs font-semibold uppercase tracking-wide mb-2" style="color: var(--text-muted);">Next step</p>
                    <textarea x-model="moreInfoNote" rows="2" class="corex-input text-xs w-full mb-2" placeholder="What do you need from the applicant? (for Request more info)"></textarea>
                    <div class="flex flex-col gap-2">
                        <button type="button" class="corex-btn-outline text-xs w-full" :disabled="moreInfoSending || !moreInfoNote.trim()" @click="requestMoreInfo()" x-text="moreInfoSending ? 'Sending…' : 'Request more info from applicant'"></button>
                        <button type="button" class="corex-btn-primary text-xs w-full" :disabled="submittingForApproval" @click="submitForApproval()" x-text="submittingForApproval ? 'Submitting…' : ({{ $isPendingAuthorisation ? 'true' : 'false' }} ? 'Re-submit to authoriser' : 'Submit to authoriser')"></button>
                    </div>
                    <p class="text-xs mt-2" x-show="agentActionStatus" x-text="agentActionStatus" :style="agentActionError ? 'color: var(--ds-red, #dc2626);' : 'color: var(--ds-emerald, #059669);'"></p>
                @endunless
            </div>
        </div>
    </div>
</div>

<script>
function rentalReview({ saveUrl, initial, initialResult, initialSavedAt, initialMarkedUpDocIds, requestMoreInfoUrl, submitForApprovalUrl }) {
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
        strokeWidth: 22,
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
                this.$nextTick(() => this.$refs.pendingNoteInput && this.$refs.pendingNoteInput.focus());
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
