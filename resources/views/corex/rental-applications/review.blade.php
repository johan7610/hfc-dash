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

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mt-5" style="align-items: start;">

        {{-- LEFT PANEL — the submitted application + supporting documents, viewable on screen --}}
        <div class="space-y-4">
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
                <a href="{{ route('corex.rental-applications.show', $rentalApplication) }}" class="text-xs inline-block mt-3" style="color: var(--ds-blue, #2563eb);">View / edit full application &rarr;</a>
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
                                        <iframe src="{{ route('corex.rental-applications.documents.view', [$rentalApplication, $document]) }}"
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

        {{-- RIGHT PANEL — the agent's own affordability capture + suggestive calculation --}}
        <div class="rounded-md p-4" style="background: var(--surface); border: 1px solid var(--border);">
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

            <div class="text-xs mt-2 h-4" x-show="saveStatus" x-text="saveStatus" :style="saveError ? 'color: var(--ds-red, #dc2626);' : 'color: var(--ds-emerald, #059669);'"></div>

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
function rentalReview({ saveUrl, initial, initialResult }) {
    return {
        fields: initial,
        result: initialResult ?? { label: 'incomplete' },
        saveStatus: '',
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
                        this.saveStatus = 'Saved';
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
</script>
@endsection
