@extends('layouts.corex')

@section('corex-content')
<div class="-m-4 lg:-m-6 flex flex-col h-[calc(100vh-3.5rem)] lg:h-[calc(100vh-1rem)]" x-data="importReview()">

    {{-- FIXED TOP BAR --}}
    <div style="background:#0b2a4a;" class="sticky top-0 z-50 px-6 py-3 flex items-center justify-between flex-shrink-0">
        <div class="flex-shrink-0">
            <h2 class="text-sm font-semibold text-white leading-tight">Document Importer</h2>
            <p class="text-xs text-white/50 mt-0.5" x-text="templateName"></p>
        </div>

        <div class="flex items-center gap-3">
            <div class="w-40 h-1.5 bg-white/20 rounded-full overflow-hidden">
                <div class="h-full bg-emerald-400 rounded-full transition-all duration-300"
                     :style="'width:' + progressPercent + '%'"></div>
            </div>
            <div class="flex items-center gap-2 text-[10px]">
                <span class="text-emerald-400" x-show="confirmedCount > 0" x-text="'&#10003; ' + confirmedCount + ' confirmed'"></span>
                <span class="text-amber-400 cursor-pointer hover:underline" x-show="needsReviewCount > 0" x-text="'&#9888; ' + needsReviewCount + ' need review'" @click="goToBlank(blanks.findIndex(b => b.suggested && !b.assigned))"></span>
                <span class="text-white/50" x-show="unassignedCount > 0" x-text="'? ' + unassignedCount + ' unassigned'"></span>
            </div>
        </div>

        <div class="flex items-center gap-2 flex-shrink-0">
            <a href="{{ route('docuperfect.import.index') }}"
               class="text-xs px-3 py-1.5 border border-white/30 text-white/70 rounded-lg hover:bg-white/10 hover:text-white transition-colors">
                Cancel
            </a>
        </div>
    </div>

    {{-- TWO-PANE AREA --}}
    <div class="flex flex-1 min-h-0 relative">

        {{-- LEFT PANE: Document Preview --}}
        <div class="w-1/2 overflow-y-auto bg-gray-100 dark:bg-gray-900" id="docPane">
            <div class="py-6 px-4">
                <div class="corex-document-page">
                    <div id="docEditor" x-ref="editor" class="corex-document-body" style="min-height:400px;">
                        {!! $parsed['html'] !!}
                    </div>
                </div>
            </div>
        </div>

        {{-- RIGHT PANE: Field Assignment --}}
        <div class="w-1/2 overflow-y-auto bg-gray-50 dark:bg-gray-800 border-l border-gray-200 dark:border-gray-700" id="rightPane">

            {{-- VALIDATION ERRORS --}}
            @if($errors->any())
            <div class="bg-red-900/50 border border-red-500 text-red-200 rounded px-4 py-3 mb-4 text-sm mt-14">
                <strong>Generation failed:</strong>
                <ul class="mt-1 list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            {{-- RESTORE BANNER --}}
            <div x-show="showRestoreBanner" x-transition class="mt-14 bg-amber-50 dark:bg-amber-900/30 border-b border-amber-200 dark:border-amber-700 px-5 py-2.5 flex items-center justify-between">
                <p class="text-xs text-amber-800 dark:text-amber-300">Previous progress found.</p>
                <div class="flex items-center gap-2">
                    <button type="button" @click="discardProgress()" class="text-xs px-3 py-1 border border-amber-300 dark:border-amber-600 text-amber-700 dark:text-amber-300 rounded-lg hover:bg-amber-100 dark:hover:bg-amber-800 transition-colors">Start fresh</button>
                    <button type="button" @click="restoreProgress()" class="text-xs px-3 py-1 bg-amber-600 text-white rounded-lg hover:bg-amber-700 transition-colors font-medium">Continue</button>
                </div>
            </div>

            <form action="{{ route('docuperfect.import.generate') }}" method="POST" id="generateForm"
                  @submit="prepareSubmission()">
                @csrf
                <input type="hidden" name="edited_html" x-ref="editedHtmlInput">
                <input type="hidden" name="draft_id" value="{{ $draftId }}">

                {{-- Hidden fields for form submission (name is null when unassigned so inputs are not submitted) --}}
                <template x-for="(blank, idx) in blanks" :key="'hidden-'+idx">
                    <div>
                        <input type="hidden" :name="blank.assigned ? 'fields[' + idx + '][key]' : null" :value="blank.fieldKey">
                        <input type="hidden" :name="blank.assigned ? 'fields[' + idx + '][label]' : null" :value="blank.fieldLabel">
                        <input type="hidden" :name="blank.assigned ? 'fields[' + idx + '][pillar]' : null" :value="blank.pillar">
                        <input type="hidden" :name="blank.assigned ? 'fields[' + idx + '][assigned_to]' : null" :value="blank.assignedTo">
                        <input type="hidden" :name="blank.assigned ? 'fields[' + idx + '][field_type]' : null" :value="blank.fieldType">
                        <input type="hidden" :name="blank.assigned ? 'fields[' + idx + '][correction_reason]' : null" :value="blank.correctionReason || ''">
                    </div>
                </template>

                {{-- Signing Parties --}}
                <div class="px-5 pt-4 pb-3 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-white mb-3">Signing Parties</h3>
                    <div class="space-y-2">
                        @foreach(['lessor' => 'Lessor / Landlord', 'lessee' => 'Lessee / Tenant', 'agent' => 'Agent'] as $key => $label)
                        <label class="flex items-center gap-2 text-sm text-gray-300 cursor-pointer">
                            <input type="checkbox"
                                   name="signing_parties[]"
                                   value="{{ $key }}"
                                   checked
                                   class="rounded border-gray-600 bg-gray-700 text-teal-500 focus:ring-teal-500">
                            {{ $label }}
                        </label>
                        @endforeach
                    </div>
                </div>

                {{-- Template name --}}
                <div class="px-5 pt-4 pb-3 border-b border-gray-200 dark:border-gray-700">
                    <label class="text-xs font-medium text-gray-500 dark:text-gray-400">Template Name</label>
                    <input type="text" name="template_name" x-model="templateName"
                           class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white mt-1"
                           required>
                </div>

                {{-- ALL ASSIGNED STATE — generate button + still allow editing --}}
                <div x-show="allAssigned && !editingBlank && totalBlanks > 0" x-transition class="px-5 py-8 text-center">
                    <div class="mb-4">
                        <svg class="w-12 h-12 mx-auto text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                        </svg>
                    </div>
                    <p class="text-lg font-semibold text-emerald-700 dark:text-emerald-400 mb-1">All fields mapped</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-6">
                        <span x-text="totalBlanks"></span> fields assigned. Click any chip in the document to reassign.
                    </p>
                    <button type="submit"
                            class="w-full py-3 bg-emerald-600 text-white text-sm font-semibold rounded-xl hover:bg-emerald-700 transition-colors flex items-center justify-center gap-2 shadow-lg">
                        Generate Template
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                    </button>
                </div>

                {{-- ACTIVE CARD (one at a time) --}}
                <div x-show="!allAssigned || editingBlank || totalBlanks === 0" class="px-5 py-4">

                    {{-- Navigation --}}
                    <div class="flex items-center justify-between mb-4">
                        <button type="button" @click="prevBlank()"
                                class="text-xs px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors flex items-center gap-1 text-gray-600 dark:text-gray-300"
                                :disabled="totalBlanks === 0">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
                            Previous
                        </button>
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400"
                              x-text="totalBlanks > 0 ? 'Blank ' + (activeIndex + 1) + ' of ' + totalBlanks : 'No blanks detected'"></span>
                        <button type="button" @click="nextBlank()"
                                class="text-xs px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors flex items-center gap-1 text-gray-600 dark:text-gray-300"
                                :disabled="totalBlanks === 0">
                            Next
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                        </button>
                    </div>

                    {{-- Card for active blank --}}
                    <template x-if="totalBlanks > 0 && blanks[activeIndex]">
                        <div class="border-2 rounded-xl overflow-hidden transition-all duration-200"
                             :class="blanks[activeIndex].assigned
                                ? 'border-emerald-300 dark:border-emerald-700 bg-white dark:bg-gray-700'
                                : 'border-teal-400 dark:border-teal-600 bg-white dark:bg-gray-700'">

                            {{-- Card header --}}
                            <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-600"
                                 :class="blanks[activeIndex].assigned ? 'bg-emerald-50 dark:bg-emerald-900/20' : (blanks[activeIndex].suggested ? 'bg-amber-50 dark:bg-amber-900/20' : 'bg-teal-50 dark:bg-teal-900/20')">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <h4 class="text-sm font-bold"
                                            :class="blanks[activeIndex].assigned ? 'text-emerald-700 dark:text-emerald-400' : (blanks[activeIndex].suggested ? 'text-amber-700 dark:text-amber-400' : 'text-teal-700 dark:text-teal-400')"
                                            x-text="'Blank [' + (activeIndex + 1) + ']'"></h4>
                                        <template x-if="blanks[activeIndex].assigned">
                                            <div class="flex items-center gap-0.5">
                                                <button type="button" @click="shiftUp(activeIndex)" :disabled="activeIndex === 0"
                                                        class="w-5 h-5 flex items-center justify-center rounded text-gray-400 hover:text-gray-700 hover:bg-gray-200 dark:hover:text-gray-200 dark:hover:bg-gray-600 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
                                                        title="Shift assignment up">
                                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5"/></svg>
                                                </button>
                                                <button type="button" @click="shiftDown(activeIndex)" :disabled="activeIndex >= totalBlanks - 1"
                                                        class="w-5 h-5 flex items-center justify-center rounded text-gray-400 hover:text-gray-700 hover:bg-gray-200 dark:hover:text-gray-200 dark:hover:bg-gray-600 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
                                                        title="Shift assignment down">
                                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                                                </button>
                                            </div>
                                        </template>
                                    </div>
                                    <template x-if="blanks[activeIndex].assigned">
                                        <span class="text-[10px] px-2 py-0.5 rounded-full font-medium"
                                              :class="sectionChipClass(blanks[activeIndex].section)"
                                              x-text="blanks[activeIndex].fieldLabel"></span>
                                    </template>
                                    <template x-if="blanks[activeIndex].suggested && !blanks[activeIndex].assigned">
                                        <span class="text-[10px] px-2 py-0.5 rounded-full font-medium bg-amber-100 text-amber-800 border border-dashed border-amber-400"
                                              x-text="'AI suggests: ' + blanks[activeIndex].fieldLabel"></span>
                                    </template>
                                </div>
                                {{-- Context snippet --}}
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5 leading-relaxed font-mono"
                                   x-html="formatContext(blanks[activeIndex].context)"></p>

                                {{-- Confirm/reject for AI suggested fields --}}
                                <template x-if="blanks[activeIndex].suggested && !blanks[activeIndex].assigned">
                                    <div class="mt-2">
                                        <div class="flex items-center gap-2">
                                            <button type="button" @click="confirmSuggestion()"
                                                    class="text-xs px-3 py-1.5 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors font-medium">
                                                Confirm
                                            </button>
                                            <button type="button" @click="showCorrectionReason = !showCorrectionReason; rejectSuggestion()"
                                                    class="text-xs px-3 py-1.5 border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                                Change
                                            </button>
                                        </div>
                                        <div x-show="showCorrectionReason" x-transition class="mt-2">
                                            <label class="text-[10px] text-gray-400">Why is this wrong? (optional)</label>
                                            <input type="text" x-model="blanks[activeIndex].correctionReason"
                                                   placeholder="e.g. blank next to party label is always the name field"
                                                   class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white mt-0.5 py-1.5 px-2.5">
                                        </div>
                                    </div>
                                </template>

                                {{-- Quick actions for assigned blanks --}}
                                <template x-if="blanks[activeIndex].assigned">
                                    <div class="mt-2 flex items-center gap-2">
                                        <button type="button" @click="reassignCurrent()"
                                                class="text-[10px] px-2.5 py-1 border border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-400 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                            Reassign
                                        </button>
                                        <button type="button" @click="markAsManual()"
                                                class="text-[10px] px-2.5 py-1 border border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-400 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                            Mark as manual
                                        </button>
                                    </div>
                                </template>

                                {{-- Quick Mark as Manual for unassigned --}}
                                <template x-if="!blanks[activeIndex].assigned && !blanks[activeIndex].suggested">
                                    <div class="mt-2">
                                        <button type="button" @click="markAsManual()"
                                                class="text-xs px-3 py-1.5 border border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-400 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                            I'll fill this in manually
                                        </button>
                                    </div>
                                </template>

                                {{-- Shift assignment controls --}}
                                <div class="mt-3 flex items-center gap-2 border-t border-gray-200 dark:border-gray-700 pt-2">
                                    <button type="button" @click="shiftAssignmentsDown(activeIndex)"
                                            class="text-[10px] px-2 py-1 border border-dashed border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-400 rounded hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                        &#x2B07; Shift assignments down from here
                                    </button>
                                    <button type="button" @click="shiftAssignmentsUp(activeIndex)"
                                            class="text-[10px] px-2 py-1 border border-dashed border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-400 rounded hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                        &#x2B06; Shift assignments up from here
                                    </button>
                                </div>
                            </div>

                            {{-- Field Library --}}
                            <div class="px-4 py-3 space-y-4 max-h-[calc(100vh-22rem)] overflow-y-auto">

                                {{-- LANDLORD --}}
                                <div>
                                    <p class="text-xs font-semibold text-blue-700 dark:text-blue-400 mb-2 flex items-center gap-1">
                                        <span class="text-sm">&#x1F464;</span> LANDLORD
                                    </p>
                                    <div class="flex flex-wrap gap-1.5">
                                        <button type="button" @click="assignField('landlord', 'contact.full_name', 'Landlord Full Name', 'contact', 'lessor')"
                                                class="field-lib-btn field-lib-landlord" :class="isCurrentAssignment('landlord', 'contact.full_name') && 'ring-2 ring-blue-500'">Full Name</button>
                                        <button type="button" @click="assignField('landlord', 'contact.id_number', 'Landlord ID Number', 'contact', 'lessor')"
                                                class="field-lib-btn field-lib-landlord" :class="isCurrentAssignment('landlord', 'contact.id_number') && 'ring-2 ring-blue-500'">ID Number</button>
                                        <button type="button" @click="assignField('landlord', 'contact.address_residential', 'Landlord Address', 'contact', 'lessor')"
                                                class="field-lib-btn field-lib-landlord" :class="isCurrentAssignment('landlord', 'contact.address_residential') && 'ring-2 ring-blue-500'">Address</button>
                                        <button type="button" @click="assignField('landlord', 'contact.cell', 'Landlord Cell', 'contact', 'lessor')"
                                                class="field-lib-btn field-lib-landlord" :class="isCurrentAssignment('landlord', 'contact.cell') && 'ring-2 ring-blue-500'">Cell</button>
                                        <button type="button" @click="assignField('landlord', 'contact.email', 'Landlord Email', 'contact', 'lessor')"
                                                class="field-lib-btn field-lib-landlord" :class="isCurrentAssignment('landlord', 'contact.email') && 'ring-2 ring-blue-500'">Email</button>
                                        <button type="button" @click="showOther('landlord')"
                                                class="field-lib-btn field-lib-landlord opacity-70 italic">Other...</button>
                                    </div>
                                    <div x-show="otherSection === 'landlord'" x-transition class="mt-2 flex gap-2">
                                        <input type="text" x-model="otherText" placeholder="Describe this field..."
                                               @keydown.enter.prevent="submitOther('landlord', 'contact', 'lessor')"
                                               class="flex-1 text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white py-1.5 px-2.5">
                                        <button type="button" @click="submitOther('landlord', 'contact', 'lessor')"
                                                x-show="otherText.length > 0"
                                                class="text-xs px-3 py-1.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Set</button>
                                    </div>
                                </div>

                                {{-- TENANT --}}
                                <div>
                                    <p class="text-xs font-semibold text-amber-700 dark:text-amber-400 mb-2 flex items-center gap-1">
                                        <span class="text-sm">&#x1F464;</span> TENANT
                                    </p>
                                    <div class="flex flex-wrap gap-1.5">
                                        <button type="button" @click="assignField('tenant', 'contact.full_name', 'Tenant Full Name', 'contact', 'lessee')"
                                                class="field-lib-btn field-lib-tenant" :class="isCurrentAssignment('tenant', 'contact.full_name') && 'ring-2 ring-amber-500'">Full Name</button>
                                        <button type="button" @click="assignField('tenant', 'contact.id_number', 'Tenant ID Number', 'contact', 'lessee')"
                                                class="field-lib-btn field-lib-tenant" :class="isCurrentAssignment('tenant', 'contact.id_number') && 'ring-2 ring-amber-500'">ID Number</button>
                                        <button type="button" @click="assignField('tenant', 'contact.address_residential', 'Tenant Address', 'contact', 'lessee')"
                                                class="field-lib-btn field-lib-tenant" :class="isCurrentAssignment('tenant', 'contact.address_residential') && 'ring-2 ring-amber-500'">Address</button>
                                        <button type="button" @click="assignField('tenant', 'contact.cell', 'Tenant Cell', 'contact', 'lessee')"
                                                class="field-lib-btn field-lib-tenant" :class="isCurrentAssignment('tenant', 'contact.cell') && 'ring-2 ring-amber-500'">Cell</button>
                                        <button type="button" @click="assignField('tenant', 'contact.email', 'Tenant Email', 'contact', 'lessee')"
                                                class="field-lib-btn field-lib-tenant" :class="isCurrentAssignment('tenant', 'contact.email') && 'ring-2 ring-amber-500'">Email</button>
                                        <button type="button" @click="showOther('tenant')"
                                                class="field-lib-btn field-lib-tenant opacity-70 italic">Other...</button>
                                    </div>
                                    <div x-show="otherSection === 'tenant'" x-transition class="mt-2 flex gap-2">
                                        <input type="text" x-model="otherText" placeholder="Describe this field..."
                                               @keydown.enter.prevent="submitOther('tenant', 'contact', 'lessee')"
                                               class="flex-1 text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white py-1.5 px-2.5">
                                        <button type="button" @click="submitOther('tenant', 'contact', 'lessee')"
                                                x-show="otherText.length > 0"
                                                class="text-xs px-3 py-1.5 bg-amber-600 text-white rounded-lg hover:bg-amber-700">Set</button>
                                    </div>
                                </div>

                                {{-- PROPERTY --}}
                                <div>
                                    <p class="text-xs font-semibold text-emerald-700 dark:text-emerald-400 mb-2 flex items-center gap-1">
                                        <span class="text-sm">&#x1F3E0;</span> PROPERTY
                                    </p>
                                    <div class="flex flex-wrap gap-1.5">
                                        <button type="button" @click="assignField('property', 'property.address_full', 'Property Address', 'property', 'agent')"
                                                class="field-lib-btn field-lib-property" :class="isCurrentAssignment('property', 'property.address_full') && 'ring-2 ring-emerald-500'">Address</button>
                                        <button type="button" @click="assignField('property', 'property.erf_number', 'Erf Number', 'property', 'agent')"
                                                class="field-lib-btn field-lib-property" :class="isCurrentAssignment('property', 'property.erf_number') && 'ring-2 ring-emerald-500'">Erf Number</button>
                                        <button type="button" @click="assignField('property', 'deal.rental_amount', 'Rental Amount', 'deal', 'agent')"
                                                class="field-lib-btn field-lib-property" :class="isCurrentAssignment('property', 'deal.rental_amount') && 'ring-2 ring-emerald-500'">Rental Amount</button>
                                        <button type="button" @click="assignField('property', 'deal.deposit_amount', 'Deposit', 'deal', 'agent')"
                                                class="field-lib-btn field-lib-property" :class="isCurrentAssignment('property', 'deal.deposit_amount') && 'ring-2 ring-emerald-500'">Deposit</button>
                                        <button type="button" @click="assignField('property', 'deal.lease_start', 'Lease Start', 'deal', 'agent')"
                                                class="field-lib-btn field-lib-property" :class="isCurrentAssignment('property', 'deal.lease_start') && 'ring-2 ring-emerald-500'">Lease Start</button>
                                        <button type="button" @click="assignField('property', 'deal.lease_end', 'Lease End', 'deal', 'agent')"
                                                class="field-lib-btn field-lib-property" :class="isCurrentAssignment('property', 'deal.lease_end') && 'ring-2 ring-emerald-500'">Lease End</button>
                                        <button type="button" @click="showOther('property')"
                                                class="field-lib-btn field-lib-property opacity-70 italic">Other...</button>
                                    </div>
                                    <div x-show="otherSection === 'property'" x-transition class="mt-2 flex gap-2">
                                        <input type="text" x-model="otherText" placeholder="Describe this field..."
                                               @keydown.enter.prevent="submitOther('property', 'property', 'agent')"
                                               class="flex-1 text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white py-1.5 px-2.5">
                                        <button type="button" @click="submitOther('property', 'property', 'agent')"
                                                x-show="otherText.length > 0"
                                                class="text-xs px-3 py-1.5 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700">Set</button>
                                    </div>
                                </div>

                                {{-- AGENT --}}
                                <div>
                                    <p class="text-xs font-semibold text-purple-700 dark:text-purple-400 mb-2 flex items-center gap-1">
                                        <span class="text-sm">&#x1F9D1;</span> AGENT
                                    </p>
                                    <div class="flex flex-wrap gap-1.5">
                                        <button type="button" @click="assignField('agent', 'agent.full_name', 'Agent Full Name', 'agent', 'agent')"
                                                class="field-lib-btn field-lib-agent" :class="isCurrentAssignment('agent', 'agent.full_name') && 'ring-2 ring-purple-500'">Full Name</button>
                                        <button type="button" @click="assignField('agent', 'agent.ffc_number', 'Agent FFC Number', 'agent', 'agent')"
                                                class="field-lib-btn field-lib-agent" :class="isCurrentAssignment('agent', 'agent.ffc_number') && 'ring-2 ring-purple-500'">FFC Number</button>
                                        <button type="button" @click="assignField('agent', 'agent.cell', 'Agent Cell', 'agent', 'agent')"
                                                class="field-lib-btn field-lib-agent" :class="isCurrentAssignment('agent', 'agent.cell') && 'ring-2 ring-purple-500'">Cell</button>
                                        <button type="button" @click="showOther('agent')"
                                                class="field-lib-btn field-lib-agent opacity-70 italic">Other...</button>
                                    </div>
                                    <div x-show="otherSection === 'agent'" x-transition class="mt-2 flex gap-2">
                                        <input type="text" x-model="otherText" placeholder="Describe this field..."
                                               @keydown.enter.prevent="submitOther('agent', 'agent', 'agent')"
                                               class="flex-1 text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white py-1.5 px-2.5">
                                        <button type="button" @click="submitOther('agent', 'agent', 'agent')"
                                                x-show="otherText.length > 0"
                                                class="text-xs px-3 py-1.5 bg-purple-600 text-white rounded-lg hover:bg-purple-700">Set</button>
                                    </div>
                                </div>

                                {{-- MANUAL ENTRY --}}
                                <div>
                                    <p class="text-xs font-semibold text-gray-600 dark:text-gray-400 mb-2 flex items-center gap-1">
                                        <span class="text-sm">&#x270F;&#xFE0F;</span> MANUAL ENTRY
                                    </p>
                                    <div class="flex flex-wrap gap-1.5">
                                        <button type="button" @click="assignField('manual', 'custom.manual', 'Manual Entry', 'custom', 'agent')"
                                                class="field-lib-btn field-lib-manual" :class="isCurrentAssignment('manual', 'custom.manual') && 'ring-2 ring-gray-500'">I will type this myself</button>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </template>

                    {{-- Generate button when editing after all assigned --}}
                    <div x-show="allAssigned && editingBlank" x-transition class="mt-4">
                        <button type="submit"
                                class="w-full py-3 bg-emerald-600 text-white text-sm font-semibold rounded-xl hover:bg-emerald-700 transition-colors flex items-center justify-center gap-2 shadow-lg">
                            Generate Template
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                        </button>
                    </div>
                </div>
            </form>
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

