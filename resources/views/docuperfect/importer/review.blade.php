@extends('layouts.corex')

@section('corex-content')
<div class="-m-4 lg:-m-6 flex flex-col h-[calc(100vh-3.5rem)] lg:h-[calc(100vh-1rem)]" x-data="importReview()">

    {{-- FIXED TOP BAR --}}
    <div style="background:#0b2a4a;" class="sticky top-0 z-50 px-6 py-3 flex items-center justify-between flex-shrink-0">
        {{-- Left: Title + template name --}}
        <div class="flex-shrink-0">
            <h2 class="text-sm font-semibold text-white leading-tight">Document Importer</h2>
            <p class="text-xs text-white/50 mt-0.5" x-text="templateName"></p>
        </div>

        {{-- Centre: Progress --}}
        <div class="flex items-center gap-3">
            <template x-if="mode === 'assign'">
                <div class="flex items-center gap-2">
                    <div class="w-40 h-1.5 bg-white/20 rounded-full overflow-hidden">
                        <div class="h-full bg-emerald-400 rounded-full transition-all duration-300"
                             :style="'width:' + progressPercent + '%'"></div>
                    </div>
                    <span class="text-xs text-white/70" x-text="resolvedCount + ' of ' + totalFields + ' fields mapped'"></span>
                </div>
            </template>
            <template x-if="mode === 'preview'">
                <span class="text-xs text-emerald-300 font-medium flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                    All fields mapped &mdash; review below
                </span>
            </template>
        </div>

        {{-- Right: Cancel + Generate --}}
        <div class="flex items-center gap-2 flex-shrink-0">
            <a href="{{ route('docuperfect.import.index') }}"
               class="text-xs px-3 py-1.5 border border-white/30 text-white/70 rounded-lg hover:bg-white/10 hover:text-white transition-colors">
                Cancel
            </a>
            <button type="button" @click="submitForm()" x-show="mode === 'preview'" x-cloak
                    class="text-xs px-4 py-1.5 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors font-medium flex items-center gap-1">
                Generate Template
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
            </button>
        </div>
    </div>

    {{-- TWO-PANE AREA --}}
    <div class="flex flex-1 min-h-0 relative">

        {{-- LEFT PANE: Document Preview --}}
        <div class="w-1/2 overflow-y-auto bg-gray-100 dark:bg-gray-900" id="docPane">
            <div class="py-6 px-4">
                <div class="corex-document-page">
                    <div id="docEditor"
                         x-ref="editor"
                         class="corex-document-body"
                         style="min-height:400px;">
                        {!! $parsed['html'] !!}
                    </div>
                </div>
            </div>
        </div>

        {{-- RIGHT PANE: Entity Assignment --}}
        <div class="w-1/2 overflow-y-auto bg-gray-50 dark:bg-gray-800 border-l border-gray-200 dark:border-gray-700" id="rightPane">
            <form action="{{ route('docuperfect.import.generate') }}" method="POST" id="generateForm"
                  @submit="prepareSubmission()">
                @csrf
                <input type="hidden" name="edited_html" x-ref="editedHtmlInput">

                {{-- Hidden fields for form submission --}}
                <template x-for="(field, idx) in fields" :key="'hidden-'+idx">
                    <div>
                        <input type="hidden" :name="'fields[' + idx + '][key]'" :value="field.key">
                        <input type="hidden" :name="'fields[' + idx + '][label]'" :value="field.label">
                        <input type="hidden" :name="'fields[' + idx + '][pillar]'" :value="field.pillar">
                        <input type="hidden" :name="'fields[' + idx + '][assigned_to]'" :value="field.assigned_to">
                        <input type="hidden" :name="'fields[' + idx + '][field_type]'" :value="field.field_type">
                    </div>
                </template>

                {{-- ======================== --}}
                {{-- ASSIGN MODE (Round 1)    --}}
                {{-- ======================== --}}
                <div x-show="mode === 'assign'">
                    {{-- Sticky header --}}
                    <div class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 px-5 py-4">
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Map Your Fields</h3>
                        <p class="text-xs text-gray-400 mt-1">For each highlighted blank, tell us who or what goes there.</p>
                    </div>

                    {{-- Auto-resolved summary --}}
                    <div x-show="autoResolvedCount > 0" class="mx-5 mt-4 p-3 bg-emerald-50 dark:bg-emerald-900/20 rounded-lg border border-emerald-200 dark:border-emerald-800">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-emerald-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                            <p class="text-xs text-emerald-700 dark:text-emerald-300">
                                <span class="font-semibold" x-text="autoResolvedCount"></span> fields were automatically identified and mapped.
                            </p>
                        </div>
                    </div>

                    {{-- Unresolved field cards --}}
                    <div class="px-5 py-4 space-y-3">
                        <template x-for="(field, idx) in fields" :key="'assign-'+idx">
                            <div x-show="!field.resolved"
                                 :id="'assign-card-' + idx"
                                 class="border bg-white dark:bg-gray-700 rounded-xl p-4 shadow-sm transition-all duration-300"
                                 :class="highlightedIdx === idx
                                    ? 'border-blue-400 ring-2 ring-blue-300'
                                    : 'border-amber-200 dark:border-amber-700'">

                                {{-- Field number --}}
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-[10px] text-gray-400" x-text="'Field ' + (idx + 1) + ' of ' + totalFields"></span>
                                    <button type="button"
                                            @click="scrollToDocField(idx)"
                                            class="text-[10px] text-blue-500 hover:text-blue-700 flex items-center gap-0.5">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                                        Show in document
                                    </button>
                                </div>

                                {{-- Context snippet --}}
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-3 leading-relaxed"
                                   x-html="formatContext(field.context)"></p>

                                {{-- Question --}}
                                <p class="text-xs font-medium text-gray-600 dark:text-gray-300 mb-2.5">Who or what goes here?</p>

                                {{-- Entity buttons --}}
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" @click="assignEntity(idx, 'property')"
                                            class="entity-btn entity-btn-property">
                                        <span>&#x1F3E0;</span> Property
                                    </button>
                                    <button type="button" @click="assignEntity(idx, 'landlord')"
                                            class="entity-btn entity-btn-landlord">
                                        <span>&#x1F464;</span> Landlord
                                    </button>
                                    <button type="button" @click="assignEntity(idx, 'tenant')"
                                            class="entity-btn entity-btn-tenant">
                                        <span>&#x1F464;</span> Tenant
                                    </button>
                                    <button type="button" @click="assignEntity(idx, 'agent')"
                                            class="entity-btn entity-btn-agent">
                                        <span>&#x1F9D1;&#x200D;&#x1F4BC;</span> Agent
                                    </button>
                                    <button type="button" @click="assignEntity(idx, 'manual')"
                                            class="entity-btn entity-btn-manual">
                                        <span>&#x270F;&#xFE0F;</span> I'll fill this in
                                    </button>
                                </div>
                            </div>
                        </template>

                        {{-- Empty: all resolved --}}
                        <div x-show="unresolvedCount === 0" class="text-center py-12">
                            <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center">
                                <svg class="w-6 h-6 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                            </div>
                            <p class="text-sm text-emerald-600 dark:text-emerald-400 font-medium">All fields mapped!</p>
                            <p class="text-xs text-gray-400 mt-1">Switching to preview&hellip;</p>
                        </div>
                    </div>
                </div>

                {{-- ======================== --}}
                {{-- PREVIEW MODE             --}}
                {{-- ======================== --}}
                <div x-show="mode === 'preview'" x-cloak>
                    {{-- Sticky header --}}
                    <div class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 px-5 py-4">
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Review Mapping</h3>
                        <p class="text-xs text-gray-400 mt-1">Click any coloured chip in the document to change it.</p>
                    </div>

                    {{-- Template Name --}}
                    <div class="px-5 pt-4 pb-3">
                        <label class="text-xs font-medium text-gray-500 dark:text-gray-400">Template Name</label>
                        <input type="text" name="template_name" x-model="templateName"
                               class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white mt-1"
                               required>
                    </div>

                    {{-- Entity groups --}}
                    <div class="px-5 py-3 space-y-5">
                        <template x-for="group in entityGroups" :key="group.entity">
                            <div x-show="group.fields.length > 0">
                                {{-- Group header --}}
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="entity-chip text-xs"
                                          :class="'entity-chip-' + group.entity"
                                          x-text="group.icon + ' ' + group.label"></span>
                                    <span class="text-[10px] text-gray-400"
                                          x-text="group.fields.length + (group.fields.length === 1 ? ' field' : ' fields')"></span>
                                </div>

                                {{-- Fields in this group --}}
                                <div class="space-y-0.5 ml-1">
                                    <template x-for="f in group.fields" :key="'preview-'+f.idx">
                                        <div class="flex items-center justify-between text-xs py-1.5 px-3 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer group"
                                             @click="scrollToDocField(f.idx)">
                                            <span class="text-gray-600 dark:text-gray-300" x-text="f.resolvedLabel"></span>
                                            <button type="button"
                                                    @click.stop="startReassign(f.idx)"
                                                    class="text-[10px] text-blue-500 hover:text-blue-700 opacity-0 group-hover:opacity-100 transition-opacity">
                                                Change
                                            </button>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>

                    {{-- Generate button (sticky bottom) --}}
                    <div class="px-5 py-4 border-t border-gray-200 dark:border-gray-700 sticky bottom-0 bg-gray-50 dark:bg-gray-800">
                        <button type="submit"
                                class="w-full py-2.5 bg-emerald-600 text-white text-sm font-medium rounded-lg hover:bg-emerald-700 transition-colors flex items-center justify-center gap-2">
                            Generate Template
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        {{-- POPOVER (appears on chip click in preview mode) --}}
        <div x-show="popover.show" x-cloak
             @click.outside="popover.show = false"
             @keydown.escape.window="popover.show = false"
             class="fixed z-[100] bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-xl p-4 w-56"
             :style="'top:' + popover.y + 'px;left:' + popover.x + 'px'">
            <p class="text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Is this wrong?</p>
            <p class="text-[10px] text-gray-400 mb-3 truncate"
               x-text="popover.fieldIdx !== null && fields[popover.fieldIdx] ? fields[popover.fieldIdx].resolvedLabel : ''"></p>
            <div class="space-y-1">
                <button type="button"
                        @click="startReassign(popover.fieldIdx); popover.show = false"
                        class="w-full text-left text-xs px-2.5 py-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 flex items-center gap-2">
                    <span class="text-sm">&#x1F504;</span> Change entity
                </button>
                <button type="button"
                        @click="assignEntity(popover.fieldIdx, 'manual'); popover.show = false"
                        class="w-full text-left text-xs px-2.5 py-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 flex items-center gap-2">
                    <span class="text-sm">&#x270F;&#xFE0F;</span> This should be manual
                </button>
                <button type="button"
                        @click="popover.show = false"
                        class="w-full text-left text-xs px-2.5 py-1.5 rounded-lg hover:bg-emerald-50 dark:hover:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400 font-medium flex items-center gap-2">
                    <span class="text-sm">&#x2713;</span> Looks right
                </button>
            </div>
        </div>

        {{-- REASSIGN MODAL --}}
        <div x-show="reassignIdx !== null" x-cloak
             class="fixed inset-0 z-[90] flex items-center justify-center bg-black/30"
             @keydown.escape.window="reassignIdx = null">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl p-6 w-80 max-w-[90vw]"
                 @click.outside="reassignIdx = null">
                <p class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Change Entity</p>
                <p class="text-xs text-gray-400 mb-4 leading-relaxed"
                   x-html="reassignIdx !== null && fields[reassignIdx] ? formatContext(fields[reassignIdx].context) : ''"></p>
                <div class="flex flex-wrap gap-2 mb-4">
                    <button type="button" @click="assignEntity(reassignIdx, 'property'); reassignIdx = null"
                            class="entity-btn entity-btn-property"><span>&#x1F3E0;</span> Property</button>
                    <button type="button" @click="assignEntity(reassignIdx, 'landlord'); reassignIdx = null"
                            class="entity-btn entity-btn-landlord"><span>&#x1F464;</span> Landlord</button>
                    <button type="button" @click="assignEntity(reassignIdx, 'tenant'); reassignIdx = null"
                            class="entity-btn entity-btn-tenant"><span>&#x1F464;</span> Tenant</button>
                    <button type="button" @click="assignEntity(reassignIdx, 'agent'); reassignIdx = null"
                            class="entity-btn entity-btn-agent"><span>&#x1F9D1;&#x200D;&#x1F4BC;</span> Agent</button>
                    <button type="button" @click="assignEntity(reassignIdx, 'manual'); reassignIdx = null"
                            class="entity-btn entity-btn-manual"><span>&#x270F;&#xFE0F;</span> Manual</button>
                </div>
                <button type="button" @click="reassignIdx = null"
                        class="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">Cancel</button>
            </div>
        </div>
    </div>
