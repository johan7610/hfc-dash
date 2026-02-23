@extends('layouts.app')

@section('content')
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

    <div class="flex items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">Finance Definitions</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                All formula definitions registered in the Finance Engine.
                <span class="font-medium">{{ $computedCount }}</span> computed values stored.
            </p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('admin.finance.audit.index') }}"
               class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 px-4 py-2 text-sm font-semibold hover:bg-slate-50 dark:hover:bg-slate-800">
                Audit History
            </a>
            <form method="POST" action="{{ route('admin.finance.recalculate') }}" class="inline"
                  onsubmit="return confirm('Run full recalculation for the current period? This may take a moment.')">
                @csrf
                <input type="hidden" name="period" value="{{ now()->format('Y-m') }}">
                <button type="submit"
                        class="rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 text-sm font-semibold">
                    Recalculate All ({{ now()->format('Y-m') }})
                </button>
            </form>
        </div>
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

    <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-900/40">
            <div class="font-semibold text-slate-900 dark:text-slate-100">Definitions ({{ $definitions->count() }})</div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100 dark:border-slate-800 text-left text-xs uppercase text-slate-500 dark:text-slate-400">
                        <th class="px-5 py-3">Key</th>
                        <th class="px-5 py-3">Entity Type</th>
                        <th class="px-5 py-3">Value Type</th>
                        <th class="px-5 py-3">Version</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Notes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($definitions as $def)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/40">
                            <td class="px-5 py-3 font-mono text-xs text-slate-800 dark:text-slate-200">{{ $def->key }}</td>
                            <td class="px-5 py-3 text-slate-600 dark:text-slate-400">{{ $def->entity_type }}</td>
                            <td class="px-5 py-3 text-slate-600 dark:text-slate-400">{{ $def->value_type }}</td>
                            <td class="px-5 py-3 text-slate-600 dark:text-slate-400">v{{ $def->version }}</td>
                            <td class="px-5 py-3">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                    {{ $def->status === 'active' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400' }}">
                                    {{ $def->status }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-xs text-slate-500 dark:text-slate-400 max-w-xs truncate">{{ $def->notes }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-8 text-center text-slate-400 dark:text-slate-500">
                                No definitions registered yet. Run a recalculation to auto-create them.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
