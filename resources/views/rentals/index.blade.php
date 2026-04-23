@extends('layouts.corex')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

    {{-- Page Header --}}
    <div class="rounded-md px-6 py-5" style="background: var(--brand-default, #0b2a4a);">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-white leading-tight">Rentals Register</h1>
                <p class="text-sm text-white/60">All assigned rentals &mdash; not period-based.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('rentals.create') }}" class="corex-btn-primary">+ New Rental</a>
            </div>
        </div>
    </div>

    {{-- Summary stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="rounded-md p-4" style="background: var(--surface); border: 1px solid var(--border);">
            <div class="text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Total rentals</div>
            <div class="text-[1.625rem] font-semibold mt-1" style="color: var(--text-primary);">
                {{ number_format($summary->total_count ?? 0) }}
            </div>
        </div>
        <div class="rounded-md p-4" style="background: var(--surface); border: 1px solid var(--border);">
            <div class="text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Commission (Excl VAT)</div>
            <div class="text-[1.625rem] font-semibold mt-1" style="color: var(--text-primary);">
                R {{ number_format($summary->total_comm ?? 0, 0) }}
            </div>
        </div>
    </div>

    {{-- Per Agent Summary --}}
    <div class="ds-status-card" style="border-left-color: var(--brand-icon, #0ea5e9);">
        <h3 class="text-sm font-semibold mb-3" style="color: var(--text-primary);">Per Agent</h3>
        @if(count($summary_per_agent) === 0)
            <p class="text-sm" style="color: var(--text-muted);">No agent splits yet.</p>
        @else
            <div class="flex flex-wrap gap-3">
                @foreach($summary_per_agent as $a)
                    <div class="rounded-md px-3 py-2" style="background: var(--surface-2); border: 1px solid var(--border);">
                        <div class="font-semibold text-sm" style="color: var(--text-primary);">{{ data_get($a, 'name') }}</div>
                        <div class="text-xs mt-0.5" style="color: var(--text-secondary);">
                            {{ number_format((int) data_get($a, 'rental_count', 0)) }} rentals &mdash;
                            R {{ number_format((float) data_get($a, 'total_comm', 0), 0) }}
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Rentals Table --}}
    @if($rentals->isEmpty())
        <div class="rounded-md py-12 px-6 text-center" style="background: var(--surface); border: 1px solid var(--border);">
            <div class="w-12 h-12 rounded-full mx-auto mb-4 flex items-center justify-center"
                 style="background: color-mix(in srgb, var(--brand-icon, #0ea5e9) 12%, transparent); color: var(--brand-icon, #0ea5e9);">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="w-6 h-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12 12 3l9.75 9m-17.25-.75V21h4.5v-6h6v6h4.5V11.25" />
                </svg>
            </div>
            <h3 class="text-base font-semibold mb-1" style="color: var(--text-primary);">No rentals yet</h3>
            <p class="text-sm mb-4" style="color: var(--text-muted);">Capture your first rental to start tracking lease income.</p>
            <a href="{{ route('rentals.create') }}" class="corex-btn-primary">+ New Rental</a>
        </div>
    @else
        <div class="rounded-md overflow-hidden" style="background: var(--surface); border: 1px solid var(--border);">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm ds-table">
                    <thead>
                        <tr style="background: var(--surface-2);">
                            <th class="text-left px-4 py-2.5 text-xs font-semibold uppercase tracking-wider" style="color: var(--text-muted);">Address</th>
                            <th class="text-left px-4 py-2.5 text-xs font-semibold uppercase tracking-wider" style="color: var(--text-muted);">Lease Start</th>
                            <th class="text-left px-4 py-2.5 text-xs font-semibold uppercase tracking-wider" style="color: var(--text-muted);">Lease End</th>
                            <th class="text-center px-4 py-2.5 text-xs font-semibold uppercase tracking-wider" style="color: var(--text-muted);">M2M</th>
                            <th class="text-center px-4 py-2.5 text-xs font-semibold uppercase tracking-wider" style="color: var(--text-muted);">Active</th>
                            <th class="text-right px-4 py-2.5 text-xs font-semibold uppercase tracking-wider" style="color: var(--text-muted);">Commission (Excl)</th>
                            <th class="text-center px-4 py-2.5 text-xs font-semibold uppercase tracking-wider" style="color: var(--text-muted);">Assist</th>
                            <th class="text-left px-4 py-2.5 text-xs font-semibold uppercase tracking-wider" style="color: var(--text-muted);">Agents</th>
                            <th class="text-right px-4 py-2.5 text-xs font-semibold uppercase tracking-wider" style="color: var(--text-muted);">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($rentals as $rental)
                        <tr style="border-top: 1px solid var(--border);">
                            <td class="px-4 py-3 font-semibold" style="color: var(--text-primary);">
                                {{ $rental->lease_address }}
                            </td>

                            <td class="px-4 py-3" style="color: var(--text-secondary);">
                                {{ optional($rental->lease_start_date)->format('Y-m-d') ?? '—' }}
                            </td>

                            <td class="px-4 py-3" style="color: var(--text-secondary);">
                                {{ optional($rental->lease_end_date)->format('Y-m-d') ?? '—' }}
                            </td>

                            <td class="px-4 py-3 text-center">
                                @if($rental->is_month_to_month)
                                    <span class="ds-badge ds-badge-info">M2M</span>
                                @else
                                    <span style="color: var(--text-muted);">&mdash;</span>
                                @endif
                            </td>

                            <td class="px-4 py-3 text-center">
                                @if($rental->is_active)
                                    <span class="ds-badge ds-badge-success">Active</span>
                                @else
                                    <span class="ds-badge ds-badge-default">Inactive</span>
                                @endif
                            </td>

                            <td class="px-4 py-3 text-right font-semibold" style="color: var(--text-primary);">
                                R {{ number_format(optional($rental->currentAmountVersion)->commission_excl ?? 0, 0) }}
                            </td>

                            <td class="px-4 py-3 text-center">
                                @if($rental->is_rental_assist)
                                    <span class="ds-badge ds-badge-info">Yes</span>
                                @else
                                    <span style="color: var(--text-muted);">&mdash;</span>
                                @endif
                            </td>

                            <td class="px-4 py-3" style="color: var(--text-secondary);">
                                @foreach($rental->agents as $agent)
                                    <div>{{ $agent->name }}</div>
                                @endforeach
                            </td>

                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('rentals.edit', $rental->id) }}" class="text-xs font-semibold" style="color: var(--brand-icon, #0ea5e9);">
                                    Edit
                                </a>
                            </td>

                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

</div>
@endsection