/* ---- Numbered markers (unassigned) ---- */
#docEditor .field-blank {
    display: inline-block;
    border-radius: 4px;
    padding: 1px 6px;
    font-size: 9pt;
    font-weight: 700;
    cursor: pointer;
    line-height: 1.5;
    vertical-align: baseline;
    white-space: nowrap;
    transition: all 0.2s ease;
    background: #ccfbf1;
    color: #0d9488;
    border: 1.5px solid #5eead4;
}

/* Assigned chip colours (high confidence — solid border) */
#docEditor .field-blank.assigned-landlord { background: #dbeafe; color: #1e40af; border-color: #93c5fd; font-weight: 500; font-size: 8.5pt; border-radius: 12px; padding: 1px 8px; }
#docEditor .field-blank.assigned-tenant   { background: #fef3c7; color: #92400e; border-color: #fcd34d; font-weight: 500; font-size: 8.5pt; border-radius: 12px; padding: 1px 8px; }
#docEditor .field-blank.assigned-property { background: #d1fae5; color: #065f46; border-color: #6ee7b7; font-weight: 500; font-size: 8.5pt; border-radius: 12px; padding: 1px 8px; }
#docEditor .field-blank.assigned-agent    { background: #ede9fe; color: #5b21b6; border-color: #a78bfa; font-weight: 500; font-size: 8.5pt; border-radius: 12px; padding: 1px 8px; }
#docEditor .field-blank.assigned-manual   { background: #f3f4f6; color: #374151; border-color: #d1d5db; font-weight: 500; font-size: 8.5pt; border-radius: 12px; padding: 1px 8px; }