</div>

{{-- ============================================
   STYLES
   ============================================ --}}
<style>
/* ---- Document page container ---- */
.corex-document-page {
    max-width: 210mm;
    margin: 0 auto;
    background: #fff;
    padding: 20mm 25mm;
    box-shadow: 0 0 12px rgba(0,0,0,0.12);
    min-height: 297mm;
    position: relative;
    font-family: 'Georgia', 'Times New Roman', serif;
}

.corex-document-body {
    font-size: 10pt;
    line-height: 1.65;
    color: #1a1a1a;
    text-align: justify;
    min-height: 400px;
}

/* Headings */
.corex-section-heading { font-size: 10.5pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.04em; text-align: left; margin-top: 14pt; margin-bottom: 4pt; color: #0b2a4a; }
.corex-heading, h1, h2, h3, h4 { font-size: 11pt; font-weight: bold; text-align: left; margin-top: 14pt; margin-bottom: 4pt; color: #0b2a4a; font-family: 'Georgia', serif; }

/* Paragraphs & clauses */
.corex-para, .corex-document-body p { margin-bottom: 6pt; text-align: justify; orphans: 3; widows: 3; }
.corex-clause { margin-bottom: 6pt; text-align: justify; }
.corex-sub-clause { margin-left: 8mm; margin-bottom: 5pt; text-align: justify; }
.corex-sub-sub-clause { margin-left: 16mm; margin-bottom: 5pt; text-align: justify; }

/* Lists */
.corex-list { margin-left: 8mm; margin-bottom: 6pt; }
.corex-list li { margin-bottom: 3pt; text-align: justify; }

/* Input rows */
.corex-input-row { display: flex; align-items: baseline; gap: 8pt; margin-bottom: 5pt; border-bottom: 0.5pt solid #ccc; padding-bottom: 3pt; }

/* Tables */
.corex-table { width: 100%; border-collapse: collapse; margin-bottom: 8pt; font-size: 9.5pt; }
.corex-table td, .corex-table th { padding: 4pt 6pt; border: 0.5pt solid #ccc; text-align: left; vertical-align: top; }
.corex-table th { background: #f0f4f8; font-weight: bold; color: #0b2a4a; }

/* Signature block */
.corex-signature-block { margin-top: 20pt; padding-top: 12pt; border-top: 1pt solid #ccc; page-break-inside: avoid; }
.corex-signature-label { font-size: 10.5pt; font-weight: bold; text-transform: uppercase; color: #0b2a4a; margin-bottom: 16pt; letter-spacing: 0.04em; }
.corex-signature-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20pt; margin-top: 8pt; }
.corex-signature-party { display: flex; flex-direction: column; gap: 20pt; }
.corex-signature-line { border-bottom: 1pt solid #333; height: 24pt; width: 100%; }
.corex-signature-name { font-size: 9pt; color: #555; margin-top: -16pt; text-align: center; }

/* Mammoth HTML formatting */
#docEditor p { margin: 0.3em 0; }
#docEditor table { width: 100%; border-collapse: collapse; }
#docEditor td, #docEditor th { padding: 4px 8px; border: 1px solid #ddd; }
#docEditor u { text-decoration: underline; }
#docEditor strong { font-weight: bold; }
#docEditor h1 { font-size: 1.2em; font-weight: bold; margin: 0.5em 0; }
#docEditor h2 { font-size: 1.1em; font-weight: bold; margin: 0.4em 0; }
#docEditor h3 { font-size: 1.05em; font-weight: bold; margin: 0.3em 0; }
#docEditor img { max-width: 100%; height: auto; }

/* ---- Field chips in document ---- */
#docEditor .field-blank {
    display: inline-block;
    border-radius: 12px;
    padding: 1px 8px;
    font-size: 8.5pt;
    font-weight: 500;
    cursor: pointer;
    line-height: 1.5;
    vertical-align: baseline;
    white-space: nowrap;
    transition: all 0.2s ease;
}

/* Entity colours for chips */
#docEditor .field-blank[data-entity="property"]   { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
#docEditor .field-blank[data-entity="landlord"]    { background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; }
#docEditor .field-blank[data-entity="tenant"]      { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
#docEditor .field-blank[data-entity="agent"]       { background: #ede9fe; color: #5b21b6; border: 1px solid #a78bfa; }
#docEditor .field-blank[data-entity="manual"]      { background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; }
#docEditor .field-blank[data-entity="unassigned"]  { background: #fef9c3; color: #854d0e; border: 1.5px solid #facc15; font-weight: 600; }

/* Hover glow on chips */
#docEditor .field-blank:hover { filter: brightness(0.95); box-shadow: 0 0 0 2px rgba(59,130,246,0.3); }

/* Pulse animation for scroll-to */
@keyframes fieldPulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(59,130,246,0.4); }
    50% { box-shadow: 0 0 0 6px rgba(59,130,246,0); }
}
#docEditor .field-blank.field-pulse { animation: fieldPulse 0.6s ease 2; }

/* ---- Entity buttons (right pane) ---- */
.entity-btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 500;
    border: 1.5px solid transparent;
    cursor: pointer;
    transition: all 0.15s ease;
}
.entity-btn:hover { transform: translateY(-1px); box-shadow: 0 2px 8px rgba(0,0,0,0.1); }

.entity-btn-property { background: #ecfdf5; color: #065f46; border-color: #6ee7b7; }
.entity-btn-property:hover { background: #d1fae5; }

.entity-btn-landlord { background: #eff6ff; color: #1e40af; border-color: #93c5fd; }
.entity-btn-landlord:hover { background: #dbeafe; }

.entity-btn-tenant { background: #fffbeb; color: #92400e; border-color: #fcd34d; }
.entity-btn-tenant:hover { background: #fef3c7; }

.entity-btn-agent { background: #f5f3ff; color: #5b21b6; border-color: #a78bfa; }
.entity-btn-agent:hover { background: #ede9fe; }

.entity-btn-manual { background: #f9fafb; color: #374151; border-color: #d1d5db; }
.entity-btn-manual:hover { background: #f3f4f6; }

/* ---- Entity chips (preview summary) ---- */
.entity-chip {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    padding: 2px 8px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 11px;
}
.entity-chip-property { background: #d1fae5; color: #065f46; }
.entity-chip-landlord { background: #dbeafe; color: #1e40af; }
.entity-chip-tenant   { background: #fef3c7; color: #92400e; }
.entity-chip-agent    { background: #ede9fe; color: #5b21b6; }
.entity-chip-manual   { background: #f3f4f6; color: #374151; }

/* Print */
@media print { .corex-document-page { box-shadow: none; padding: 15mm 20mm; } }

/* Disable parent scroll */
#appScroll:has(> div > .sticky) { overflow: hidden !important; padding: 0 !important; }
</style>

{{-- ============================================
   ALPINE.JS COMPONENT
   ============================================ --}}
<script>
function importReview() {
    const serverFields = @json($fields);

    /* -------------------------------------------------------
       Entity resolution rules:
       Given an entity + context text, resolve to a specific
       pillar field key. Order matters — first match wins.
       ------------------------------------------------------- */
    const entityRules = {
        property: [
            { p: /erf|stand/i,                     key: 'property.erf_number',       label: 'Erf Number' },
            { p: /unit\s*(no|number)?/i,            key: 'property.unit_number',      label: 'Unit Number' },
            { p: /complex/i,                        key: 'property.complex_name',     label: 'Complex Name' },
            { p: /suburb/i,                         key: 'property.suburb',           label: 'Suburb' },
            { p: /city|town/i,                      key: 'property.city',             label: 'City' },
            { p: /municipal|account/i,              key: 'property.municipal_account',label: 'Municipal Account' },
            { p: /description/i,                    key: 'property.description',      label: 'Property Description' },
            { p: /address|property|premises|situated|known/i, key: 'property.address', label: 'Property Address' },
            { p: /.*/,                              key: 'property.address',          label: 'Property Detail' },
        ],
        landlord: [
            { p: /surname|last\s*name/i,            key: 'contact.lessor_surname',    label: 'Landlord Surname' },
            { p: /id|identity|passport|registration/i, key: 'contact.lessor_id',      label: 'Landlord ID' },
            { p: /email/i,                          key: 'contact.lessor_email',      label: 'Landlord Email' },
            { p: /tel|phone|cell|mobile/i,          key: 'contact.lessor_tel',        label: 'Landlord Telephone' },
            { p: /bank\s*name/i,                    key: 'deal.bank_name',            label: 'Bank Name' },
            { p: /account\s*hold/i,                 key: 'deal.account_holder',       label: 'Account Holder' },
            { p: /account\s*(no|num)/i,             key: 'deal.account_number',       label: 'Account Number' },
            { p: /branch/i,                         key: 'deal.branch_code',          label: 'Branch Code' },
            { p: /rental|rent|monthly/i,            key: 'deal.monthly_rental',       label: 'Monthly Rental' },
            { p: /deposit/i,                        key: 'deal.deposit',              label: 'Deposit Amount' },
            { p: /name/i,                           key: 'contact.lessor_name',       label: 'Landlord Name' },
            { p: /address/i,                        key: 'contact.lessor_address',    label: 'Landlord Address' },
            { p: /.*/,                              key: 'contact.lessor_name',       label: 'Landlord Detail' },
        ],
        tenant: [
            { p: /surname|last\s*name/i,            key: 'contact.lessee_surname',    label: 'Tenant Surname' },
            { p: /id|identity|passport|registration/i, key: 'contact.lessee_id',      label: 'Tenant ID' },
            { p: /email/i,                          key: 'contact.lessee_email',      label: 'Tenant Email' },
            { p: /tel|phone|cell|mobile/i,          key: 'contact.lessee_tel',        label: 'Tenant Telephone' },
            { p: /name/i,                           key: 'contact.lessee_name',       label: 'Tenant Name' },
            { p: /address/i,                        key: 'contact.lessee_address',    label: 'Tenant Address' },
            { p: /.*/,                              key: 'contact.lessee_name',       label: 'Tenant Detail' },
        ],
        agent: [
            { p: /commence|start\s*date|commencement/i, key: 'deal.lease_start',     label: 'Lease Start Date' },
            { p: /expir|end\s*date|termination/i,   key: 'deal.lease_end',            label: 'Lease End Date' },
            { p: /day\s*of/i,                       key: 'deal.signed_day',           label: 'Signed Day' },
            { p: /commission/i,                     key: 'deal.commission',           label: 'Commission' },
            { p: /vat/i,                            key: 'deal.vat_amount',           label: 'VAT Amount' },
            { p: /ffc|fidelity/i,                   key: 'agent.ffc_number',          label: 'FFC Number' },
            { p: /agency/i,                         key: 'agent.agency_name',         label: 'Agency Name' },
            { p: /surname|last\s*name/i,            key: 'agent.agent_surname',       label: 'Agent Surname' },
            { p: /email/i,                          key: 'agent.agent_email',         label: 'Agent Email' },
            { p: /tel|phone|cell|mobile/i,          key: 'agent.agent_tel',           label: 'Agent Telephone' },
            { p: /name/i,                           key: 'agent.agent_name',          label: 'Agent Name' },
            { p: /date/i,                           key: 'deal.date',                 label: 'Date' },
            { p: /reference/i,                      key: 'deal.reference',            label: 'Reference' },
            { p: /.*/,                              key: 'agent.agent_name',          label: 'Agent Detail' },
        ],
        manual: [
            { p: /.*/, key: 'custom.manual_field', label: 'Manual Field' },
        ],
    };

    /* Pillar + assigned_to lookups per entity */
    const entityPartyMap = {
        property: { pillar: 'property', assigned_to: 'agent' },
        landlord: { pillar: 'contact',  assigned_to: 'lessor' },
        tenant:   { pillar: 'contact',  assigned_to: 'lessee' },
        agent:    { pillar: 'agent',    assigned_to: 'agent' },
        manual:   { pillar: 'custom',   assigned_to: 'agent' },
    };

    const entityMeta = {
        property: { icon: '\u{1F3E0}', label: 'Property' },
        landlord: { icon: '\u{1F464}', label: 'Landlord' },
        tenant:   { icon: '\u{1F464}', label: 'Tenant' },
        agent:    { icon: '\u{1F9D1}\u200D\u{1F4BC}', label: 'Agent' },
        manual:   { icon: '\u270F\uFE0F', label: 'Manual' },
    };

    /** Derive entity from server-side pillar + assigned_to */
    function deriveEntity(f) {
        if (f.pillar === 'property') return 'property';
        if (f.pillar === 'agent') return 'agent';
        if (f.assigned_to === 'lessor') return 'landlord';
        if (f.assigned_to === 'lessee') return 'tenant';
        if (f.pillar === 'deal') {
            if (f.assigned_to === 'agent') return 'agent';
            return 'agent';
        }
        if (f.pillar === 'custom') return 'manual';
        return 'manual';
    }

    /** Resolve specific field key from entity + context */
    function resolveField(entity, context) {
        const rules = entityRules[entity] || entityRules.manual;
        for (const rule of rules) {
            if (rule.p.test(context)) {
                const partyInfo = entityPartyMap[entity] || entityPartyMap.manual;
                // Use rule-specific pillar if the key implies it
                let pillar = partyInfo.pillar;
                if (rule.key.startsWith('deal.')) pillar = 'deal';
                else if (rule.key.startsWith('property.')) pillar = 'property';
                else if (rule.key.startsWith('agent.')) pillar = 'agent';
                else if (rule.key.startsWith('contact.')) pillar = 'contact';
                else if (rule.key.startsWith('custom.')) pillar = 'custom';
                return {
                    key: rule.key,
                    label: rule.label,
                    pillar: pillar,
                    assigned_to: partyInfo.assigned_to,
                };
            }
        }
        return { key: 'custom.field', label: 'Custom Field', pillar: 'custom', assigned_to: 'agent' };
    }

    /* Build initial fields with entity data */
    let manualCounter = 0;
    const processedFields = serverFields.map((f) => {
        const entity = deriveEntity(f);
        const isAutoResolved = f.confidence === 'high';

        return {
            context: f.context || '',
            confidence: f.confidence,
            label: f.suggested_label,
            key: f.suggested_key,
            pillar: f.pillar,
            assigned_to: f.assigned_to || 'agent',
            field_type: 'text',
            entity: isAutoResolved ? entity : null,
            resolved: isAutoResolved,
            resolvedLabel: isAutoResolved ? f.suggested_label : '',
        };
    });

    return {
        fields: processedFields,
        mode: 'assign',
        templateName: @json($templateName),
        popover: { show: false, fieldIdx: null, x: 0, y: 0 },
        reassignIdx: null,
        highlightedIdx: null,

        /* Computed */
        get totalFields() { return this.fields.length; },
        get resolvedCount() { return this.fields.filter(f => f.resolved).length; },
        get unresolvedCount() { return this.totalFields - this.resolvedCount; },
        get autoResolvedCount() { return this.fields.filter(f => f.resolved && f.confidence === 'high').length; },
        get progressPercent() { return this.totalFields > 0 ? Math.round((this.resolvedCount / this.totalFields) * 100) : 0; },

        get entityGroups() {
            const groups = {};
            for (const [key, meta] of Object.entries(entityMeta)) {
                groups[key] = { entity: key, icon: meta.icon, label: meta.label, fields: [] };
            }
            this.fields.forEach((f, idx) => {
                if (f.entity && groups[f.entity]) {
                    groups[f.entity].fields.push({ ...f, idx });
                }
            });
            return Object.values(groups);
        },

        /* Lifecycle */
        init() {
            this.updateDocSpans();
            const main = document.getElementById('appScroll');
            if (main) { main.style.overflow = 'hidden'; main.style.padding = '0'; }
            // If all auto-resolved, go straight to preview
            if (this.unresolvedCount === 0 && this.totalFields > 0) {
                this.$nextTick(() => { this.mode = 'preview'; });
            }
        },

        /* Entity assignment */
        assignEntity(idx, entity) {
            const field = this.fields[idx];
            if (!field) return;

            field.entity = entity;
            field.resolved = true;

            if (entity === 'manual') {
                manualCounter++;
                field.key = 'custom.manual_' + manualCounter;
                field.label = 'Manual Field';
                field.resolvedLabel = 'Manual Field';
                field.pillar = 'custom';
                field.assigned_to = 'agent';
            } else {
                const resolved = resolveField(entity, field.context);
                field.key = resolved.key;
                field.label = resolved.label;
                field.resolvedLabel = resolved.label;
                field.pillar = resolved.pillar;
                field.assigned_to = resolved.assigned_to;
            }

            this.deduplicateKeys();
            this.updateDocSpans();

            // Auto-switch to preview when all resolved
            if (this.unresolvedCount === 0 && this.mode === 'assign') {
                setTimeout(() => { this.mode = 'preview'; }, 500);
            }
        },

        deduplicateKeys() {
            const counts = {};
            this.fields.forEach(f => {
                const baseKey = f.key.replace(/_\d+$/, '');
                if (!counts[baseKey]) counts[baseKey] = [];
                counts[baseKey].push(f);
            });
            for (const [baseKey, group] of Object.entries(counts)) {
                if (group.length > 1) {
                    group.forEach((f, i) => {
                        if (i > 0) {
                            f.key = baseKey + '_' + (i + 1);
                            // Keep base label, append number only if duplicate
                            const baseLabel = f.resolvedLabel.replace(/\s+\d+$/, '');
                            f.resolvedLabel = baseLabel + ' ' + (i + 1);
                            f.label = f.resolvedLabel;
                        }
                    });
                }
            }
        },

        /* Update document spans to reflect entity state */
        updateDocSpans() {
            const editor = this.$refs.editor;
            if (!editor) return;

            editor.querySelectorAll('.field-blank').forEach(span => {
                const idx = parseInt(span.getAttribute('data-field-index'));
                span.setAttribute('contenteditable', 'false');
                span.style.cursor = 'pointer';

                if (idx < 0 || isNaN(idx)) {
                    // Unmatched blank — subtle yellow pill
                    span.setAttribute('data-entity', 'unassigned');
                    span.textContent = '?';
                    span.onclick = null;
                    return;
                }

                const field = this.fields[idx];
                if (!field) return;

                if (field.resolved && field.entity) {
                    span.setAttribute('data-entity', field.entity);
                    span.textContent = field.resolvedLabel;
                } else {
                    span.setAttribute('data-entity', 'unassigned');
                    span.textContent = '?';
                }

                span.onclick = (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    if (this.mode === 'assign' && !field.resolved) {
                        this.scrollToAssignCard(idx);
                    } else if (this.mode === 'preview' && field.resolved) {
                        this.showPopover(idx, e);
                    }
                };
            });
        },

        /* Navigation */
        scrollToAssignCard(idx) {
            this.highlightedIdx = idx;
            const card = document.getElementById('assign-card-' + idx);
            if (card) card.scrollIntoView({ behavior: 'smooth', block: 'center' });
            setTimeout(() => { this.highlightedIdx = null; }, 2000);
        },

        scrollToDocField(idx) {
            const span = this.$refs.editor.querySelector('[data-field-index="' + idx + '"]');
            if (span) {
                span.scrollIntoView({ behavior: 'smooth', block: 'center' });
                span.classList.add('field-pulse');
                setTimeout(() => span.classList.remove('field-pulse'), 1500);
            }
        },

        /* Popover */
        showPopover(idx, event) {
            const rect = event.target.getBoundingClientRect();
            this.popover = {
                show: true,
                fieldIdx: idx,
                x: Math.min(rect.left, window.innerWidth - 240),
                y: rect.bottom + 8,
            };
        },

        /* Reassign */
        startReassign(idx) {
            this.popover.show = false;
            this.reassignIdx = idx;
        },

        /* Context formatting */
        formatContext(context) {
            if (!context) return '';
            const escaped = context.replace(/</g, '&lt;').replace(/>/g, '&gt;');
            return escaped.replace(
                /\[___\]/g,
                '<span class="inline-block px-2 py-0.5 mx-1 bg-amber-200 dark:bg-amber-700 rounded text-amber-800 dark:text-amber-200 text-[10px] font-mono font-bold">___</span>'
            );
        },

        /* Form submission */
        prepareSubmission() {
            this.$refs.editedHtmlInput.value = this.$refs.editor.innerHTML;
        },

        submitForm() {
            this.prepareSubmission();
            document.getElementById('generateForm').submit();
        },
    };
}
</script>
@endsection
