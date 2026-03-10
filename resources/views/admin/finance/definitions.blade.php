@extends('layouts.corex')

@section('content')
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

    {{-- Page Header --}}
    <div style="background: var(--brand-default, #0b2a4a);" class="rounded-md px-6 py-4">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <div>
                <h2 class="text-xl font-bold text-white leading-tight tracking-tight">Finance Definitions</h2>
                <div class="text-sm text-white/60">
                    All formula definitions registered in the Finance Engine.
                    <span class="font-medium text-white/80">{{ $computedCount }}</span> computed values stored.
                </div>
            </div>

            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route('admin.finance.audit.index') }}" class="corex-btn-outline text-sm">Audit History</a>
                <form method="POST" action="{{ route('admin.finance.recalculate') }}" class="flex items-center gap-2"
                      id="recalcForm">
                    @csrf
                    <input type="hidden" name="mode" id="recalcMode" value="single">
                    <select name="period"
                            class="rounded-md border-0 text-white text-sm px-3 py-1.5 transition-all duration-300 [&>option]:text-slate-900"
                            style="background: rgba(255,255,255,0.1);">
                        @foreach($availablePeriods as $p)
                            <option value="{{ $p }}" {{ $p === now()->format('Y-m') ? 'selected' : '' }}>
                                {{ \Carbon\Carbon::createFromFormat('Y-m', $p)->format('F Y') }}
                            </option>
                        @endforeach
                    </select>
                    <button type="submit"
                            onclick="document.getElementById('recalcMode').value='single'"
                            class="corex-btn-primary text-sm whitespace-nowrap">
                        Recalculate Period
                    </button>
                    <button type="submit"
                            onclick="if(!confirm('This will recalculate ALL periods with deals. This may take a while. Continue?')){event.preventDefault();return;}document.getElementById('recalcMode').value='all'"
                            class="rounded-md text-white px-4 py-2 text-sm font-semibold whitespace-nowrap transition-all duration-300"
                            style="background: #d97706;"
                            onmouseover="this.style.background='#b45309'"
                            onmouseout="this.style.background='#d97706'">
                        Recalculate ALL
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Definitions Table --}}
    <div class="rounded-md overflow-hidden" style="background: var(--surface); border: 1px solid var(--border);">
        <div class="px-5 py-4" style="border-bottom: 1px solid var(--border);">
            <h3 class="text-sm font-semibold" style="color: var(--text-primary);">Definitions ({{ $definitions->count() }})</h3>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm ds-table">
                <thead>
                    <tr style="background: var(--surface-2, var(--surface));">
                        <th class="text-left px-4 py-3" style="color: var(--text-secondary);">Key</th>
                        <th class="text-left px-4 py-3" style="color: var(--text-secondary);">Entity Type</th>
                        <th class="text-left px-4 py-3" style="color: var(--text-secondary);">Value Type</th>
                        <th class="text-left px-4 py-3" style="color: var(--text-secondary);">Version</th>
                        <th class="text-left px-4 py-3" style="color: var(--text-secondary);">Status</th>
                        <th class="text-left px-4 py-3" style="color: var(--text-secondary);">Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($definitions as $def)
                        <tr class="transition-all duration-300" style="border-bottom: 1px solid var(--border);">
                            <td class="px-4 py-3 font-mono text-xs" style="color: var(--text-primary);">{{ $def->key }}</td>
                            <td class="px-4 py-3" style="color: var(--text-secondary);">{{ $def->entity_type }}</td>
                            <td class="px-4 py-3" style="color: var(--text-secondary);">{{ $def->value_type }}</td>
                            <td class="px-4 py-3" style="color: var(--text-secondary);">v{{ $def->version }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium
                                    {{ $def->status === 'active' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400' }}">
                                    {{ $def->status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs max-w-xs truncate" style="color: var(--text-muted);">{{ $def->notes }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-sm" style="color: var(--text-muted);">
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
