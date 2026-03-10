@extends('layouts.corex')

@section('content')
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

    {{-- Page Header --}}
    <div style="background: var(--brand-default, #0b2a4a);" class="rounded-md px-6 py-4">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2">
            <div>
                <h2 class="text-xl font-bold text-white leading-tight tracking-tight">My Deals</h2>
                <div class="text-sm text-white/60">Deals where you are allocated on listing and/or selling side.</div>
            </div>
            <span class="inline-flex items-center rounded-md px-3 py-1 text-xs font-semibold text-white"
                  style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2);">
                {{ $deals->count() }} deals
            </span>
        </div>
    </div>

    {{-- Deal Register Table --}}
    <div class="rounded-md overflow-hidden" style="background: var(--surface); border: 1px solid var(--border);">

        {{-- Table Header --}}
        <div class="px-5 py-4" style="border-bottom: 1px solid var(--border);">
            <h3 class="text-sm font-semibold" style="color: var(--text-primary);">Deal Register</h3>
            <div class="text-xs mt-1" style="color: var(--text-muted);">Read-only. You can add remarks in the log.</div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm ds-table">
                <thead>
                    <tr style="background: var(--surface-2);">
                        <th class="text-left px-5 py-2.5 text-xs font-semibold uppercase tracking-wide" style="color: var(--text-secondary);">Deal</th>
                        <th class="text-left px-5 py-2.5 text-xs font-semibold uppercase tracking-wide" style="color: var(--text-secondary);">Property</th>
                        <th class="text-left px-5 py-2.5 text-xs font-semibold uppercase tracking-wide" style="color: var(--text-secondary);">Branch</th>
                        <th class="text-right px-5 py-2.5 text-xs font-semibold uppercase tracking-wide" style="color: var(--text-secondary);">Selling Price</th>
                        <th class="text-right px-5 py-2.5 text-xs font-semibold uppercase tracking-wide" style="color: var(--text-secondary);">Our Total (Ex VAT)</th>
                        <th class="text-right px-5 py-2.5 text-xs font-semibold uppercase tracking-wide" style="color: var(--text-secondary);">Actions</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach($deals->sortByDesc('deal_no') as $deal)
                        @php
                            $b = $branches->firstWhere('id', $deal->branch_id);
                            $acceptedMap = ['P'=>'Pending','D'=>'Declined','G'=>'Granted','R'=>'Registered'];
                            $acceptedLabel = $deal->accepted_status
                                ? ($acceptedMap[$deal->accepted_status] ?? $deal->accepted_status)
                                : '—';

                            $asVal = (string)($deal->accepted_status ?? '');
                            $statusBadge = 'ds-badge-default';
                            if ($asVal === 'G') $statusBadge = 'ds-badge-success';
                            elseif ($asVal === 'R') $statusBadge = 'ds-badge-info';
                            elseif ($asVal === 'D') $statusBadge = 'ds-badge-danger';
                            elseif ($asVal === 'P') $statusBadge = 'ds-badge-warning';

                            $csVal = (string)($deal->commission_status ?? '');
                            $commBadge = 'ds-badge-default';
                            if ($csVal === 'Paid') $commBadge = 'ds-badge-paid';
                            elseif ($csVal === 'Not Paid') $commBadge = 'ds-badge-notpaid';
                            elseif ($csVal === 'Loss') $commBadge = 'ds-badge-loss';
                        @endphp
                        <tr class="transition-all duration-300" style="border-bottom: 1px solid var(--border);"
                            onmouseover="this.style.background='var(--surface-2)'" onmouseout="this.style.background='transparent'">
                            <td class="px-5 py-3">
                                <div class="font-bold" style="color: var(--brand-icon, #0ea5e9);">#{{ $deal->deal_no }}</div>
                                <div class="mt-1 flex items-center gap-1.5">
                                    <span class="ds-badge {{ $statusBadge }}">{{ $acceptedLabel }}</span>
                                    <span class="ds-badge {{ $commBadge }}">{{ $csVal ?: '—' }}</span>
                                </div>
                            </td>

                            <td class="px-5 py-3">
                                <div style="color: var(--text-primary);">{{ \Illuminate\Support\Str::limit($deal->property_address, 60) }}</div>
                                <div class="mt-0.5 text-xs" style="color: var(--text-muted);">{{ $deal->seller_name }} &rarr; {{ $deal->buyer_name }}</div>
                            </td>

                            <td class="px-5 py-3">
                                <div class="font-medium" style="color: var(--text-primary);">{{ $b?->name ?? '—' }}</div>
                                <div class="mt-0.5 text-xs" style="color: var(--text-muted);">Period: <span style="color: var(--text-secondary);">{{ $deal->period ?: '—' }}</span></div>
                            </td>

                            <td class="px-5 py-3 text-right">
                                <div class="font-semibold" style="color: var(--text-primary);">R {{ number_format((float)$deal->property_value, 0) }}</div>
                            </td>

                            <td class="px-5 py-3 text-right">
                                <div class="font-semibold" style="color: var(--text-primary);">R {{ number_format((float)$deal->totalOurCommission(), 0) }}</div>
                                <div class="mt-0.5 text-xs" style="color: var(--text-muted);">Company + agents (Ex VAT)</div>
                            </td>

                            <td class="px-5 py-3 text-right">
                                <a href="{{ route('agent.deals.log', $deal) }}"
                                   class="corex-btn-primary text-xs px-3 py-1.5">
                                    Log
                                </a>
                            </td>
                        </tr>
                    @endforeach

                    @if($deals->isEmpty())
                        <tr>
                            <td colspan="6" class="px-5 py-10 text-center text-sm" style="color: var(--text-muted);">No deals allocated to you yet.</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