/* Suggested chip colours (medium confidence — dashed border) */
#docEditor .field-blank.suggested-landlord { background: #eff6ff; color: #3b82f6; border: 1.5px dashed #93c5fd; font-weight: 500; font-size: 8.5pt; border-radius: 12px; padding: 1px 8px; }
#docEditor .field-blank.suggested-tenant   { background: #fffbeb; color: #d97706; border: 1.5px dashed #fcd34d; font-weight: 500; font-size: 8.5pt; border-radius: 12px; padding: 1px 8px; }
#docEditor .field-blank.suggested-property { background: #ecfdf5; color: #10b981; border: 1.5px dashed #6ee7b7; font-weight: 500; font-size: 8.5pt; border-radius: 12px; padding: 1px 8px; }
#docEditor .field-blank.suggested-agent    { background: #f5f3ff; color: #8b5cf6; border: 1.5px dashed #a78bfa; font-weight: 500; font-size: 8.5pt; border-radius: 12px; padding: 1px 8px; }
#docEditor .field-blank.suggested-manual   { background: #f9fafb; color: #6b7280; border: 1.5px dashed #d1d5db; font-weight: 500; font-size: 8.5pt; border-radius: 12px; padding: 1px 8px; }

/* Hover */
#docEditor .field-blank:hover { filter: brightness(0.95); box-shadow: 0 0 0 2px rgba(13,148,136,0.3); }

