@extends('layouts.corex')

@section('content')
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6"
     x-data="{
        showRejectModal: false,
        rejectDocId: null,
        rejectDocName: '',
        showUploadOnBehalfModal: false,
        uploadOnBehalfDocId: null,
        uploadOnBehalfRequestId: null,
        uploadOnBehalfPartyName: '',
        showRejected: false,
        savedIndicators: {},
        async saveMetadata(docId, field, value) {
            const body = {};
            body[field] = value || null;
            try {
                const resp = await fetch(`/rental/signatures/${docId}/assign-metadata`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                    body: JSON.stringify(body)
                });
                if (resp.ok) {
                    this.savedIndicators[docId + '-' + field] = true;
                    setTimeout(() => { this.savedIndicators[docId + '-' + field] = false; }, 2000);
                }
            } catch (e) { console.error('Save failed', e); }
        },
        async saveExpiry(docId, date) {
            try {
                const resp = await fetch(`/rental/signatures/${docId}/set-expiry`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                    body: JSON.stringify({ lease_expiry_date: date })
                });
                if (resp.ok) {
                    this.savedIndicators[docId + '-expiry'] = true;
                    setTimeout(() => { this.savedIndicators[docId + '-expiry'] = false; }, 2000);
                }
            } catch (e) { console.error('Save failed', e); }
        }
     }">

    {{-- Page Header --}}
    <div style="background: var(--brand-default, #0b2a4a);" class="rounded-md px-6 py-5 flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-white leading-tight tracking-tight">Electronic Signatures</h2>
            <div class="text-sm text-white/60 mt-1">
                <a href="{{ route('rental.dashboard') }}" class="text-white/60 hover:text-white transition-all duration-300">&larr; Rentals</a>
                &middot; Manage rental document signing workflows.
            </div>
        </div>
        <a href="{{ route('docuperfect.rental.uploadAndSend') }}"
           class="corex-btn-primary inline-flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" /></svg>
            Upload &amp; Send for Signing
        </a>
    </div>

    @if(session('status'))
        <div class="rounded-md border border-emerald-500/30 px-4 py-3 text-sm" style="background: rgba(16,185,129,0.1); color: var(--text-primary);">
            {{ session('status') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-md border border-red-500/30 px-4 py-3 text-sm" style="background: rgba(239,68,68,0.1); color: var(--text-primary);">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    {{-- Status summary cards --}}
    <div class="grid grid-cols-2 md:grid-cols-6 gap-4">
        @if($counts['pending_approval'] > 0)
        <a href="#section-pending-approval" onclick="event.preventDefault(); scrollToSection('section-pending-approval')"
           class="rounded-md p-4 text-center border-2 border-amber-400 cursor-pointer block transition-all duration-300 hover:border-amber-500" style="background: rgba(245,158,11,0.1);">
            <div class="text-2xl font-bold text-amber-500" id="pending-approval-count">{{ $counts['pending_approval'] }}</div>
            <div class="text-xs text-amber-500 mt-1 font-semibold">Needs Approval</div>
        </a>
        @endif
        <a href="#section-draft" onclick="event.preventDefault(); scrollToSection('section-draft')"
           class="ds-status-card p-4 text-center transition-all duration-300 cursor-pointer block">
            <div class="text-2xl font-bold" style="color: {{ $counts['draft'] > 0 ? 'var(--text-primary)' : 'var(--text-muted)' }}">{{ $counts['draft'] }}</div>
            <div class="text-xs mt-1" style="color: {{ $counts['draft'] > 0 ? 'var(--text-secondary)' : 'var(--text-muted)' }}">Draft</div>
        </a>
        <a href="#section-ready" onclick="event.preventDefault(); scrollToSection('section-ready')"
           class="ds-status-card p-4 text-center transition-all duration-300 cursor-pointer block">
            <div class="text-2xl font-bold" style="color: {{ $counts['ready_to_sign'] > 0 ? 'var(--brand-icon, #0ea5e9)' : 'var(--text-muted)' }}">{{ $counts['ready_to_sign'] }}</div>
            <div class="text-xs mt-1" style="color: {{ $counts['ready_to_sign'] > 0 ? 'var(--text-secondary)' : 'var(--text-muted)' }}">Ready to Sign</div>
        </a>
        <a href="#section-awaiting" onclick="event.preventDefault(); scrollToSection('section-awaiting')"
           class="ds-status-card p-4 text-center transition-all duration-300 cursor-pointer block">
            <div class="text-2xl font-bold" style="color: {{ $counts['awaiting_signatures'] > 0 ? '#f59e0b' : 'var(--text-muted)' }}">{{ $counts['awaiting_signatures'] }}</div>
            <div class="text-xs mt-1" style="color: {{ $counts['awaiting_signatures'] > 0 ? 'var(--text-secondary)' : 'var(--text-muted)' }}">Awaiting Signatures</div>
        </a>
        <a href="#section-completed" onclick="event.preventDefault(); scrollToSection('section-completed')"
           class="ds-status-card p-4 text-center transition-all duration-300 cursor-pointer block">
            <div class="text-2xl font-bold" style="color: {{ $counts['completed'] > 0 ? '#10b981' : 'var(--text-muted)' }}">{{ $counts['completed'] }}</div>
            <div class="text-xs mt-1" style="color: {{ $counts['completed'] > 0 ? 'var(--text-secondary)' : 'var(--text-muted)' }}">Completed</div>
        </a>
        <a href="{{ route('rental.active-leases') }}"
           class="ds-status-card p-4 text-center transition-all duration-300 cursor-pointer block">
            <div class="text-2xl font-bold" style="color: {{ $activeLeaseCount > 0 ? '#22c55e' : 'var(--text-muted)' }}">{{ $activeLeaseCount }}</div>
            <div class="text-xs mt-1" style="color: {{ $activeLeaseCount > 0 ? 'var(--text-secondary)' : 'var(--text-muted)' }}">Active Leases</div>
        </a>
    </div>

    {{-- Upcoming Renewals --}}
    @if($upcomingRenewals->isNotEmpty())
    <div class="space-y-3">
        <h3 class="text-sm font-semibold text-orange-500 uppercase tracking-wider">Upcoming Renewals</h3>
        <div class="space-y-3">
            @foreach($upcomingRenewals as $lease)
                @php
                    $daysLeft = $lease->daysUntilExpiry();
                    $urgencyBorder = match(true) {
                        $daysLeft <= 0  => 'border-red-500/40',
                        $daysLeft <= 30 => 'border-red-500/30',
                        $daysLeft <= 60 => 'border-amber-500/30',
                        default         => 'border-emerald-500/30',
                    };
                    $urgencyBg = match(true) {
                        $daysLeft <= 0  => 'rgba(239,68,68,0.08)',
                        $daysLeft <= 30 => 'rgba(239,68,68,0.06)',
                        $daysLeft <= 60 => 'rgba(245,158,11,0.06)',
                        default         => 'rgba(16,185,129,0.06)',
                    };
                    $urgencyBadge = match(true) {
                        $daysLeft <= 0  => 'bg-red-500/20 text-red-400',
                        $daysLeft <= 30 => 'bg-red-500/15 text-red-400',
                        $daysLeft <= 60 => 'bg-amber-500/15 text-amber-400',
                        default         => 'bg-emerald-500/15 text-emerald-400',
                    };
                    $rental = number_format((float) $lease->rental_amount, 0, '.', ' ');
                @endphp
                <div class="rounded-md border {{ $urgencyBorder }} p-4" style="background: {{ $urgencyBg }};">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="font-semibold" style="color: var(--text-primary);">{{ $lease->property_address }}</div>
                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                Tenant: {{ $lease->tenant_name }} | Landlord: {{ $lease->landlord_name }}
                            </div>
                            <div class="text-xs mt-0.5" style="color: var(--text-secondary);">
                                Rental: R {{ $rental }}/mo | Expires: {{ $lease->lease_end_date?->format('d M Y') }}
                            </div>
                            <div class="mt-2">
                                <span class="inline-block px-2 py-0.5 rounded-md text-[10px] font-semibold {{ $urgencyBadge }}">
                                    @if($daysLeft <= 0)
                                        EXPIRED
                                    @elseif($daysLeft <= 30)
                                        {{ $daysLeft }} days remaining — URGENT
                                    @else
                                        {{ $daysLeft }} days remaining
                                    @endif
                                </span>
                            </div>
                        </div>
                        <div class="flex flex-col gap-2 ml-4">
                            <form method="POST" action="{{ route('docuperfect.leases.renew', $lease) }}" class="inline">
                                @csrf
                                <button type="submit" class="corex-btn-primary text-xs px-3 py-1.5" onclick="return confirm('Renew lease for {{ $lease->property_address }}?')">
                                    Renew Lease
                                </button>
                            </form>
                            <button type="button" class="text-xs px-3 py-1.5 rounded-md border border-red-500/30 text-red-400 hover:bg-red-500/10 transition-all duration-300"
                                    onclick="document.getElementById('terminate-modal-{{ $lease->id }}').classList.remove('hidden')">
                                Terminate
                            </button>
                            <a href="{{ route('docuperfect.leases.history', $lease) }}" class="text-xs px-3 py-1.5 rounded-md text-center transition-all duration-300" style="border: 1px solid var(--border); color: var(--text-secondary);">
                                History
                            </a>
                        </div>
                    </div>
                </div>

                {{-- Terminate modal --}}
                <div id="terminate-modal-{{ $lease->id }}" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
                    <div class="rounded-md p-6 max-w-md w-full shadow-xl" style="background: var(--surface); border: 1px solid var(--border);">
                        <h4 class="font-semibold mb-3" style="color: var(--text-primary);">Terminate Lease</h4>
                        <p class="text-sm mb-4" style="color: var(--text-secondary);">{{ $lease->property_address }}</p>
                        <form method="POST" action="{{ route('docuperfect.leases.terminate', $lease) }}">
                            @csrf
                            <div class="mb-3">
                                <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Termination Date</label>
                                <input type="date" name="termination_date" value="{{ now()->format('Y-m-d') }}" required
                                       class="w-full rounded-md text-sm" style="background: var(--surface-2); border: 1px solid var(--border); color: var(--text-primary);">
                            </div>
                            <div class="mb-4">
                                <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Reason (optional)</label>
                                <textarea name="reason" rows="2" maxlength="500" class="w-full rounded-md text-sm" style="background: var(--surface-2); border: 1px solid var(--border); color: var(--text-primary);" placeholder="Reason for termination..."></textarea>
                            </div>
                            <div class="flex gap-2 justify-end">
                                <button type="button" class="text-xs px-3 py-1.5 rounded-md transition-all duration-300" style="border: 1px solid var(--border); color: var(--text-secondary);"
                                        onclick="this.closest('[id^=terminate-modal]').classList.add('hidden')">
                                    Cancel
                                </button>
                                <button type="submit" class="text-xs px-3 py-1.5 rounded-md bg-red-600 text-white hover:bg-red-700 transition-all duration-300">
                                    Confirm Termination
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Expired Leases --}}
    @if($expiredLeases->isNotEmpty())
    <div class="space-y-3">
        <h3 class="text-sm font-semibold text-red-400 uppercase tracking-wider">Recently Expired Leases</h3>
        <div class="rounded-md overflow-hidden" style="background: var(--surface); border: 1px solid var(--border);">
            <table class="w-full text-sm ds-table">
                <thead>
                    <tr style="background: var(--surface-2);">
                        <th class="text-left px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Property</th>
                        <th class="text-left px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Tenant</th>
                        <th class="text-left px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Expired</th>
                        <th class="text-right px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($expiredLeases as $lease)
                    <tr class="transition-all duration-300" style="border-bottom: 1px solid var(--border);"
                        onmouseover="this.style.background='var(--surface-2)'" onmouseout="this.style.background='transparent'">
                        <td class="px-4 py-3 font-medium" style="color: var(--text-primary);">{{ $lease->property_address }}</td>
                        <td class="px-4 py-3" style="color: var(--text-secondary);">{{ $lease->tenant_name }}</td>
                        <td class="px-4 py-3 text-red-400 text-xs">{{ $lease->lease_end_date?->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-right">
                            <form method="POST" action="{{ route('docuperfect.leases.renew', $lease) }}" class="inline">
                                @csrf
                                <button type="submit" class="text-xs hover:underline transition-all duration-300" style="color: var(--brand-icon, #0ea5e9);" onclick="return confirm('Renew lease for {{ $lease->property_address }}?')">Renew</button>
                            </form>
                            <a href="{{ route('docuperfect.leases.history', $lease) }}" class="text-xs hover:underline ml-2 transition-all duration-300" style="color: var(--text-secondary);">History</a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Needs Your Approval --}}
    @if($groups['pending_approval']->isNotEmpty())
    <div id="section-pending-approval" class="space-y-3 scroll-mt-4">
        <h3 class="text-sm font-semibold text-amber-500 uppercase tracking-wider flex items-center gap-2">
            <span class="inline-flex items-center justify-center w-5 h-5 bg-amber-500 text-white text-[10px] font-bold rounded-md">{{ $groups['pending_approval']->count() }}</span>
            Needs Your Approval
        </h3>
        <div class="space-y-3">
            @foreach($groups['pending_approval'] as $doc)
                @php
                    $sigTemplate = $signatureTemplates->get($doc->id);
                    $requests = $sigTemplate ? $sigTemplate->requests->keyBy('party_role') : collect();
                    $completedReq = $sigTemplate ? $sigTemplate->requests->where('status', 'completed')->where('party_role', '!=', 'agent')->sortByDesc('completed_at')->first() : null;
                    $wetInkReq = $sigTemplate ? $sigTemplate->requests->first(fn($r) => $r->wet_ink_status === 'uploaded_pending_review') : null;
                    $isWetInkApproval = (bool) $wetInkReq;
                @endphp
                <div class="rounded-md border-2 border-amber-400 p-4" style="background: rgba(245,158,11,0.08);">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="font-semibold" style="color: var(--text-primary);">
                                {{ $doc->name }}
                                @if($doc->document_type)
                                    <span class="inline-block ml-2 px-2 py-0.5 rounded-md text-[10px] font-semibold" style="background: var(--surface-2); color: var(--text-secondary);">{{ ucwords(str_replace('_', ' ', $doc->document_type)) }}</span>
                                @endif
                            </div>
                            <div class="flex flex-wrap items-center gap-2 mt-2">
                                @foreach(['agent', 'tenant', 'landlord'] as $role)
                                    @php $req = $requests->get($role); @endphp
                                    @if($req)
                                        @if($req->status === 'completed')
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-semibold bg-emerald-500/15 text-emerald-400">
                                                &#10003; {{ ucfirst($role) }} signed
                                                @if($req->signing_method === 'wet_ink')
                                                    <span style="color: var(--text-muted);">(wet ink)</span>
                                                @endif
                                            </span>
                                        @elseif($req->wet_ink_status === 'uploaded_pending_review')
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-semibold bg-amber-500/15 text-amber-400">
                                                &#9888; {{ ucfirst($role) }} wet ink — pending review
                                            </span>
                                        @elseif($req->status === 'waiting')
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-semibold" style="background: var(--surface-2); color: var(--text-muted);">
                                                &#128274; {{ ucfirst($role) }} waiting
                                            </span>
                                        @elseif(in_array($req->status, ['pending', 'viewed', 'partially_signed']))
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-semibold" style="background: rgba(14,165,233,0.12); color: var(--brand-icon, #0ea5e9);">
                                                &#9993; {{ ucfirst($role) }} — {{ $req->status === 'viewed' ? 'viewed' : ($req->status === 'partially_signed' ? 'signing' : 'sent') }}
                                            </span>
                                        @endif
                                    @endif
                                @endforeach
                            </div>
                            @if($completedReq)
                                <div class="text-xs text-amber-500 mt-2">
                                    {{ ucfirst($completedReq->party_role) }} <strong>{{ $completedReq->signer_name }}</strong>
                                    signed {{ $completedReq->completed_at?->diffForHumans() }}
                                </div>
                            @endif
                            {{-- Inline metadata dropdowns --}}
                            <div class="flex flex-wrap items-center gap-3 mt-3 pt-3 border-t border-amber-500/20">
                                <div class="flex items-center gap-1.5">
                                    <label class="text-[10px] font-medium whitespace-nowrap" style="color: var(--text-muted);">Type:</label>
                                    <select class="text-xs rounded-md py-1 px-2" style="background: var(--surface); border: 1px solid var(--border); color: var(--text-primary);"
                                            @change="saveMetadata({{ $doc->id }}, 'document_type_id', $event.target.value)">
                                        <option value="">-- None --</option>
                                        @foreach($documentTypes as $dt)
                                            <option value="{{ $dt->id }}" {{ ($doc->document_type ?? '') === $dt->slug ? 'selected' : '' }}>{{ $dt->name }}</option>
                                        @endforeach
                                    </select>
                                    <span class="text-emerald-400 text-[10px] font-medium transition-opacity" :class="savedIndicators[{{ $doc->id }} + '-document_type_id'] ? 'opacity-100' : 'opacity-0'">Saved</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <label class="text-[10px] font-medium whitespace-nowrap" style="color: var(--text-muted);">Property:</label>
                                    <select class="text-xs rounded-md py-1 px-2" style="background: var(--surface); border: 1px solid var(--border); color: var(--text-primary);"
                                            @change="saveMetadata({{ $doc->id }}, 'property_id', $event.target.value)">
                                        <option value="">-- None --</option>
                                        @foreach($properties as $prop)
                                            <option value="{{ $prop->id }}" {{ ($doc->property_id ?? '') == $prop->id ? 'selected' : '' }}>{{ $prop->full_address }}</option>
                                        @endforeach
                                    </select>
                                    <span class="text-emerald-400 text-[10px] font-medium transition-opacity" :class="savedIndicators[{{ $doc->id }} + '-property_id'] ? 'opacity-100' : 'opacity-0'">Saved</span>
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col gap-2 ml-4">
                            @if($isWetInkApproval)
                                <a href="{{ route('docuperfect.signatures.wetInkReview', ['document' => $doc->id, 'signingRequest' => $wetInkReq->id]) }}"
                                   class="inline-flex items-center px-4 py-2 bg-amber-600 text-white text-xs font-medium rounded-md hover:bg-amber-700 whitespace-nowrap transition-all duration-300">
                                    Review Wet Ink
                                </a>
                            @else
                                <a href="{{ route('docuperfect.signatures.review', $doc) }}"
                                   class="inline-flex items-center px-4 py-2 bg-amber-600 text-white text-xs font-medium rounded-md hover:bg-amber-700 whitespace-nowrap transition-all duration-300">
                                    Review &amp; Approve
                                </a>
                            @endif
                            <button type="button"
                                    @click="rejectDocId = {{ $doc->id }}; rejectDocName = {{ Js::from($doc->name) }}; showRejectModal = true"
                                    class="text-xs text-red-400 hover:text-red-300 text-center transition-all duration-300">
                                Reject / Redo
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Awaiting Signatures --}}
    @if($groups['awaiting_signatures']->isNotEmpty())
    <div id="section-awaiting" class="space-y-3 scroll-mt-4">
        <h3 class="text-sm font-semibold text-amber-500 uppercase tracking-wider">Awaiting Signatures</h3>
        <div class="rounded-md overflow-hidden" style="background: var(--surface); border: 1px solid var(--border);">
            <table class="w-full text-sm ds-table">
                <thead>
                    <tr style="background: var(--surface-2);">
                        <th class="text-left px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Document</th>
                        <th class="text-left px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Type</th>
                        <th class="text-left px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Property</th>
                        <th class="text-left px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Signing Progress</th>
                        @if($user->hasPermission('documents.edit'))
                        <th class="text-left px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Agent</th>
                        @endif
                        <th class="text-right px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($groups['awaiting_signatures'] as $doc)
                    @php
                        $sigTemplate = $signatureTemplates->get($doc->id);
                        $requests = $sigTemplate ? $sigTemplate->requests->keyBy('party_role') : collect();
                    @endphp
                    <tr class="transition-all duration-300" style="border-bottom: 1px solid var(--border);"
                        onmouseover="this.style.background='var(--surface-2)'" onmouseout="this.style.background='transparent'">
                        <td class="px-4 py-3 font-medium" style="color: var(--text-primary);">{{ $doc->name }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-1">
                                <select class="text-xs rounded-md py-0.5 px-1 w-28" style="background: var(--surface); border: 1px solid var(--border); color: var(--text-primary);"
                                        @change="saveMetadata({{ $doc->id }}, 'document_type_id', $event.target.value)">
                                    <option value="">--</option>
                                    @foreach($documentTypes as $dt)
                                        <option value="{{ $dt->id }}" {{ ($doc->document_type ?? '') === $dt->slug ? 'selected' : '' }}>{{ $dt->name }}</option>
                                    @endforeach
                                </select>
                                <span class="text-emerald-400 text-[10px]" :class="savedIndicators[{{ $doc->id }} + '-document_type_id'] ? 'opacity-100' : 'opacity-0'">&#10003;</span>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-1">
                                <select class="text-xs rounded-md py-0.5 px-1 w-36" style="background: var(--surface); border: 1px solid var(--border); color: var(--text-primary);"
                                        @change="saveMetadata({{ $doc->id }}, 'property_id', $event.target.value)">
                                    <option value="">--</option>
                                    @foreach($properties as $prop)
                                        <option value="{{ $prop->id }}" {{ ($doc->property_id ?? '') == $prop->id ? 'selected' : '' }}>{{ $prop->full_address }}</option>
                                    @endforeach
                                </select>
                                <span class="text-emerald-400 text-[10px]" :class="savedIndicators[{{ $doc->id }} + '-property_id'] ? 'opacity-100' : 'opacity-0'">&#10003;</span>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            @if($sigTemplate)
                            <div class="flex flex-col gap-1.5">
                                @foreach(['agent', 'tenant', 'landlord'] as $role)
                                    @php $req = $requests->get($role); @endphp
                                    @if($req)
                                    <div class="flex items-start gap-1.5 text-xs">
                                        @if($req->status === 'completed')
                                            <span class="text-emerald-400 mt-0.5" title="Completed">&#10003;</span>
                                            <div>
                                                <span class="capitalize" style="color: var(--text-secondary);">{{ $role }}</span>
                                                <span class="text-emerald-400 font-medium">
                                                    {{ $req->signer_name }}
                                                    @if($req->signing_method === 'wet_ink')
                                                        <span style="color: var(--text-muted);">(wet ink)</span>
                                                    @endif
                                                </span>
                                            </div>
                                        @elseif($req->wet_ink_status === 'uploaded_pending_review')
                                            <span class="text-amber-400 mt-0.5" title="Wet ink uploaded">&#9888;</span>
                                            <div>
                                                <span class="capitalize" style="color: var(--text-secondary);">{{ $role }}</span>
                                                <span class="text-amber-400 font-medium">wet ink — pending review</span>
                                            </div>
                                        @elseif(in_array($req->status, ['pending', 'viewed', 'partially_signed']))
                                            @php
                                                $days = $req->daysSinceSent();
                                                $dayColor = $days <= 3 ? 'text-emerald-400' : ($days <= 7 ? 'text-amber-400' : 'text-red-400');
                                                $dayBg = $days <= 3 ? 'bg-emerald-500/10' : ($days <= 7 ? 'bg-amber-500/10' : 'bg-red-500/10');
                                            @endphp
                                            <span class="mt-0.5" style="color: var(--brand-icon, #0ea5e9);" title="Awaiting">&#9993;</span>
                                            <div>
                                                <div>
                                                    <span class="capitalize" style="color: var(--text-secondary);">{{ $role }}</span>
                                                    <span style="color: var(--brand-icon, #0ea5e9);">
                                                        {{ $req->signer_name }}
                                                        — {{ $req->status === 'viewed' ? 'viewed' : ($req->status === 'partially_signed' ? 'signing' : 'sent') }}
                                                    </span>
                                                </div>
                                                <div class="flex items-center gap-2 mt-0.5">
                                                    <span class="inline-block px-1.5 py-0.5 rounded-md {{ $dayBg }} {{ $dayColor }} text-[10px] font-medium">
                                                        {{ $days }}d ago
                                                    </span>
                                                    @if($req->viewed_at)
                                                        <span class="text-[10px]" style="color: var(--text-muted);">viewed {{ $req->viewed_at->format('d M H:i') }}</span>
                                                    @endif
                                                    @if($req->reminder_count > 0)
                                                        <span class="text-[10px]" style="color: var(--text-muted);">{{ $req->reminder_count }} reminder{{ $req->reminder_count > 1 ? 's' : '' }} sent</span>
                                                    @endif
                                                </div>
                                                @if($days >= 7)
                                                    <div class="text-red-400 text-[10px] font-medium mt-0.5">&#9888; {{ $days }} days without signing — follow up recommended</div>
                                                @endif
                                            </div>
                                        @elseif($req->status === 'waiting')
                                            <span class="mt-0.5" style="color: var(--text-muted);" title="Waiting for previous party">&#128274;</span>
                                            <div>
                                                <span class="capitalize" style="color: var(--text-muted);">{{ $role }}</span>
                                                <span style="color: var(--text-muted);">waiting</span>
                                            </div>
                                        @endif
                                    </div>
                                    @endif
                                @endforeach
                            </div>
                            @endif
                        </td>
                        @if($user->hasPermission('documents.edit'))
                        <td class="px-4 py-3" style="color: var(--text-secondary);">{{ $doc->owner->name ?? '-' }}</td>
                        @endif
                        <td class="px-4 py-3 text-right">
                            <div class="flex flex-col items-end gap-1">
                                <a href="{{ route('docuperfect.signatures.setup', $doc) }}" class="text-xs hover:underline transition-all duration-300" style="color: var(--brand-icon, #0ea5e9);">View</a>
                                @if($sigTemplate)
                                    @php
                                        $activeReq = $sigTemplate->requests->first(fn($r) => in_array($r->status, ['pending', 'viewed', 'partially_signed']));
                                    @endphp
                                    @if($activeReq)
                                        <form method="POST" action="{{ route('docuperfect.signatures.sendReminder', ['document' => $doc->id, 'signatureRequest' => $activeReq->id]) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="text-amber-500 hover:underline text-xs transition-all duration-300" onclick="return confirm('Send reminder to {{ $activeReq->signer_name }}?')">
                                                Send Reminder
                                            </button>
                                        </form>
                                        <button type="button"
                                                @click="uploadOnBehalfDocId = {{ $doc->id }}; uploadOnBehalfRequestId = {{ $activeReq->id }}; uploadOnBehalfPartyName = {{ Js::from($activeReq->signer_name) }}; showUploadOnBehalfModal = true"
                                                class="text-indigo-400 hover:underline text-xs transition-all duration-300">
                                            Upload on Behalf
                                        </button>
                                    @endif
                                @endif
                                <button type="button"
                                        @click="rejectDocId = {{ $doc->id }}; rejectDocName = {{ Js::from($doc->name) }}; showRejectModal = true"
                                        class="text-red-400 hover:underline text-xs transition-all duration-300">
                                    Reject / Redo
                                </button>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Ready to Sign --}}
    @if($groups['ready_to_sign']->isNotEmpty())
    <div id="section-ready" class="space-y-3 scroll-mt-4">
        <h3 class="text-sm font-semibold uppercase tracking-wider" style="color: var(--brand-icon, #0ea5e9);">Ready to Sign</h3>
        <div class="rounded-md overflow-hidden" style="background: var(--surface); border: 1px solid var(--border);">
            <table class="w-full text-sm ds-table">
                <thead>
                    <tr style="background: var(--surface-2);">
                        <th class="text-left px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Document</th>
                        <th class="text-left px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Type</th>
                        <th class="text-left px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Status</th>
                        @if($user->hasPermission('documents.edit'))
                        <th class="text-left px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Agent</th>
                        @endif
                        <th class="text-right px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($groups['ready_to_sign'] as $doc)
                    @php
                        $sigTemplate = $signatureTemplates->get($doc->id);
                        $hasSigTemplate = $sigTemplate !== null;
                    @endphp
                    <tr class="transition-all duration-300" style="border-bottom: 1px solid var(--border);"
                        onmouseover="this.style.background='var(--surface-2)'" onmouseout="this.style.background='transparent'">
                        <td class="px-4 py-3 font-medium" style="color: var(--text-primary);">{{ $doc->name }}</td>
                        <td class="px-4 py-3" style="color: var(--text-secondary);">{{ $doc->template->documentType->name ?? '-' }}</td>
                        <td class="px-4 py-3">
                            @if($hasSigTemplate)
                                <span class="inline-block px-2 py-0.5 rounded-md text-[10px] font-semibold" style="background: rgba(14,165,233,0.12); color: var(--brand-icon, #0ea5e9);">Signature setup started</span>
                            @else
                                <span class="inline-block px-2 py-0.5 rounded-md text-[10px] font-semibold bg-emerald-500/15 text-emerald-400">All fields complete</span>
                            @endif
                        </td>
                        @if($user->hasPermission('documents.edit'))
                        <td class="px-4 py-3" style="color: var(--text-secondary);">{{ $doc->owner->name ?? '-' }}</td>
                        @endif
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                @if(!$hasSigTemplate || in_array($sigTemplate?->status, ['draft', 'ready']))
                                    <form action="{{ route('docuperfect.signatures.uploadPresigned', $doc) }}" method="POST" enctype="multipart/form-data" class="inline-flex items-center gap-1"
                                          x-data="{ files: null }" @submit.prevent="if (files) $el.submit()">
                                        @csrf
                                        <label class="inline-flex items-center px-3 py-1 text-xs rounded-md cursor-pointer transition-all duration-300" style="background: var(--surface-2); border: 1px solid var(--border); color: var(--text-secondary);">
                                            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                            Upload Pre-Signed
                                            <input type="file" name="presigned_files[]" multiple accept=".pdf,.jpg,.jpeg,.png" class="hidden"
                                                   @change="files = $event.target.files; if (files.length) $el.closest('form').submit()">
                                        </label>
                                    </form>
                                @endif
                                <a href="{{ route('docuperfect.signatures.setup', $doc) }}" class="corex-btn-primary text-xs px-3 py-1">Set Up Signatures</a>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Draft --}}
    @if($groups['draft']->isNotEmpty())
    <div id="section-draft" class="space-y-3 scroll-mt-4">
        <h3 class="text-sm font-semibold uppercase tracking-wider" style="color: var(--text-secondary);">Draft — Fields Incomplete</h3>
        <div class="rounded-md overflow-hidden" style="background: var(--surface); border: 1px solid var(--border);">
            <table class="w-full text-sm ds-table">
                <thead>
                    <tr style="background: var(--surface-2);">
                        <th class="text-left px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Document</th>
                        <th class="text-left px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Type</th>
                        <th class="text-left px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Field Progress</th>
                        @if($user->hasPermission('documents.edit'))
                        <th class="text-left px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Agent</th>
                        @endif
                        <th class="text-right px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($groups['draft'] as $doc)
                    @php
                        $fs = $fieldStatus[$doc->id] ?? null;
                    @endphp
                    <tr class="transition-all duration-300" style="border-bottom: 1px solid var(--border);"
                        onmouseover="this.style.background='var(--surface-2)'" onmouseout="this.style.background='transparent'">
                        <td class="px-4 py-3 font-medium" style="color: var(--text-primary);">{{ $doc->name }}</td>
                        <td class="px-4 py-3" style="color: var(--text-secondary);">{{ $doc->template->documentType->name ?? '-' }}</td>
                        <td class="px-4 py-3">
                            @if($fs && $fs['total'] > 0)
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 max-w-[120px] rounded-md h-1.5" style="background: var(--surface-2);">
                                        <div class="bg-amber-500 h-1.5 rounded-md" style="width: {{ round(($fs['filled'] / $fs['total']) * 100) }}%"></div>
                                    </div>
                                    <span class="text-xs text-amber-500 font-medium">{{ $fs['filled'] }}/{{ $fs['total'] }}</span>
                                </div>
                                @if(count($fs['missing']) > 0)
                                    <div class="text-[10px] mt-0.5" style="color: var(--text-muted);">
                                        Missing: {{ implode(', ', array_slice($fs['missing'], 0, 3)) }}
                                        @if(count($fs['missing']) > 3)
                                            +{{ count($fs['missing']) - 3 }} more
                                        @endif
                                    </div>
                                @endif
                            @else
                                <span class="text-xs" style="color: var(--text-muted);">No required fields</span>
                            @endif
                        </td>
                        @if($user->hasPermission('documents.edit'))
                        <td class="px-4 py-3" style="color: var(--text-secondary);">{{ $doc->owner->name ?? '-' }}</td>
                        @endif
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <form action="{{ route('docuperfect.signatures.uploadPresigned', $doc) }}" method="POST" enctype="multipart/form-data" class="inline-flex items-center gap-1"
                                      x-data="{ files: null }">
                                    @csrf
                                    <label class="inline-flex items-center px-3 py-1 text-xs rounded-md cursor-pointer transition-all duration-300" style="background: var(--surface-2); border: 1px solid var(--border); color: var(--text-secondary);">
                                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                        Upload Pre-Signed
                                        <input type="file" name="presigned_files[]" multiple accept=".pdf,.jpg,.jpeg,.png" class="hidden"
                                               @change="files = $event.target.files; if (files.length) $el.closest('form').submit()">
                                    </label>
                                </form>
                                <a href="{{ route('docuperfect.documents.edit', $doc) }}" class="text-xs hover:underline transition-all duration-300" style="color: var(--brand-icon, #0ea5e9);">Edit Document</a>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Completed --}}
    @if($groups['completed']->isNotEmpty())
    <div id="section-completed" class="space-y-3 scroll-mt-4">
        <h3 class="text-sm font-semibold text-emerald-500 uppercase tracking-wider">Completed</h3>
        <div class="rounded-md overflow-hidden" style="background: var(--surface); border: 1px solid var(--border);">
            <table class="w-full text-sm ds-table">
                <thead>
                    <tr style="background: var(--surface-2);">
                        <th class="text-left px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Document</th>
                        <th class="text-left px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Type</th>
                        @if($user->hasPermission('documents.edit'))
                        <th class="text-left px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Agent</th>
                        @endif
                        <th class="text-right px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($groups['completed'] as $doc)
                    <tr class="transition-all duration-300" style="border-bottom: 1px solid var(--border);"
                        onmouseover="this.style.background='var(--surface-2)'" onmouseout="this.style.background='transparent'">
                        <td class="px-4 py-3 font-medium" style="color: var(--text-primary);">{{ $doc->name }}</td>
                        <td class="px-4 py-3" style="color: var(--text-secondary);">{{ $doc->template->documentType->name ?? '-' }}</td>
                        @if($user->hasPermission('documents.edit'))
                        <td class="px-4 py-3" style="color: var(--text-secondary);">{{ $doc->owner->name ?? '-' }}</td>
                        @endif
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('docuperfect.signatures.audit', $doc) }}" class="text-xs hover:underline transition-all duration-300" style="color: var(--brand-icon, #0ea5e9);">Audit</a>
                            <a href="{{ route('docuperfect.signatures.download', $doc) }}" class="text-emerald-400 hover:underline text-xs ml-2 transition-all duration-300">Download</a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Properties — documents grouped by property --}}
    @if($documentsByProperty->isNotEmpty())
    <div id="section-properties" class="space-y-3 scroll-mt-4">
        <h3 class="text-sm font-semibold text-indigo-400 uppercase tracking-wider flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
            </svg>
            Properties
        </h3>
        <div class="space-y-3">
            @foreach($documentsByProperty->sortKeys() as $propertyId => $docs)
                @php
                    $propName = $propertyId ? ($docs->first()->property_address ?? ($properties->firstWhere('id', $propertyId)->full_address ?? 'Unknown Property')) : null;
                @endphp
                <div x-data="{ open: {{ $propertyId ? 'true' : 'false' }} }">
                    <button @click="open = !open" class="w-full flex items-center justify-between px-4 py-3 rounded-md transition-all duration-300 text-left" style="background: var(--surface); border: 1px solid var(--border);">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-indigo-400 transition-transform duration-300" :class="open ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                            <span class="font-semibold text-sm" style="color: var(--text-primary);">
                                {{ $propName ?? 'Unassigned' }}
                            </span>
                            <span class="text-xs" style="color: var(--text-muted);">({{ $docs->count() }} {{ Str::plural('document', $docs->count()) }})</span>
                        </div>
                    </button>
                    <div x-show="open" x-collapse class="mt-1">
                        <div class="rounded-md overflow-hidden" style="background: var(--surface); border: 1px solid var(--border);">
                            <table class="w-full text-sm ds-table">
                                <thead>
                                    <tr style="background: var(--surface-2);">
                                        <th class="text-left px-4 py-2 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Type</th>
                                        <th class="text-left px-4 py-2 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Document</th>
                                        <th class="text-left px-4 py-2 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Status</th>
                                        <th class="text-right px-4 py-2 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @foreach($docs as $doc)
                                    @php
                                        $st = $signatureTemplates->get($doc->id);
                                        $statusLabel = 'Draft';
                                        $statusColor = 'background: var(--surface-2); color: var(--text-secondary);';
                                        if ($st) {
                                            $statusLabel = match($st->status) {
                                                'completed' => 'Signed',
                                                'pending_agent_approval' => 'Needs Approval',
                                                'rejected' => 'Rejected',
                                                'signing', 'awaiting_tenant', 'awaiting_landlord' => 'Awaiting',
                                                default => ucfirst(str_replace('_', ' ', $st->status)),
                                            };
                                            $statusColor = match($st->status) {
                                                'completed' => 'background: rgba(16,185,129,0.12); color: #10b981;',
                                                'pending_agent_approval' => 'background: rgba(245,158,11,0.12); color: #f59e0b;',
                                                'rejected' => 'background: rgba(239,68,68,0.12); color: #ef4444;',
                                                'signing', 'awaiting_tenant', 'awaiting_landlord' => 'background: rgba(14,165,233,0.12); color: #0ea5e9;',
                                                default => 'background: var(--surface-2); color: var(--text-secondary);',
                                            };
                                        }
                                    @endphp
                                    <tr class="transition-all duration-300" style="border-bottom: 1px solid var(--border);"
                                        onmouseover="this.style.background='var(--surface-2)'" onmouseout="this.style.background='transparent'">
                                        <td class="px-4 py-2">
                                            @if($doc->document_type)
                                                <span class="inline-block px-2 py-0.5 rounded-md text-[10px] font-semibold" style="background: var(--surface-2); color: var(--text-secondary);">{{ ucwords(str_replace('_', ' ', $doc->document_type)) }}</span>
                                            @else
                                                <span class="text-xs" style="color: var(--text-muted);">—</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 font-medium" style="color: var(--text-primary);">{{ $doc->name }}</td>
                                        <td class="px-4 py-2">
                                            <span class="inline-block px-2 py-0.5 rounded-md text-[10px] font-semibold" style="{{ $statusColor }}">{{ $statusLabel }}</span>
                                        </td>
                                        <td class="px-4 py-2 text-right">
                                            @if($st && $st->status === 'completed')
                                                <a href="{{ route('docuperfect.signatures.audit', $doc) }}" class="text-xs hover:underline transition-all duration-300" style="color: var(--brand-icon, #0ea5e9);">Audit</a>
                                                <a href="{{ route('docuperfect.signatures.download', $doc) }}" class="text-emerald-400 hover:underline text-xs ml-2 transition-all duration-300">Download</a>
                                            @else
                                                <a href="{{ route('docuperfect.signatures.setup', $doc) }}" class="text-xs hover:underline transition-all duration-300" style="color: var(--brand-icon, #0ea5e9);">View</a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Active Leases --}}
    <div id="section-active-leases" class="space-y-3 scroll-mt-4">
        <h3 class="text-sm font-semibold text-green-500 uppercase tracking-wider flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            Active Leases
        </h3>

        {{-- LeaseRecord-based active leases --}}
        @if($activeLeases->isNotEmpty())
        <div class="space-y-3">
            @foreach($activeLeases as $lease)
                @php
                    $rental = number_format((float) $lease->rental_amount, 0, '.', ' ');
                    $daysLeft = $lease->daysUntilExpiry();
                    $expiryColor = match(true) {
                        $daysLeft <= 30 => 'text-red-400',
                        $daysLeft <= 90 => 'text-amber-400',
                        default => 'text-green-400',
                    };
                    $expiryBg = match(true) {
                        $daysLeft <= 30 => 'rgba(239,68,68,0.06)',
                        $daysLeft <= 90 => 'rgba(245,158,11,0.06)',
                        default => 'rgba(34,197,94,0.06)',
                    };
                    $expiryBorder = match(true) {
                        $daysLeft <= 30 => 'border-red-500/25',
                        $daysLeft <= 90 => 'border-amber-500/25',
                        default => 'border-green-500/25',
                    };
                    $expiryDot = match(true) {
                        $daysLeft <= 30 => 'bg-red-500',
                        $daysLeft <= 90 => 'bg-amber-500',
                        default => 'bg-green-500',
                    };
                @endphp
                <div class="rounded-md border {{ $expiryBorder }} p-4" style="background: {{ $expiryBg }};">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <span class="inline-block w-2.5 h-2.5 rounded-full {{ $expiryDot }}"></span>
                                <span class="font-semibold" style="color: var(--text-primary);">{{ $lease->property_address ?: ($lease->document->name ?? 'Unnamed') }}</span>
                            </div>
                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                Tenant: {{ $lease->tenant_name ?? '—' }}
                                <span class="mx-1.5">|</span>
                                Landlord: {{ $lease->landlord_name ?? '—' }}
                            </div>
                            <div class="text-xs mt-0.5" style="color: var(--text-muted);">
                                @if($lease->rental_amount)
                                    Rental: R {{ $rental }}/mo
                                    <span class="mx-1.5">|</span>
                                @endif
                                @if($lease->lease_start_date)
                                    Start: {{ $lease->lease_start_date->format('d M Y') }}
                                    <span class="mx-1.5">|</span>
                                @endif
                                @if($lease->lease_end_date)
                                    <span class="{{ $expiryColor }} font-medium">Expires: {{ $lease->lease_end_date->format('d M Y') }} ({{ $daysLeft }}d)</span>
                                @endif
                            </div>
                            @if($lease->signatureTemplate && $lease->signatureTemplate->completed_at)
                                <div class="text-[10px] text-green-500 mt-1">
                                    Signed {{ $lease->signatureTemplate->completed_at->format('d M Y') }}
                                </div>
                            @endif
                        </div>
                        <div class="flex flex-col gap-2 ml-4">
                            @if($lease->document)
                                <a href="{{ route('docuperfect.signatures.audit', $lease->document) }}"
                                   class="text-xs px-3 py-1.5 rounded-md text-center transition-all duration-300" style="border: 1px solid var(--border); color: var(--text-secondary);">
                                    Audit
                                </a>
                            @endif
                            @if($lease->signatureTemplate && $lease->signatureTemplate->signed_pdf_path)
                                <a href="{{ route('docuperfect.signatures.download', $lease->document) }}"
                                   class="text-xs px-3 py-1.5 rounded-md bg-green-600 text-white hover:bg-green-700 text-center transition-all duration-300">
                                    Download PDF
                                </a>
                            @endif
                            <a href="{{ route('docuperfect.leases.history', $lease) }}"
                               class="text-xs px-3 py-1.5 rounded-md text-center transition-all duration-300" style="border: 1px solid var(--border); color: var(--text-secondary);">
                                History
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        @endif

        {{-- Completed lease-type documents with expiry picker --}}
        @if($completedLeaseDocs->isNotEmpty())
        <div class="rounded-md overflow-hidden mt-3" style="background: var(--surface); border: 1px solid var(--border);">
            <div class="px-4 py-2.5" style="background: var(--surface-2); border-bottom: 1px solid var(--border);">
                <span class="text-xs font-medium" style="color: var(--text-secondary);">Lease Documents — Set Expiry Dates</span>
            </div>
            <table class="w-full text-sm ds-table">
                <thead>
                    <tr style="background: var(--surface-2);">
                        <th class="text-left px-4 py-2 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Property</th>
                        <th class="text-left px-4 py-2 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Document</th>
                        <th class="text-left px-4 py-2 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Signed</th>
                        <th class="text-left px-4 py-2 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Lease Expiry</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($completedLeaseDocs as $doc)
                    @php
                        $st = $signatureTemplates->get($doc->id);
                        $signedDate = $st?->completed_at;
                        $expiry = $doc->lease_expiry_date;
                        $expiryDaysLeft = $expiry ? (int) now()->diffInDays($expiry, false) : null;
                        $expiryIndicator = match(true) {
                            $expiryDaysLeft === null => 'bg-slate-400',
                            $expiryDaysLeft <= 30 => 'bg-red-500',
                            $expiryDaysLeft <= 90 => 'bg-amber-500',
                            default => 'bg-green-500',
                        };
                    @endphp
                    <tr class="transition-all duration-300" style="border-bottom: 1px solid var(--border);"
                        onmouseover="this.style.background='var(--surface-2)'" onmouseout="this.style.background='transparent'">
                        <td class="px-4 py-2 font-medium" style="color: var(--text-primary);">{{ $doc->property_address ?: '—' }}</td>
                        <td class="px-4 py-2" style="color: var(--text-secondary);">{{ $doc->name }}</td>
                        <td class="px-4 py-2 text-xs" style="color: var(--text-muted);">{{ $signedDate?->format('d M Y') ?? '—' }}</td>
                        <td class="px-4 py-2">
                            <div class="flex items-center gap-2">
                                <span class="inline-block w-2 h-2 rounded-full {{ $expiryIndicator }}"></span>
                                <input type="date" value="{{ $expiry?->format('Y-m-d') ?? '' }}"
                                       class="text-xs rounded-md py-0.5 px-1.5" style="background: var(--surface); border: 1px solid var(--border); color: var(--text-primary);"
                                       @change="saveExpiry({{ $doc->id }}, $event.target.value)">
                                <span class="text-emerald-400 text-[10px] transition-opacity" :class="savedIndicators[{{ $doc->id }} + '-expiry'] ? 'opacity-100' : 'opacity-0'">Saved</span>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif

        @if($activeLeases->isEmpty() && $completedLeaseDocs->isEmpty())
        <div class="ds-status-card p-4 text-center">
            <div class="text-sm italic" style="color: var(--text-muted);">No active leases yet.</div>
        </div>
        @endif
    </div>

    {{-- Rejected / Archived --}}
    @if(isset($rejected) && $rejected->isNotEmpty())
    <div id="section-rejected" class="space-y-3 scroll-mt-4 mt-6">
        <h3 class="text-sm font-semibold uppercase tracking-wider cursor-pointer transition-all duration-300" style="color: var(--text-muted);"
            @click="showRejected = !showRejected">
            Rejected ({{ $rejected->count() }})
            <span class="text-xs" x-text="showRejected ? '&#9660;' : '&#9654;'"></span>
        </h3>
        <div x-show="showRejected" x-collapse class="space-y-3">
            @foreach($rejected as $doc)
                @php $sigTemplate = $signatureTemplates->get($doc->id); @endphp
                <div class="rounded-md p-4 opacity-75" style="background: var(--surface); border: 1px solid var(--border);">
                    <div class="flex items-start justify-between">
                        <div>
                            <h4 class="font-medium line-through" style="color: var(--text-muted);">{{ $doc->name }}</h4>
                            @if($sigTemplate && $sigTemplate->rejection_reason)
                                <p class="text-xs text-red-400 mt-1">
                                    Rejected: {{ $sigTemplate->rejection_reason }}
                                </p>
                            @endif
                            @if($sigTemplate && $sigTemplate->rejected_at)
                                <p class="text-xs mt-0.5" style="color: var(--text-muted);">
                                    {{ $sigTemplate->rejected_at->format('d M Y H:i') }}
                                    @if($sigTemplate->rejectedBy)
                                        by {{ $sigTemplate->rejectedBy->name }}
                                    @endif
                                </p>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Empty state --}}
    @if($counts['draft'] === 0 && $counts['ready_to_sign'] === 0 && $counts['awaiting_signatures'] === 0 && $counts['completed'] === 0 && $counts['pending_approval'] === 0 && $activeLeases->isEmpty())
    <div class="ds-status-card p-6 text-center">
        <div class="text-sm" style="color: var(--text-secondary);">No rental documents found. Create a document from a rental template to get started.</div>
    </div>
    @endif

    {{-- Upload on Behalf Modal --}}
    <div x-show="showUploadOnBehalfModal" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <div class="rounded-md shadow-xl p-6 w-full max-w-md" style="background: var(--surface); border: 1px solid var(--border);" @click.away="showUploadOnBehalfModal = false">
            <h3 class="text-lg font-bold text-indigo-400 mb-2">Upload on Behalf</h3>
            <p class="text-sm mb-4" style="color: var(--text-secondary);">Uploading signed document for <strong x-text="uploadOnBehalfPartyName"></strong></p>

            <form method="POST"
                  :action="'/docuperfect/documents/' + uploadOnBehalfDocId + '/signatures/inspect/' + uploadOnBehalfRequestId + '/upload-on-behalf'"
                  enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="auto_approve" value="1">

                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Signed Document *</label>
                    <input type="file" name="files[]" multiple required
                           accept=".pdf,.jpg,.jpeg,.png"
                           class="w-full text-sm rounded-md px-3 py-2 file:mr-3 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-indigo-500/15 file:text-indigo-400 hover:file:bg-indigo-500/25" style="border: 1px solid var(--border); color: var(--text-primary);">
                    <p class="text-[10px] mt-1" style="color: var(--text-muted);">PDF, JPG or PNG. Max 20MB per file.</p>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">How was it received? *</label>
                    <select name="receive_method" required class="w-full rounded-md px-3 py-2 text-sm" style="background: var(--surface-2); border: 1px solid var(--border); color: var(--text-primary);">
                        <option value="">-- Select --</option>
                        <option value="whatsapp">WhatsApp</option>
                        <option value="email">Email</option>
                        <option value="in_person">In-person</option>
                    </select>
                </div>

                <div class="flex justify-end gap-3">
                    <button type="button" @click="showUploadOnBehalfModal = false"
                            class="px-4 py-2 text-sm transition-all duration-300" style="color: var(--text-secondary);">Cancel</button>
                    <button type="submit"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700 transition-all duration-300">
                        Upload &amp; Approve
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Rejection Modal --}}
    <div x-show="showRejectModal" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <div class="rounded-md shadow-xl p-6 w-full max-w-md" style="background: var(--surface); border: 1px solid var(--border);" @click.away="showRejectModal = false">
            <h3 class="text-lg font-bold text-red-400 mb-4">Reject Document</h3>
            <p class="text-sm mb-4" style="color: var(--text-secondary);" x-text="'Rejecting: ' + rejectDocName"></p>

            <form method="POST" :action="'/docuperfect/documents/' + rejectDocId + '/reject'">
                @csrf

                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Reason for Rejection *</label>
                    <textarea name="rejection_reason" rows="3" required minlength="5"
                              class="w-full rounded-md px-3 py-2 text-sm" style="background: var(--surface-2); border: 1px solid var(--border); color: var(--text-primary);"
                              placeholder="e.g. Wrong rental amount, tenant name misspelled..."></textarea>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">What would you like to do?</label>
                    <div class="space-y-2">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="action" value="revise" checked
                                   style="accent-color: var(--brand-button, #0ea5e9);">
                            <span class="text-sm" style="color: var(--text-primary);">Create a revised version (clone with fields, clear signatures)</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="action" value="archive"
                                   style="accent-color: var(--brand-button, #0ea5e9);">
                            <span class="text-sm" style="color: var(--text-primary);">Just archive it (no further action)</span>
                        </label>
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <button type="button" @click="showRejectModal = false"
                            class="px-4 py-2 text-sm transition-all duration-300" style="color: var(--text-secondary);">Cancel</button>
                    <button type="submit"
                            class="px-4 py-2 bg-red-600 text-white rounded-md text-sm font-medium hover:bg-red-700 transition-all duration-300">
                        Reject Document
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

