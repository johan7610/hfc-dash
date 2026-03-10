@extends('layouts.corex')

@section('content')
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

    <div style="background: var(--brand-default, #0b2a4a);" class="rounded-md px-6 py-4">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2">
            <div>
                <div class="text-sm text-white/60">
                    <a class="hover:underline text-white/60 transition-all duration-300" href="{{ route('bm.daily.summary', array_filter(['range'=>$range,'month'=>$month])) }}">&larr; Back to Branch Summary</a>
                </div>
                <h2 class="text-xl font-bold text-white leading-tight tracking-tight mt-1">{{ $def->name }}</h2>
                <div class="text-sm text-white/60">
                    {{ $branchName ?? ('Branch #' . (int)$branchId) }} &middot; {{ $start->toFormattedDateString() }} &rarr; {{ $end->toFormattedDateString() }}
                </div>
            </div>
        </div>
    </div>

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

    <div class="ds-status-card overflow-hidden" style="padding: 0;">
        <div class="px-5 py-4" style="border-bottom: 1px solid var(--border);">
            <h3 class="ds-section-header">By Agent</h3>
            <div class="text-xs mt-1" style="color: var(--text-muted);">Click the agent name or count to see dates performed.</div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm ds-table">
                <thead>
                    <tr>
                        <th class="text-left px-4 py-3">Agent</th>
                        <th class="text-right px-4 py-3">Count</th>
                        <th class="text-right px-4 py-3">Points</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $it)
                        <tr>
                            <td class="px-4 py-3 font-medium" style="color: var(--text-primary);">
                                <a class="hover:underline transition-all duration-300" style="color: var(--brand-icon, #0ea5e9);"
                                   href="{{ route('bm.daily.summary.activity.agent', array_filter(['definition'=>$def->id,'user'=>$it['user_id'],'range'=>$range,'month'=>$month])) }}">
                                    {{ $it['name'] }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a class="inline-flex items-center rounded-md px-2.5 py-1 font-semibold transition-all duration-300"
                                   style="background: var(--surface-2); color: var(--text-primary);"
                                   href="{{ route('bm.daily.summary.activity.agent', array_filter(['definition'=>$def->id,'user'=>$it['user_id'],'range'=>$range,'month'=>$month])) }}">
                                    {{ (int)$it['count'] }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-right" style="color: var(--text-primary);">{{ number_format((float)$it['points'], 0) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="p-6 text-center" style="color: var(--text-muted);">No entries in this range.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
