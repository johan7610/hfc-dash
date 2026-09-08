{{-- DESIGN SYSTEM COMPLIANCE: UI_DESIGN_SYSTEM.md v 2026-04-20 --}}
{{-- AT-392 authoriser flow, 2026-09-08 — the authoriser's read + decide view.
     Same dominant-document + narrow-panel layout the agent's review screen
     already uses (resources/views/corex/rental-applications/review.blade.php)
     — matching an existing CoreX pattern rather than inventing a new one.
     Three outcomes: Approve (captures the monthly amount), Decline, Request
     more information (goes back to the agent). Each has its own free-text
     box. A CO acting on an application that already has a decision is an
     OVERRIDE — reason becomes required, and it's marked unmistakably as an
     override both here and in the audit trail below. --}}
@extends('layouts.corex')

@php
    // 2026-09-08 — the authoriser now marks up documents too (Johan: "the
    // auth should be able to write on the docs as well making notes etc").
    // Same shared viewer as the agent's review screen
    // (partials/document-highlighter-script.blade.php's
    // rentalDocumentHighlighter()) — same backend trait, same routes shape,
    // just under the authorisation namespace.
    $initialMarkedUpDocIds = $documents->filter(fn ($row) => $row['has_highlights'])->pluck('document.id')->values();
@endphp

