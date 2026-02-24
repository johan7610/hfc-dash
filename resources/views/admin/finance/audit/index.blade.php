@extends('layouts.nexus')

@section('content')
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

    <div class="flex items-start justify-between gap-4 flex-wrap">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">Finance Audit Center</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">View past audit runs or run a new audit for any period.</p>
        </div>

        <form method="GET" action="{{ route('admin.finance.audit.index') }}" class="flex items-center gap-2">
            <select name="period"
                    class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 py-2 text-sm">
                <option value="">All periods</option>
                @foreach($availablePeriods as $p)
                    <option value="{{ $p }}" {{ request('period') === $p ? 'selected' : '' }}>
                        {{ \Carbon\Carbon::createFromFormat('Y-m', $p)->format('F Y') }}
                    </option>
                @endforeach
            </select>
            <button class="rounded-xl bg-slate-900 dark:bg-slate-700 text-white px-4 py-2 text-sm font-semibold">Filter</button>
            @if(request('period'))
                <a href="{{ route('admin.finance.audit.index') }}" class="text-sm text-slate-500 hover:text-slate-800 dark:hover:text-slate-200">Clear</a>
            @endif
        </form>
    </div>

    @if(session('status'))
        <div class="rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 px-4 py-3 text-sm text-emerald-800 dark:text-emerald-200">
            {{ session('status') }}
        </div>
    @endif

    @if(session('error'))
        <div class="rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-800 dark:text-red-200">
            {{ session('error') }}
        </div>
    @endif

    {{-- Run New Audit --}}
    <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-5 shadow-sm">
        <div class="font-semibold text-slate-900 dark:text-slate-100 mb-3">Run New Audit</div>
        <form method="POST" action="{{ route('admin.finance.recalculate') }}" class="flex items-center gap-3"
              onsubmit="return confirm('Run recalculation and audit for the selected period?')">
            @csrf
            <input type="hidden" name="mode" value="single">
            <select name="period"
                    class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 py-2 text-sm">
                @foreach($availablePeriods as $p)
                    <option value="{{ $p }}" {{ $p === now()->format('Y-m') ? 'selected' : '' }}>
                        {{ \Carbon\Carbon::createFromFormat('Y-m', $p)->format('F Y') }}
                    </option>
                @endforeach
            </select>
            <button type="submit"
                    class="rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 text-sm font-semibold">
                Audit This Period
            </button>
        </form>
    </div>

    {{-- Audit Runs Table --}}
    <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-900/40">
            <div class="font-semibold text-slate-900 dark:text-slate-100">Audit Runs</div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100 dark:border-slate-800 text-slate-600 dark:text-slate-400 bg-white dark:bg-slate-950">
                        <th class="text-left px-4 py-3">Run</th>
                        <th class="text-left px-4 py-3">Period</th>
                        <th class="text-left px-4 py-3">Status</th>
                        <th class="text-right px-4 py-3">Items</th>
                        <th class="text-right px-4 py-3">Errors</th>
                        <th class="text-right px-4 py-3">Warnings</th>
                        <th class="text-right px-4 py-3">Pass</th>
                        <th class="text-left px-4 py-3">Started</th>
                        <th class="text-left px-4 py-3">Finished</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($runs as $run)
                        @php $passCount = $run->items_count - $run->errors_count - $run->warnings_count; @endphp
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/40">
                            <td class="px-4 py-3">
                                <a href="{{ route('admin.finance.audit.run', $run) }}"
                                   class="font-semibold text-blue-600 dark:text-blue-400 hover:underline">
                                    #{{ $run->id }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-slate-700 dark:text-slate-300">
                                {{ \Carbon\Carbon::createFromFormat('Y-m', $run->period)->format('F Y') }}
                            </td>
                            <td class="px-4 py-3">
                                @if($run->status === 'complete')
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 dark:bg-emerald-900/30 px-2 py-0.5 text-xs font-semibold text-emerald-700 dark:text-emerald-400">complete</span>
                                @elseif($run->status === 'failed')
                                    <span class="inline-flex items-center rounded-full bg-red-50 dark:bg-red-900/30 px-2 py-0.5 text-xs font-semibold text-red-700 dark:text-red-400">failed</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-amber-50 dark:bg-amber-900/30 px-2 py-0.5 text-xs font-semibold text-amber-700 dark:text-amber-400">{{ $run->status }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right text-slate-700 dark:text-slate-300">{{ number_format($run->items_count) }}</td>
                            <td class="px-4 py-3 text-right">
                                @if($run->errors_count > 0)
                                    <span class="font-semibold text-red-600 dark:text-red-400">{{ $run->errors_count }}</span>
                                @else
                                    <span class="text-slate-400">0</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if($run->warnings_count > 0)
                                    <span class="font-semibold text-amber-600 dark:text-amber-400">{{ $run->warnings_count }}</span>
                                @else
                                    <span class="text-slate-400">0</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if($passCount > 0)
                                    <span class="font-semibold text-emerald-600 dark:text-emerald-400">{{ $passCount }}</span>
                                @else
                                    <span class="text-slate-400">0</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-500 text-xs">{{ $run->started_at?->format('d M Y H:i') ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-500 text-xs">{{ $run->finished_at?->format('d M Y H:i') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-8 text-center text-slate-400 dark:text-slate-600">No audit runs found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($runs->hasPages())
            <div class="px-5 py-4 border-t border-slate-100 dark:border-slate-800">
                {{ $runs->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