{{-- Tile scroll-to-section --}}
<script>
function scrollToSection(sectionId) {
    var el = document.getElementById(sectionId);
    if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        el.classList.add('ring-2', 'ring-offset-2');
        el.style.setProperty('--tw-ring-color', 'var(--brand-icon, #0ea5e9)');
        setTimeout(function() {
            el.classList.remove('ring-2', 'ring-offset-2');
        }, 2000);
    }
}
</script>

{{-- Dashboard polling for live updates --}}
<script>
(function() {
    let lastKnownUpdate = null;
    let initialized = false;

    setInterval(async () => {
        try {
            const response = await fetch('{{ route("docuperfect.rental.statusCheck") }}');
            if (!response.ok) return;
            const data = await response.json();

            if (!initialized) {
                lastKnownUpdate = data.last_update || '';
                initialized = true;
            } else if (data.last_update && data.last_update !== lastKnownUpdate) {
                lastKnownUpdate = data.last_update;
                showUpdateBanner();
            }

            const badge = document.getElementById('pending-approval-count');
            if (badge && data.pending_approval_count !== undefined) {
                badge.textContent = data.pending_approval_count;
            }
        } catch (e) {
            // Silent fail
        }
    }, 60000);

    function showUpdateBanner() {
        if (document.getElementById('ds-update-banner')) return;
        const banner = document.createElement('div');
        banner.id = 'ds-update-banner';
        banner.className = 'fixed top-4 right-4 px-4 py-2 rounded-md shadow-lg z-50 flex items-center gap-3 text-sm text-white';
        banner.style.background = 'var(--brand-button, #0ea5e9)';
        banner.innerHTML = '<span>Signing status updated</span>'
            + '<button onclick="window.location.reload()" class="bg-white/20 hover:bg-white/30 px-3 py-1 rounded-md text-sm font-medium">Refresh</button>'
            + '<button onclick="this.parentElement.remove()" class="opacity-70 hover:opacity-100 ml-1">&times;</button>';
        document.body.appendChild(banner);
    }
})();
</script>
@endsection