/* Active glow */
#docEditor .field-blank.field-active {
    box-shadow: 0 0 0 3px rgba(13,148,136,0.5), 0 0 12px rgba(13,148,136,0.25);
    outline: 2px solid #14b8a6;
    outline-offset: 1px;
    z-index: 10;
    position: relative;
}

/* Pulse animation */
@keyframes fieldPulse {
    0% { box-shadow: 0 0 0 0 rgba(13,148,136,0.6); }
    50% { box-shadow: 0 0 0 8px rgba(13,148,136,0); }
    100% { box-shadow: 0 0 0 0 rgba(13,148,136,0); }
}
#docEditor .field-blank.field-pulse { animation: fieldPulse 0.5s ease-out 3; }

/* ---- Field library buttons ---- */
.field-lib-btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 5px 10px;
    border-radius: 8px;
    font-size: 11px;
    font-weight: 500;
    border: 1.5px solid transparent;
    cursor: pointer;
    transition: all 0.15s ease;
}
.field-lib-btn:hover { transform: translateY(-1px); box-shadow: 0 2px 8px rgba(0,0,0,0.1); }

.field-lib-landlord { background: #eff6ff; color: #1e40af; border-color: #93c5fd; }
.field-lib-landlord:hover { background: #dbeafe; }

.field-lib-tenant { background: #fffbeb; color: #92400e; border-color: #fcd34d; }
.field-lib-tenant:hover { background: #fef3c7; }

.field-lib-property { background: #ecfdf5; color: #065f46; border-color: #6ee7b7; }
.field-lib-property:hover { background: #d1fae5; }

.field-lib-agent { background: #f5f3ff; color: #5b21b6; border-color: #a78bfa; }
.field-lib-agent:hover { background: #ede9fe; }

.field-lib-manual { background: #f9fafb; color: #374151; border-color: #d1d5db; }
.field-lib-manual:hover { background: #f3f4f6; }

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

    /* Map pillar+assigned_to to section name for chip colours */
    function resolveSection(pillar, assignedTo) {
        if (assignedTo === 'lessor') return 'landlord';
        if (assignedTo === 'lessee') return 'tenant';
        if (pillar === 'agent') return 'agent';
        if (pillar === 'property' || pillar === 'deal') return 'property';
        return 'manual';
    }

    /* Build blanks array — pre-populate from AI confidence */
    const blanks = serverFields.map((f, i) => {
        const confidence = (f.confidence || 'low').toLowerCase();
        const hasMapping = f.suggested_key && f.suggested_key !== '' && confidence !== 'low';

        if (hasMapping) {
            return {
                context: f.context || '',
                assigned: confidence === 'high',
                suggested: confidence === 'medium',
                section: resolveSection(f.pillar || 'custom', f.assigned_to || 'agent'),
                fieldKey: f.suggested_key || '',
                fieldLabel: f.suggested_label || '',
                pillar: f.pillar || 'custom',
                assignedTo: f.assigned_to || 'agent',
                fieldType: 'text',
                confidence: confidence,
                correctionReason: '',
            };
        }

        return {
            context: f.context || '',
            assigned: false,
            suggested: false,
            section: null,
            fieldKey: '',
            fieldLabel: '',
            pillar: '',
            assignedTo: '',
            fieldType: 'text',
            confidence: 'low',
            correctionReason: '',
        };
    });

    return {
        blanks: blanks,
        templateName: @json($templateName),
        filename: @json($parsed['original_filename'] ?? $templateName),
        activeIndex: 0,
        otherSection: null,
        otherText: '',
        customCounter: 0,
        showCorrectionReason: false,
        editingBlank: false,
        showRestoreBanner: false,

        /* Computed */
        get totalBlanks() { return this.blanks.length; },
        get assignedCount() { return this.blanks.filter(b => b.assigned).length; },
        get confirmedCount() { return this.blanks.filter(b => b.assigned).length; },
        get needsReviewCount() { return this.blanks.filter(b => b.suggested && !b.assigned).length; },
        get unassignedCount() { return this.blanks.filter(b => !b.assigned && !b.suggested).length; },
        get suggestedCount() { return this.blanks.filter(b => b.suggested && !b.assigned).length; },
        get allAssigned() { return this.totalBlanks > 0 && this.assignedCount === this.totalBlanks; },
        get progressPercent() { return this.totalBlanks > 0 ? Math.round((this.assignedCount / this.totalBlanks) * 100) : 0; },

        /* Init */
        init() {
            /* Check for saved progress */
            const saved = this.loadProgress();
            if (saved && saved.length === this.blanks.length) {
                this._savedBlanks = saved;
                this.showRestoreBanner = true;
            }

            this.initMarkers();

            /* Apply AI pre-populated assignments to markers */
            this.blanks.forEach((blank, idx) => {
                if (blank.assigned || blank.suggested) {
                    this.updateMarker(idx);
                }
            });

            const main = document.getElementById('appScroll');
            if (main) { main.style.overflow = 'hidden'; main.style.padding = '0'; }

            /* Jump to first unassigned blank */
            const firstUnassigned = this.blanks.findIndex(b => !b.assigned);
            const startIdx = firstUnassigned >= 0 ? firstUnassigned : 0;
            this.activeIndex = startIdx;

            if (this.totalBlanks > 0) {
                this.$nextTick(() => this.scrollToMarker(startIdx));
            }
        },

        /* Restore saved progress from localStorage */
        restoreProgress() {
            if (!this._savedBlanks) return;
            const props = ['assigned', 'suggested', 'section', 'fieldKey', 'fieldLabel', 'pillar', 'assignedTo', 'fieldType', 'confidence', 'correctionReason'];
            this._savedBlanks.forEach((saved, idx) => {
                if (idx < this.blanks.length) {
                    props.forEach(p => { if (saved[p] !== undefined) this.blanks[idx][p] = saved[p]; });
                }
            });
            this.showRestoreBanner = false;
            this.renumberAllMarkers();
            const firstUnassigned = this.blanks.findIndex(b => !b.assigned);
            const startIdx = firstUnassigned >= 0 ? firstUnassigned : 0;
            this.activeIndex = startIdx;
            this.$nextTick(() => this.scrollToMarker(startIdx));
        },

        /* Discard saved progress and start fresh */
        discardProgress() {
            this.showRestoreBanner = false;
            this._savedBlanks = null;
            localStorage.removeItem('importer_' + this.filename);
        },

        /* Save current blanks state to localStorage */
        saveProgress() {
            const key = 'importer_' + this.filename;
            localStorage.setItem(key, JSON.stringify(this.blanks));
        },

        /* Load saved blanks state from localStorage */
        loadProgress() {
            const key = 'importer_' + this.filename;
            const saved = localStorage.getItem(key);
            if (saved) {
                try { return JSON.parse(saved); } catch(e) { return null; }
            }
            return null;
        },

        /* Set up markers in the document HTML */
        initMarkers() {
            const editor = this.$refs.editor;
            if (!editor) return;
            const self = this;

            editor.querySelectorAll('.field-blank').forEach((span, idx) => {
                span.setAttribute('data-index', idx);
                span.setAttribute('contenteditable', 'false');
                span.style.cursor = 'pointer';
                span.textContent = '[' + (idx + 1) + ']';

                span.onclick = (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    self.goToBlank(idx);
                };
            });

            /* Also extract better context from DOM if server context is empty */
            this.blanks.forEach((blank, idx) => {
                if (blank.context && blank.context.replace(/\[___\]/g, '').replace(/_+/g, '').trim().length >= 5) return;

                const span = editor.querySelector('[data-index="' + idx + '"]');
                if (!span) return;

                let paraEl = span.parentElement;
                while (paraEl && paraEl !== editor && !['P', 'DIV', 'TD', 'LI'].includes(paraEl.tagName)) {
                    paraEl = paraEl.parentElement;
                }
                if (!paraEl || paraEl === editor) return;

                const clone = paraEl.cloneNode(true);
                clone.querySelectorAll('.field-blank').forEach(fb => {
                    const thisIdx = parseInt(fb.getAttribute('data-index'));
                    fb.replaceWith(thisIdx === idx ? '[___]' : (fb.textContent || '___'));
                });
                let paraText = clone.textContent.trim();

                const blankPos = paraText.indexOf('[___]');
                if (blankPos >= 0) {
                    const before = paraText.substring(Math.max(0, blankPos - 40), blankPos);
                    const after = paraText.substring(blankPos + 5, blankPos + 45);
                    paraText = before.trim() + ' [___] ' + after.trim();
                } else if (paraText.length > 80) {
                    paraText = paraText.substring(0, 77) + '...';
                }

                if (paraText.replace(/\[___\]/g, '').replace(/_+/g, '').trim().length > 0) {
                    blank.context = paraText;
                }
            });
        },

        /* Mark current blank as manual entry and advance */
        markAsManual() {
            this.assignField('manual', 'custom.manual', 'Manual Entry', 'custom', 'agent');
        },

        /* Clear current assignment to allow reassigning */
        reassignCurrent() {
            const blank = this.blanks[this.activeIndex];
            if (!blank) return;

            blank.assigned = false;
            blank.suggested = false;
            blank.section = null;
            blank.fieldKey = '';
            blank.fieldLabel = '';
            blank.pillar = '';
            blank.assignedTo = '';
            this.editingBlank = true;
            this.updateMarker(this.activeIndex);
            this.saveProgress();
        },

        /* Confirm an AI suggestion (medium confidence → assigned) */
        confirmSuggestion() {
            const blank = this.blanks[this.activeIndex];
            if (!blank || !blank.suggested) return;

            blank.assigned = true;
            blank.suggested = false;
            this.updateMarker(this.activeIndex);
            this.deduplicateKeys();
            this.saveProgress();

            if (this.allAssigned) {
                this.editingBlank = false;
            } else {
                this.advanceToNextUnassigned();
            }
        },

        /* Reject AI suggestion — clear it so user can manually assign */
        rejectSuggestion() {
            const blank = this.blanks[this.activeIndex];
            if (!blank) return;

            blank.suggested = false;
            blank.assigned = false;
            blank.section = null;
            blank.fieldKey = '';
            blank.fieldLabel = '';
            blank.pillar = '';
            blank.assignedTo = '';
            this.showCorrectionReason = true;
            this.updateMarker(this.activeIndex);
            this.saveProgress();
        },

        /* Shift all assignments from idx downward — blank[idx] becomes unassigned */
        shiftAssignmentsDown(idx) {
            // Iterate from bottom to top so we don't
            // overwrite values before copying them
            for (let i = this.blanks.length - 1; i > idx; i--) {
                this.copyAssignment(this.blanks[i - 1], this.blanks[i]);
            }
            this.clearAssignment(this.blanks[idx]);
            this.renumberAllMarkers();
            this.saveProgress();
        },

        /* Shift all assignments from idx upward — last blank becomes unassigned */
        shiftAssignmentsUp(idx) {
            for (let i = idx; i < this.blanks.length - 1; i++) {
                this.copyAssignment(this.blanks[i + 1], this.blanks[i]);
            }
            this.clearAssignment(this.blanks[this.blanks.length - 1]);
            this.renumberAllMarkers();
            this.saveProgress();
        },

        /* Copy assignment data from source blank to target blank */
        copyAssignment(source, target) {
            target.fieldLabel = source.fieldLabel;
            target.fieldKey = source.fieldKey;
            target.pillar = source.pillar;
            target.assignedTo = source.assignedTo;
            target.assigned = source.assigned;
            target.suggested = source.suggested;
            target.confidence = source.confidence;
            target.section = source.section;
        },

        /* Clear all assignment data on a blank */
        clearAssignment(blank) {
            blank.fieldLabel = '';
            blank.fieldKey = '';
            blank.pillar = '';
            blank.assignedTo = '';
            blank.assigned = false;
            blank.suggested = false;
            blank.confidence = 'low';
            blank.section = null;
        },

        /* Derive section from blank data when section is null */
        deriveSection(blank) {
            if (blank.section) return blank.section;
            if (blank.assignedTo === 'lessor') return 'landlord';
            if (blank.assignedTo === 'lessee') return 'tenant';
            if (blank.pillar === 'contact') return 'landlord';
            if (blank.pillar === 'property' || blank.pillar === 'deal') return 'property';
            if (blank.pillar === 'agent') return 'agent';
            return 'manual';
        },

        /* Renumber all markers in the document after insert/remove */
        renumberAllMarkers() {
            const editor = this.$refs.editor;
            if (!editor) return;

            const spans = editor.querySelectorAll('.field-blank');
            const self = this;

            spans.forEach((span, idx) => {
                span.setAttribute('data-index', idx);
                span.onclick = (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    self.goToBlank(idx);
                };

                const blank = self.blanks[idx];
                span.className = 'field-blank';
                if (blank && blank.assigned) {
                    span.classList.add('assigned-' + self.deriveSection(blank));
                    span.textContent = blank.fieldLabel;
                } else if (blank && blank.suggested) {
                    span.classList.add('suggested-' + self.deriveSection(blank));
                    span.textContent = blank.fieldLabel + ' ?';
                } else {
                    span.textContent = '[' + (idx + 1) + ']';
                }
            });
        },

        /* Shift assignment up — swap this blank's assignment with the one above */
        shiftUp(idx) {
            if (idx <= 0) return;
            this.swapAssignments(idx, idx - 1);
        },

        /* Shift assignment down — swap this blank's assignment with the one below */
        shiftDown(idx) {
            if (idx >= this.totalBlanks - 1) return;
            this.swapAssignments(idx, idx + 1);
        },

        /* Swap the assignments of two blanks */
        swapAssignments(idxA, idxB) {
            const a = this.blanks[idxA];
            const b = this.blanks[idxB];

            const props = ['assigned', 'suggested', 'section', 'fieldKey', 'fieldLabel', 'pillar', 'assignedTo', 'fieldType', 'confidence'];
            const temp = {};
            props.forEach(p => temp[p] = a[p]);
            props.forEach(p => a[p] = b[p]);
            props.forEach(p => b[p] = temp[p]);

            this.updateMarker(idxA);
            this.updateMarker(idxB);
            this.saveProgress();
        },

        /* Assign a field from the library */
        assignField(section, fieldKey, fieldLabel, pillar, assignedTo) {
            const blank = this.blanks[this.activeIndex];
            if (!blank) return;

            blank.assigned = true;
            blank.suggested = false;
            blank.section = section;
            blank.fieldKey = fieldKey;
            blank.fieldLabel = fieldLabel;
            blank.pillar = pillar;
            blank.assignedTo = assignedTo;
            blank.fieldType = 'text';

            this.otherSection = null;
            this.otherText = '';

            this.updateMarker(this.activeIndex);
            this.deduplicateKeys();
            this.saveProgress();

            if (this.allAssigned) {
                this.editingBlank = false;
            } else {
                this.advanceToNextUnassigned();
            }
        },

        /* Show "Other..." input for a section */
        showOther(section) {
            this.otherSection = this.otherSection === section ? null : section;
            this.otherText = '';
        },

        /* Submit custom "Other..." field */
        submitOther(section, pillar, assignedTo) {
            const label = this.otherText.trim();
            if (!label) return;

            this.customCounter++;
            const slug = label.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
            const fieldKey = 'custom.' + section + '_' + slug;
            const fieldLabel = label;

            this.assignField(section, fieldKey, fieldLabel, pillar, assignedTo);
        },

        /* Navigate to a specific blank */
        goToBlank(idx) {
            if (idx < 0 || idx >= this.totalBlanks) return;
            this.activeIndex = idx;
            this.otherSection = null;
            this.otherText = '';
            this.showCorrectionReason = false;
            this.editingBlank = true;
            this.scrollToMarker(idx);
        },

        nextBlank() {
            if (this.totalBlanks === 0) return;
            this.goToBlank((this.activeIndex + 1) % this.totalBlanks);
        },

        prevBlank() {
            if (this.totalBlanks === 0) return;
            this.goToBlank((this.activeIndex - 1 + this.totalBlanks) % this.totalBlanks);
        },

        /* Advance to next unassigned or suggested blank, skipping assigned ones */
        advanceToNextUnassigned() {
            if (this.allAssigned) return;

            const needsWork = (b) => !b.assigned || (b.suggested && !b.assigned);
            let next = this.blanks.findIndex((b, i) => i > this.activeIndex && needsWork(b));
            if (next < 0) next = this.blanks.findIndex(b => needsWork(b));
            if (next >= 0) {
                this.goToBlank(next);
            }
        },

        /* Update a marker span in the document */
        updateMarker(idx) {
            const editor = this.$refs.editor;
            if (!editor) return;

            const span = editor.querySelector('[data-index="' + idx + '"]');
            if (!span) return;

            const blank = this.blanks[idx];

            /* Remove all assigned-* and suggested-* classes */
            span.className = 'field-blank';

            if (blank.assigned) {
                span.classList.add('assigned-' + this.deriveSection(blank));
                span.textContent = blank.fieldLabel;
            } else if (blank.suggested) {
                span.classList.add('suggested-' + this.deriveSection(blank));
                span.textContent = blank.fieldLabel + ' ?';
            } else {
                span.textContent = '[' + (idx + 1) + ']';
            }
        },

        /* Scroll the document pane to center a marker */
        scrollToMarker(idx) {
            const editor = this.$refs.editor;
            if (!editor) return;

            /* Clear previous active */
            editor.querySelectorAll('.field-blank.field-active').forEach(el => el.classList.remove('field-active'));

            const span = editor.querySelector('[data-index="' + idx + '"]');
            if (!span) return;

            span.classList.add('field-active');
            span.classList.add('field-pulse');
            setTimeout(() => span.classList.remove('field-pulse'), 1800);

            const docPane = document.getElementById('docPane');
            if (docPane) {
                const paneRect = docPane.getBoundingClientRect();
                const spanRect = span.getBoundingClientRect();
                const scrollTarget = docPane.scrollTop + (spanRect.top - paneRect.top) - (paneRect.height / 2) + (spanRect.height / 2);
                docPane.scrollTo({ top: scrollTarget, behavior: 'smooth' });
            }
        },

        /* Check if the current active blank has a specific assignment */
        isCurrentAssignment(section, fieldKey) {
            const blank = this.blanks[this.activeIndex];
            return blank && blank.assigned && blank.section === section && blank.fieldKey === fieldKey;
        },

        /* Section chip class for assigned display */
        sectionChipClass(section) {
            const map = {
                landlord: 'bg-blue-100 text-blue-800',
                tenant:   'bg-amber-100 text-amber-800',
                property: 'bg-emerald-100 text-emerald-800',
                agent:    'bg-purple-100 text-purple-800',
                manual:   'bg-gray-100 text-gray-700',
            };
            return map[section] || 'bg-gray-100 text-gray-700';
        },

        /* Deduplicate keys — add _2, _3 suffixes */
        deduplicateKeys() {
            const counts = {};
            this.blanks.forEach(b => {
                if (!b.assigned) return;
                const base = b.fieldKey;
                if (!counts[base]) counts[base] = [];
                counts[base].push(b);
            });
            for (const [base, group] of Object.entries(counts)) {
                if (group.length > 1) {
                    group.forEach((b, i) => {
                        if (i > 0) {
                            b.fieldKey = base + '_' + (i + 1);
                        }
                    });
                }
            }
        },

        /* Context formatting */
        formatContext(context) {
            if (!context) return '<span class="text-gray-300 italic">No context available</span>';
            const stripped = context.replace(/\[___\]/g, '').replace(/_+/g, '').trim();
            if (stripped.length === 0) return '<span class="text-gray-300 italic">Blank field in document</span>';
            const escaped = context.replace(/</g, '&lt;').replace(/>/g, '&gt;');
            return '...' + escaped.replace(
                /\[___\]/g,
                '<span class="inline-block px-2 py-0.5 mx-1 bg-teal-200 dark:bg-teal-700 rounded text-teal-800 dark:text-teal-200 text-[10px] font-bold">___</span>'
            ) + '...';
        },

        /* Form submission */
        prepareSubmission() {
            this.$refs.editedHtmlInput.value = this.$refs.editor.innerHTML;
        },
    };
}
</script>
@endsection
