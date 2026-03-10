@extends('layouts.corex')

@section('content')
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

    <div style="background: var(--brand-default, #0b2a4a);" class="rounded-md px-6 py-4">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <div>
                <div class="text-sm text-white/60">
                    <a class="hover:underline text-white/60 transition-all duration-300" href="{{ route('bm.my.dashboard') }}">&larr; Dashboard</a>
                </div>
                <h2 class="text-xl font-bold text-white leading-tight tracking-tight mt-1">Daily Activity Summary (Branch)</h2>
                <div class="text-sm text-white/60">
                    {{ $branchName ?? ('Branch #' . (int)$branchId) }} &middot; {{ $start->toFormattedDateString() }} &rarr; {{ $end->toFormattedDateString() }}
                </div>
            </div>

            <form method="GET" action="{{ route('bm.daily.summary') }}" class="flex flex-wrap items-center gap-2">
                <select name="range" class="rounded-md border border-white/20 bg-white/10 text-white text-sm px-3 py-1.5 transition-all duration-300 [&>option]:text-black">
                    <option value="7d"  {{ $range==='7d' ? 'selected' : '' }}>Last 7 days</option>
                    <option value="month" {{ $range==='month' ? 'selected' : '' }}>This month</option>
                    <option value="3m"  {{ $range==='3m' ? 'selected' : '' }}>Last 3 months</option>
                    <option value="6m"  {{ $range==='6m' ? 'selected' : '' }}>Last 6 months</option>
                    <option value="12m" {{ $range==='12m' ? 'selected' : '' }}>Last 12 months</option>
                </select>

                @if($range === 'month')
                    <input type="text" name="month" value="{{ $month ?? '' }}" placeholder="YYYY-MM"
                           class="w-28 rounded-md border border-white/20 bg-white/10 text-white text-sm px-3 py-1.5 placeholder:text-white/40 transition-all duration-300" />
                @endif

                <button class="corex-btn-primary text-sm">Apply</button>
            </form>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="ds-status-card">
            <div class="ds-label">Total Count</div>
            <div class="ds-value-xl">{{ (int)$grandCount }}</div>
        </div>
        <div class="ds-status-card">
            <div class="ds-label">Total Points</div>
            <div class="ds-value-xl">{{ number_format((float)$grandPoints, 0) }}</div>
        </div>
        <div class="ds-status-card">
            <div class="ds-label">Activities Tracked</div>
            <div class="ds-value-xl">{{ count($items) }}</div>
        </div>
    </div>

    <div class="ds-status-card overflow-hidden" style="padding: 0;">
        <div class="px-5 py-4" style="border-bottom: 1px solid var(--border);">
            <h3 class="ds-section-header">By Activity</h3>
            <div class="text-xs mt-1" style="color: var(--text-muted);">Click the activity name or count to drill down to agents.</div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm ds-table">
                <thead>
                    <tr>
                        <th class="text-left px-4 py-3">Activity</th>
                        <th class="text-right px-4 py-3">Count</th>
                        <th class="text-right px-4 py-3">Points</th>
                        <th class="text-right px-4 py-3">% (Points)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $it)
                        <tr>
                            <td class="px-4 py-3 font-medium" style="color: var(--text-primary);">
                                <a class="hover:underline transition-all duration-300" style="color: var(--brand-icon, #0ea5e9);"
                                   href="{{ route('bm.daily.summary.activity', array_filter(['definition'=>$it['id'],'range'=>$range,'month'=>$month])) }}">
                                    {{ $it['name'] }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a class="inline-flex items-center rounded-md px-2.5 py-1 font-semibold transition-all duration-300"
                                   style="background: var(--surface-2); color: var(--text-primary);"
                                   href="{{ route('bm.daily.summary.activity', array_filter(['definition'=>$it['id'],'range'=>$range,'month'=>$month])) }}">
                                    {{ (int)$it['count'] }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-right" style="color: var(--text-primary);">{{ number_format((float)$it['points'], 0) }}</td>
                            <td class="px-4 py-3 text-right" style="color: var(--text-secondary);">{{ number_format((float)$it['pct_points'], 1) }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
