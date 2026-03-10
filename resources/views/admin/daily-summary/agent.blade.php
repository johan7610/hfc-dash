@extends('layouts.corex')

@section('content')
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

    {{-- Page Header --}}
    <div style="background: var(--brand-default, #0b2a4a);" class="rounded-md px-6 py-4">
        <div class="text-sm text-white/60 mb-1">
            <a class="hover:underline text-white/60 transition-all duration-300" href="{{ route('admin.daily.summary.activity.branch', array_filter(['definition'=>$def->id,'branch'=>$branchId,'range'=>$range,'month'=>$month])) }}">&larr; Back to Branch</a>
        </div>
        <div class="text-sm text-white/60 space-x-2">
            <a class="hover:underline transition-all duration-300" href="{{ route('admin.daily.summary', array_filter(['range'=>$range,'month'=>$month])) }}">Company Summary</a>
            <span>&rsaquo;</span>
            <a class="hover:underline transition-all duration-300" href="{{ route('admin.daily.summary.activity', array_filter(['definition'=>$def->id,'range'=>$range,'month'=>$month])) }}">{{ $def->name }}</a>
            <span>&rsaquo;</span>
            <a class="hover:underline transition-all duration-300" href="{{ route('admin.daily.summary.activity.branch', array_filter(['definition'=>$def->id,'branch'=>$branchId,'range'=>$range,'month'=>$month])) }}">{{ $branchName }}</a>
            <span>&rsaquo;</span>
            <span class="text-white/80">{{ $agentName }}</span>
        </div>

        <h2 class="text-xl font-bold text-white leading-tight tracking-tight mt-1">{{ $agentName }} &mdash; {{ $def->name }}</h2>
        <div class="text-sm text-white/60">
            {{ $start->toFormattedDateString() }} &rarr; {{ $end->toFormattedDateString() }}
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="ds-status-card">
            <div class="ds-label">Total Count</div>
            <div class="ds-value-xl">{{ (int)$totalCount }}</div>
        </div>
        <div class="ds-status-card">
            <div class="ds-label">Weight</div>
            <div class="ds-value-xl">{{ number_format((float)$def->weight, 2) }}</div>
        </div>
        <div class="ds-status-card">
            <div class="ds-label">Total Points</div>
            <div class="ds-value-xl">{{ number_format((float)$totalPoints, 0) }}</div>
        </div>
    </div>

    {{-- Dates Table --}}
    <div class="rounded-md overflow-hidden" style="background: var(--surface); border: 1px solid var(--border);">
        <div class="px-5 py-4" style="border-bottom: 1px solid var(--border);">
            <h3 class="text-sm font-semibold" style="color: var(--text-primary);">Dates Performed</h3>
            <div class="text-xs mt-1" style="color: var(--text-muted);">Newest first.</div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm ds-table">
                <thead>
                    <tr style="background: var(--surface-2);">
                        <th class="text-left px-4 py-2.5 text-xs font-semibold uppercase tracking-wide" style="color: var(--text-secondary);">Date</th>
                        <th class="text-right px-4 py-2.5 text-xs font-semibold uppercase tracking-wide" style="color: var(--text-secondary);">Count</th>
                        <th class="text-right px-4 py-2.5 text-xs font-semibold uppercase tracking-wide" style="color: var(--text-secondary);">Points</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $r)
                        <tr class="transition-all duration-300" style="border-bottom: 1px solid var(--border);"
                            onmouseover="this.style.background='var(--surface-2)'" onmouseout="this.style.background='transparent'">
                            <td class="px-4 py-2.5 font-medium" style="color: var(--text-primary);">
                                {{ \Illuminate\Support\Carbon::parse($r['date'])->format('D j M Y') }}
                            </td>
                            <td class="px-4 py-2.5 text-right" style="color: var(--text-primary);">{{ (int)$r['count'] }}</td>
                            <td class="px-4 py-2.5 text-right" style="color: var(--text-secondary);">{{ number_format((float)$r['points'], 0) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-4 py-6 text-center" style="color: var(--text-muted);">No entries in this range.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
