@extends('layouts.corex')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

    {{-- Page Header --}}
    <div style="background: var(--brand-default, #0b2a4a);" class="rounded-md px-6 py-4">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <div>
                <h2 class="text-xl font-bold text-white leading-tight tracking-tight">Listings per Agent</h2>
                <div class="text-sm text-white/60">Read-only overview from imported listing stock.</div>
            </div>

            <form method="get" class="flex flex-wrap gap-3 items-end">
                <div>
                    <label class="block text-xs text-white/60 mb-1">Status</label>
                    <select name="status" class="rounded-md border-0 bg-white/10 text-white text-sm px-3 py-1.5 transition-all duration-300 [&>option]:text-slate-900">
                        @foreach(['active'=>'Active (contains active/for sale)','all'=>'All'] as $k=>$label)
                            <option value="{{ $k }}" @selected(($status ?? 'active') === $k)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-white/60 mb-1">Source</label>
                    <input name="source" value="{{ $source ?? 'propcon' }}" class="w-40 rounded-md border-0 bg-white/10 text-white text-sm px-3 py-1.5 transition-all duration-300 placeholder:text-white/40" />
                </div>
                <button class="corex-btn-primary text-sm">Apply</button>
            </form>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="ds-status-card">
            <div class="ds-label">Active listings</div>
            <div class="ds-value-xl">{{ number_format((int)($totals->listing_count ?? 0)) }}</div>
        </div>
        <div class="ds-status-card">
            <div class="ds-label">Total asking value</div>
            <div class="ds-value-lg">R {{ number_format(((int)($totals->total_value_cents ?? 0))/100, 0) }}</div>
        </div>
        <div class="ds-status-card">
            <div class="ds-label">Agents with stock</div>
            <div class="ds-value-xl">{{ number_format(count($rows ?? [])) }}</div>
        </div>
    </div>

    {{-- Agent Breakdown Table --}}
    <div class="rounded-md overflow-hidden" style="border: 1px solid var(--border); background: var(--surface);">
        <div class="px-4 py-3 flex items-center justify-between" style="border-bottom: 1px solid var(--border);">
            <div class="text-sm font-medium" style="color: var(--text-primary);">Agent breakdown</div>
            <div class="text-xs" style="color: var(--text-muted);">Click an agent to drill in</div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm ds-table">
                <thead>
                    <tr style="background: var(--surface-2);">
                        <th class="text-left px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Agent</th>
                        <th class="text-right px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Active</th>
                        <th class="text-right px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Asking value</th>
                        <th class="text-left px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Mandates</th>
                        <th class="text-left px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Top types</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $r)
                        <tr class="transition-all duration-300" style="border-bottom: 1px solid var(--border);" onmouseover="this.style.background='var(--surface-2)'" onmouseout="this.style.background='transparent'">
                            <td class="px-4 py-3">
                                <div class="font-medium" style="color: var(--text-primary);">{{ $r['name'] }}</div>
                                <div class="text-xs" style="color: var(--text-muted);">{{ $r['email'] }}</div>
                            </td>
                            <td class="px-4 py-3 text-right font-semibold" style="color: var(--text-primary);">{{ number_format($r['listing_count']) }}</td>
                            <td class="px-4 py-3 text-right" style="color: var(--text-primary);">R {{ number_format($r['total_value_cents']/100, 0) }}</td>
                            <td class="px-4 py-3">
                                @php $m = $r['mandates'] ?? []; @endphp
                                @if(count($m))
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach($m as $k => $c)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs transition-all duration-300" style="background: var(--surface-2); border: 1px solid var(--border); color: var(--text-secondary);">
                                                <span class="font-medium" style="color: var(--text-primary);">{{ $k }}</span>
                                                <span>{{ $c }}</span>
                                            </span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-xs" style="color: var(--text-muted);">(none)</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @php $t = $r['top_types'] ?? []; @endphp
                                @if(count($t))
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach($t as $x)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs transition-all duration-300" style="background: var(--surface-2); border: 1px solid var(--border); color: var(--text-secondary);">
                                                <span class="font-medium" style="color: var(--text-primary);">{{ $x['type'] }}</span>
                                                <span>{{ $x['c'] }}</span>
                                            </span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-xs" style="color: var(--text-muted);">(none)</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a class="corex-btn-primary text-sm"
                                   href="{{ route('admin.listings.agents.show', ['user' => $r['user_id'], 'status' => $status ?? 'active', 'source' => $source ?? 'propcon']) }}">
                                    View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-4 py-8 text-center" style="color: var(--text-muted);" colspan="6">
                                No listing stock found for this filter.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