@section('corex-content')
<div class="w-full"
     x-data="rentalAuthorisationViewer({
         initialMarkedUpDocIds: {{ Js::from($initialMarkedUpDocIds) }},
     })">

    <div class="rounded-md px-6 py-4 corex-page-banner flex items-center justify-between">
        <div>
            <h1 class="text-base font-bold leading-tight" style="color: var(--text-primary);">
                Authorise — {{ $rentalApplication->contact->full_name ?? $rentalApplication->contact->first_name . ' ' . $rentalApplication->contact->last_name }}
            </h1>
            <p class="text-xs" style="color: var(--text-muted);">
                {{ $rentalApplication->property_address_override ?? optional($rentalApplication->property)->address ?? 'No property linked' }}
                &middot; Submitted for approval {{ $rentalApplication->submitted_for_approval_at?->format('d M Y H:i') }}
            </p>
        </div>
        <a href="{{ route('corex.rental-applications.authorisation.index') }}" class="corex-btn-outline text-xs">Back to list</a>
    </div>

    @if($alreadyDecided)
        <div class="rounded-md px-4 py-3 text-sm mt-4" style="background: var(--ds-amber-soft, #fffbeb); color: var(--ds-amber, #92400e); border: 1px solid var(--ds-amber, #f59e0b);">
            This application already has a decision: <strong>{{ ucfirst($rentalApplication->status) }}</strong>{{ $rentalApplication->approved_rental_amount ? ' for R' . number_format($rentalApplication->approved_rental_amount, 2) : '' }}.
            @if($canOverride)
                Acting below will OVERRIDE it — a reason is required.
            @else
                Only a CO (Override) user may change it.
            @endif
        </div>
    @endif

    {{-- Save confirmation toast — same pattern as review.blade.php's own. --}}
    <div x-show="justSaved" x-cloak x-transition
         class="fixed z-40 rounded-md px-3 py-2 text-xs font-medium flex items-center gap-1.5"
         style="top: 72px; right: 20px; background: var(--ds-emerald, #059669); color: #fff; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
        &check; Marks saved — the next person to open this document sees them.
    </div>

    <style>
        .rah-auth-columns { display: flex; flex-direction: column; gap: 20px; }
        .rah-auth-main    { flex: 1 1 auto; min-width: 0; }
        .rah-auth-aside   { width: 100%; }
        @media (min-width: 1280px) {
            .rah-auth-columns { flex-direction: row; gap: 16px; align-items: stretch; }
            .rah-auth-main    { max-height: calc(100vh - 32px); overflow-y: auto; }
            .rah-auth-aside   { flex: 0 0 320px; width: 320px; align-self: stretch; position: sticky; top: 16px; max-height: calc(100vh - 32px); overflow-y: auto; }
        }
    </style>

    <div class="rah-auth-columns mt-5">
        <div class="rah-auth-main space-y-4">
            <div class="rounded-md p-4" style="background: var(--surface); border: 1px solid var(--border);">
                <h2 class="text-sm font-semibold mb-3" style="color: var(--text-primary);">Submitted Application</h2>
                <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-xs">
                    <dt style="color: var(--text-muted);">Employer</dt><dd>{{ $rentalApplication->employer_name ?? '—' }}</dd>
                    <dt style="color: var(--text-muted);">Position</dt><dd>{{ $rentalApplication->employer_position ?? '—' }}</dd>
                    <dt style="color: var(--text-muted);">Monthly salary (self-reported)</dt><dd>{{ $rentalApplication->monthly_salary !== null ? 'R ' . number_format($rentalApplication->monthly_salary, 2) : '—' }}</dd>
                    <dt style="color: var(--text-muted);">Current rental amount</dt><dd>{{ $rentalApplication->current_rental_amount !== null ? 'R ' . number_format($rentalApplication->current_rental_amount, 2) : '—' }}</dd>
                    <dt style="color: var(--text-muted);">Current landlord</dt><dd>{{ $rentalApplication->current_landlord_name ?? '—' }}</dd>
                    <dt style="color: var(--text-muted);">Adults / Children</dt><dd>{{ $rentalApplication->adults ?? '—' }} / {{ $rentalApplication->children ?? '—' }}</dd>
                </dl>
            </div>

            <div class="rounded-md p-4" style="background: var(--surface); border: 1px solid var(--border);">
                <h2 class="text-sm font-semibold mb-1" style="color: var(--text-primary);">Agent's Assessment</h2>
                <p class="text-xs mb-3" style="color: var(--text-muted);">Read-only — captured by the submitting agent.</p>
                {{-- 2026-09-08 — Johan, application 9: "now all the work the
                     agent did is nowhere to be found on the auth screen -
                     no values from the right hand panel." Root cause: this
                     block still referenced $assessment->monthly_income /
                     other_monthly_income / monthly_expenses — three columns
                     that no longer exist on the model (Round 9 replaced them
                     with the growable incomeItems()/expenseItems() lines,
                     see the model's own docblock). Eloquent returns null for
                     an unknown attribute rather than erroring, so every row
                     here silently showed "—" instead of a real figure — no
                     error, nothing to notice, exactly why this got missed.
                     The controller was already passing the right data
                     ($assessment, $result via qualifyingResult()); only this
                     view was stale. Rebuilt to show every line the agent
                     actually captured — the authoriser is deciding on a
                     person's home from this, not a display nicety. --}}
                <div class="text-xs mb-3">
                    <p style="color: var(--text-muted);">Number of months this bank statement covers</p>
                    <p class="font-semibold" style="color: var(--text-primary);">{{ $assessment->statement_months ?? '—' }}</p>
                </div>
                <div class="text-xs mb-3">
                    <p class="font-medium mb-1" style="color: var(--text-secondary);">Income (gross, before deductions)</p>
                    @forelse($assessment->incomeItems as $item)
                        <div class="flex items-center justify-between py-0.5">
                            <span style="color: var(--text-primary);">{{ $item->description ?: '(no description)' }}</span>
                            <span style="color: var(--text-primary);">R {{ number_format($item->amount, 2) }}</span>
                        </div>
                    @empty
                        <p style="color: var(--text-muted);">Nothing captured yet.</p>
                    @endforelse
                    @if($result && $result['total_captured_income'] !== null)
                        <p class="mt-1" style="color: var(--text-secondary);">Total captured: <strong>R {{ number_format($result['total_captured_income'], 2) }}</strong></p>
                        @if($result['gross_income'] !== null)
                            <p style="color: var(--text-secondary);">Monthly average (÷ {{ $result['statement_months'] }} months — used in the affordability check below): <strong>R {{ number_format($result['gross_income'], 2) }}</strong></p>
                        @endif
                    @endif
                </div>
                <div class="text-xs mb-3">
                    <p class="font-medium mb-1" style="color: var(--text-secondary);">Expenses / existing debt</p>
                    @forelse($assessment->expenseItems as $item)
                        <div class="flex items-center justify-between py-0.5">
                            <span style="color: var(--text-primary);">{{ $item->description ?: '(no description)' }}</span>
                            <span style="color: var(--text-primary);">R {{ number_format($item->amount, 2) }}</span>
                        </div>
                    @empty
                        <p style="color: var(--text-muted);">Nothing captured.</p>
                    @endforelse
                    @if($result && $result['total_captured_expenses'] !== null)
                        <p class="mt-1" style="color: var(--text-secondary);">Total captured: <strong>R {{ number_format($result['total_captured_expenses'], 2) }}</strong></p>
                    @endif
                </div>
                {{-- The rule, stated as the law states it: rent must not
                     exceed {max_rent_percent}% of GROSS income. Not a
                     multiplier of rent (the same arithmetic wearing a
                     disguise). --}}
                @if($result && $result['label'] !== 'incomplete')
                    <div class="rounded-md p-3" style="background: var(--ds-slate-soft, #f1f5f9); border: 1px solid var(--border);">
                        <p class="text-[11px] font-semibold uppercase tracking-wide mb-1" style="color: var(--text-muted);">Suggested check — not a rule</p>
                        <p class="text-sm">Gross income R{{ number_format($result['gross_income'], 2) }} — rent must not exceed {{ rtrim(rtrim(number_format($result['max_rent_percent'], 2), '0'), '.') }}% of this (R{{ number_format($result['max_affordable_rent'], 2) }}). Actual rent (R{{ number_format($result['rent'], 2) }}) is {{ $result['rent_as_percent_of_gross'] }}% of gross income.
                            <span class="ds-badge" :class="'{{ $result['meets_threshold'] ? 'ds-badge-success' : 'ds-badge-warning' }}'">{{ $result['meets_threshold'] ? 'Within the affordability guideline' : 'Exceeds the affordability guideline' }}</span>
                        </p>
                    </div>
                @elseif($result)
                    <div class="rounded-md p-3 text-xs" style="background: var(--ds-slate-soft, #f1f5f9); border: 1px solid var(--border); color: var(--text-muted);">
                        Not enough captured yet to run the affordability guideline (needs both income and the number of months).
                    </div>
                @endif
                @if($assessment->notes)
                    <p class="text-xs mt-3 whitespace-pre-wrap" style="color: var(--text-primary);">{{ $assessment->notes }}</p>
                @endif
            </div>

            <div class="rounded-md p-4" style="background: var(--surface); border: 1px solid var(--border);">
                <h2 class="text-sm font-semibold mb-3" style="color: var(--text-primary);">
                    Supporting Documents
                    <span class="ds-badge ds-badge-default">{{ $documents->count() }}</span>
                </h2>
                @if($documents->isEmpty())
                    <p class="text-xs" style="color: var(--text-muted);">No supporting documents.</p>
                @else
                    <div class="space-y-2">
                        @foreach($documents as $row)
                            @php $document = $row['document']; @endphp
                            <div class="rounded-md border" style="border-color: var(--border);">
                                <div class="flex items-center justify-between px-3 py-2 text-xs">
                                    <span>{{ $document->original_name }}</span>
                                    <span class="flex items-center gap-2">
                                        <span class="ds-badge ds-badge-success" x-show="markedUpDocIds.includes({{ $document->id }})" x-cloak title="This document has saved marks — visible to anyone who opens it next.">Marked up</span>
                                        @if($row['inline_viewable'])
                                            <button type="button" style="color: var(--ds-blue, #2563eb); font-weight: 600;"
                                                    @click="openHighlighter({
                                                        documentId: {{ $document->id }},
                                                        firstPageUrl: '{{ route('corex.rental-applications.authorisation.documents.highlight-data.first', [$rentalApplication, $document]) }}',
                                                        remainingPagesUrl: '{{ route('corex.rental-applications.authorisation.documents.highlight-data.remaining', [$rentalApplication, $document]) }}',
                                                        postUrl: '{{ route('corex.rental-applications.authorisation.documents.highlight', [$rentalApplication, $document]) }}',
                                                        label: {{ Js::from($document->original_name) }},
                                                    })"
                                                    x-text="activeDocId === {{ $document->id }} ? 'Close' : 'View & Mark Up'"></button>
                                        @else
                                            <span class="ds-badge ds-badge-default" title="This file type cannot be previewed on screen — download it to view it.">No preview</span>
                                        @endif
                                    </span>
                                </div>

                                {{-- IN-PLACE viewer, 2026-09-08 — Johan: "should load the doc in
                                     the same screen that the reviewer can see the submitted info
                                     whilst working through the doc... how do I now look at the
                                     information and the document at the same time?" Same
                                     in-flow-block pattern as review.blade.php: this lives inside
                                     rah-auth-main's own independently-scrolling column, so
                                     rah-auth-aside (the Decision panel) stays visible the whole
                                     time — a plain flex sibling, never covered. The toolbar (tool
                                     picker, colours, undo/redo, save) is inline at the top of the
                                     panel rather than in a sticky header — this screen doesn't use
                                     x-sticky-action-bar and adopting it here wasn't worth the risk
                                     of touching that shared component for this one screen. --}}
                                <div x-show="activeDocId === {{ $document->id }}" x-cloak class="px-3 pb-3 border-t" style="border-color: var(--border);">
                                    <div class="flex items-center gap-3 flex-wrap py-2" x-show="!loading && !loadError">
                                        <div class="flex items-center gap-1">
                                            <button type="button" class="text-xs px-2 py-1 rounded-md" @click="activeTool = 'highlight'"
                                                    :style="{ border:'1px solid var(--border)', background: activeTool === 'highlight' ? 'var(--ds-blue-soft, #eff6ff)' : 'transparent', fontWeight: activeTool === 'highlight' ? '700' : '400' }">Highlight</button>
                                            <button type="button" class="text-xs px-2 py-1 rounded-md" @click="activeTool = 'note'"
                                                    :style="{ border:'1px solid var(--border)', background: activeTool === 'note' ? 'var(--ds-blue-soft, #eff6ff)' : 'transparent', fontWeight: activeTool === 'note' ? '700' : '400' }">Note</button>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <template x-for="c in colors" :key="c.key">
                                                <button type="button" :title="c.label" @click="activeColor = c.key"
                                                        :style="{ width:'16px', height:'16px', borderRadius:'9999px', background: c.css, cursor:'pointer', border: activeColor === c.key ? '2px solid var(--text-primary,#111)' : '1px solid rgba(0,0,0,0.2)' }"></button>
                                            </template>
                                        </div>
                                        <div class="flex items-center gap-1" x-show="activeTool === 'highlight'">
                                            <template x-for="s in strokeSizes" :key="s.key">
                                                <button type="button" class="text-xs px-2 py-1 rounded-md" :title="s.label" @click="setStrokeSize(s.key)"
                                                        :style="{ border:'1px solid var(--border)', background: strokeSizeKey === s.key ? 'var(--ds-blue-soft, #eff6ff)' : 'transparent', fontWeight: strokeSizeKey === s.key ? '700' : '400' }" x-text="s.label"></button>
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
                                        <button type="button" class="corex-btn-primary text-xs"
                                                :disabled="applying || pagesLoading" :title="pagesLoading ? 'Still loading the rest of this document' : ''"
                                                x-text="applying ? 'Saving…' : (pagesLoading ? 'Loading…' : 'Save')" @click="applyHighlights()"></button>
                                        <button type="button" class="corex-btn-outline text-xs" @click="closeHighlighter()">Done</button>
                                    </div>

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
                                                        <svg class="absolute inset-0" style="pointer-events:none; width:100%; height:100%;"
                                                             x-html="strokesSvgFor(page.index)"></svg>
                                                        <template x-for="(mark, mi) in strokesFor(page.index)" :key="'r'+mi">
                                                            <button type="button" title="Remove this mark"
                                                                    @pointerdown.stop.prevent="removeMark(page.index, mi, 'highlight')"
                                                                    :style="{ position:'absolute', left:(mark.points[0].x-9)+'px', top:(mark.points[0].y-9)+'px', width:'18px', height:'18px', borderRadius:'9999px', background:'#475569', color:'#fff', fontSize:'12px', lineHeight:'16px', textAlign:'center', border:'1px solid #fff', padding:'0', pointerEvents:'auto', cursor:'pointer' }">&times;</button>
                                                        </template>
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
            </div>

            @if($auditLog->isNotEmpty())
                <div class="rounded-md p-4" style="background: var(--surface); border: 1px solid var(--border);">
                    <h2 class="text-sm font-semibold mb-3" style="color: var(--text-primary);">Audit Trail</h2>
                    <div class="space-y-2 text-xs">
                        @foreach($auditLog as $entry)
                            <div class="pb-2" style="border-bottom: 1px solid var(--border);">
                                <div style="color: var(--text-primary);">
                                    <strong>{{ $entry->actor_label ?? ($entry->user->name ?? 'System') }}</strong>
                                    &mdash; {{ $entry->human_summary ?? $entry->event_type }}
                                    @if($entry->is_override)
                                        <span class="ds-badge ds-badge-warning" title="This changed an existing decision.">OVERRIDE</span>
                                    @endif
                                    <span style="color: var(--text-muted);">({{ $entry->created_at->format('d M Y H:i') }})</span>
                                </div>
                                @if($entry->reason)
                                    <div class="mt-0.5 whitespace-pre-wrap" style="color: var(--text-secondary);">{{ $entry->reason }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div class="rah-auth-aside rounded-md p-4" style="background: var(--surface); border: 1px solid var(--border);">
            <h2 class="text-sm font-semibold mb-1" style="color: var(--text-primary);">Decision</h2>
            <p class="text-xs mb-3" style="color: var(--text-muted);">
                @if($alreadyDecided && $canOverride)
                    Acting below overrides the existing decision — a reason is required.
                @else
                    Approve, decline, or ask the agent for more information.
                @endif
            </p>

            {{-- Approve --}}
            <div class="rounded-md p-3 mb-3" style="border: 1px solid var(--border);">
                <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Approve — monthly amount</label>
                {{-- Johan, 2026-09-08 — "hitting the . on an amount clears the
                     values". type="number" + x-model writes the browser's own
                     parsed .value back into the field; a lone trailing "."
                     doesn't parse as a number yet, so the write-back silently
                     drops it mid-type. text + inputmode="decimal" gives the
                     same numeric keyboard on mobile with none of that native
                     parsing interference — the raw typed string passes
                     through untouched; sanitizeNumericInput() on the server
                     is the only place that ever interprets it. --}}
                <input type="text" inputmode="decimal" x-model="approveAmount" class="corex-input text-sm w-full mb-2" placeholder="0.00">
                <textarea x-model="approveReason" rows="2" class="corex-input text-xs w-full mb-2" placeholder="{{ $alreadyDecided ? 'Reason for override (required)' : 'Notes (optional)' }}"></textarea>
                <form method="POST" action="{{ route('corex.rental-applications.authorisation.approve', $rentalApplication) }}" @submit="$refs.approveAmountField.value = approveAmount; $refs.approveReasonField.value = approveReason">
                    @csrf
                    <input type="hidden" name="approved_rental_amount" x-ref="approveAmountField">
                    <input type="hidden" name="reason" x-ref="approveReasonField">
                    <button type="submit" class="corex-btn-primary text-xs w-full" :disabled="!approveAmount">Approve</button>
                </form>
            </div>

            {{-- Decline --}}
            <div class="rounded-md p-3 mb-3" style="border: 1px solid var(--border);">
                <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Decline</label>
                <textarea x-model="declineReason" rows="2" class="corex-input text-xs w-full mb-2" placeholder="{{ $alreadyDecided ? 'Reason for override (required)' : 'Notes (optional)' }}"></textarea>
                <form method="POST" action="{{ route('corex.rental-applications.authorisation.decline', $rentalApplication) }}" @submit="$refs.declineReasonField.value = declineReason">
                    @csrf
                    <input type="hidden" name="reason" x-ref="declineReasonField">
                    <button type="submit" class="corex-btn-outline text-xs w-full" style="color: var(--ds-red, #dc2626); border-color: var(--ds-red, #dc2626);">Decline</button>
                </form>
            </div>

            {{-- Request more information (only while pending — not an override action) --}}
            @unless($alreadyDecided)
                <div class="rounded-md p-3" style="border: 1px solid var(--border);">
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Request more information</label>
                    <p class="text-xs mb-2" style="color: var(--text-muted);">Sends this back to the agent, not the applicant.</p>
                    <textarea x-model="moreInfoReason" rows="2" class="corex-input text-xs w-full mb-2" placeholder="What's missing? (required)"></textarea>
                    <form method="POST" action="{{ route('corex.rental-applications.authorisation.request-more-info', $rentalApplication) }}" @submit="$refs.moreInfoReasonField.value = moreInfoReason">
                        @csrf
                        <input type="hidden" name="reason" x-ref="moreInfoReasonField">
                        <button type="submit" class="corex-btn-outline text-xs w-full" :disabled="!moreInfoReason.trim()">Request More Information</button>
                    </form>
                </div>
            @endunless
        </div>
    </div>
</div>

@include('corex.rental-applications.partials.document-highlighter-script')

<script>
function rentalAuthorisationViewer({ initialMarkedUpDocIds }) {
    return {
        // Shared highlight/note viewer — see partials/document-highlighter-script.blade.php.
        ...rentalDocumentHighlighter({ initialMarkedUpDocIds }),

        // Decision panel fields — unchanged from before this screen grew a document viewer.
        approveAmount: '',
        approveReason: '',
        declineReason: '',
        moreInfoReason: '',

        init() {
            this.initHighlighterPrefs();
        },
    };
}
</script>
@endsection
