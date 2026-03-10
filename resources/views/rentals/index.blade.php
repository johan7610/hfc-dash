@extends('layouts.corex')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

    {{-- Page Header --}}
    <div style="background: var(--brand-default, #0b2a4a);" class="rounded-md px-6 py-5">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-white leading-tight tracking-tight">Rentals Register</h2>
                <div class="text-sm text-white/60 mt-1">All assigned rentals &mdash; not period-based</div>
            </div>
            <div class="flex items-center gap-6">
                <div class="text-right">
                    <div class="text-xs uppercase tracking-wide text-white/50 font-medium">Total rentals</div>
                    <div class="text-2xl font-bold text-white leading-tight mt-0.5">{{ $summary->total_count ?? 0 }}</div>
                </div>
                <div class="text-right">
                    <div class="text-xs uppercase tracking-wide text-white/50 font-medium">Commission (Excl VAT)</div>
                    <div class="text-2xl font-bold text-white leading-tight mt-0.5">R {{ number_format($summary->total_comm ?? 0, 2) }}</div>
                </div>
                <a href="{{ route('rentals.create') }}" class="corex-btn-primary">+ New Rental</a>
            </div>
        </div>
    </div>

    {{-- Per Agent Summary --}}
    <div class="ds-status-card" style="border-left-color: var(--brand-icon, #0ea5e9);">
        <h3 class="text-sm font-semibold mb-3" style="color: var(--text-primary);">Per Agent</h3>
        <div class="flex flex-wrap gap-3">
            @foreach($summary_per_agent as $a)
                <div class="rounded-md px-3 py-2 transition-all duration-300" style="background: var(--surface-2); border: 1px solid var(--border);">
                    <div class="font-semibold text-sm" style="color: var(--text-primary);">{{ data_get($a, 'name') }}</div>
                    <div class="text-xs mt-0.5" style="color: var(--text-secondary);">
                        {{ data_get($a, 'rental_count', 0) }} rentals &mdash;
                        R {{ number_format((float) data_get($a, 'total_comm', 0), 2) }}
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Rentals Table --}}
    <div class="rounded-md overflow-hidden" style="background: var(--surface); border: 1px solid var(--border);">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm ds-table">
                <thead>
                    <tr style="background: var(--surface-2);">
                        <th class="text-left px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Address</th>
                        <th class="text-left px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Lease Start</th>
                        <th class="text-left px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Lease End</th>
                        <th class="text-center px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">M2M</th>
                        <th class="text-center px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Active</th>
                        <th class="text-right px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Commission (excl)</th>
                        <th class="text-center px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Assist</th>
                        <th class="text-left px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Agents</th>
                        <th class="text-right px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Actions</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($rentals as $rental)
                    <tr class="transition-all duration-300" style="border-bottom: 1px solid var(--border);"
                        onmouseover="this.style.background='var(--surface-2)'" onmouseout="this.style.background='transparent'">

                        <td class="px-4 py-3 font-semibold" style="color: var(--text-primary);">
                            {{ $rental->lease_address }}
                        </td>

                        <td class="px-4 py-3" style="color: var(--text-secondary);">
                            {{ optional($rental->lease_start_date)->format('Y-m-d') }}
                        </td>

                        <td class="px-4 py-3" style="color: var(--text-secondary);">
                            {{ optional($rental->lease_end_date)->format('Y-m-d') }}
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
                            R {{ number_format(optional($rental->currentAmountVersion)->commission_excl ?? 0, 2) }}
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
                                <div class="text-sm">{{ $agent->name }}</div>
                            @endforeach
                        </td>

                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('rentals.edit', $rental->id) }}" class="corex-btn-outline text-xs px-2.5 py-1">
                                Edit
                            </a>
                        </td>

                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="px-4 py-12 text-center text-sm" style="color: var(--text-muted);">
                            No rentals found
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
