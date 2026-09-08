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

@section('corex-content')
<div class="w-full" x-data="{ approveAmount: '', approveReason: '', declineReason: '', moreInfoReason: '' }">

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
                            <div class="rounded-md border flex items-center justify-between px-3 py-2 text-xs" style="border-color: var(--border);">
                                <span>{{ $document->original_name }}</span>
                                <span class="flex items-center gap-2">
                                    @if($row['has_highlights'])
                                        <span class="ds-badge ds-badge-success" title="The agent marked this document up.">Marked up</span>
                                    @endif
                                    @if($row['inline_viewable'])
                                        @if($row['has_highlights'])
                                            <a href="{{ route('corex.rental-applications.authorisation.documents.highlighted-file', [$rentalApplication, $document]) }}" target="_blank" style="color: var(--ds-blue, #2563eb); font-weight: 600;">View marked-up copy</a>
                                        @else
                                            <a href="{{ route('corex.rental-applications.authorisation.documents.view', [$rentalApplication, $document]) }}" target="_blank" style="color: var(--ds-blue, #2563eb); font-weight: 600;">View</a>
                                        @endif
                                    @else
                                        <span class="ds-badge ds-badge-default">No preview</span>
                                    @endif
                                </span>
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
@endsection
