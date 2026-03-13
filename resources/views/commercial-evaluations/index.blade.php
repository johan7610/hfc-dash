@extends('layouts.corex')

@section('corex-content')
<div class="max-w-6xl mx-auto">

    {{-- Page header --}}
    <div class="rounded-md px-6 py-4 mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-3" style="background: var(--brand-default, #0b2a4a);">
        <div>
            <h2 class="text-xl font-bold text-white tracking-tight">Commercial Market Evaluations</h2>
            <p class="text-sm text-white/60 mt-0.5">Evaluate commercial, industrial, hospitality & agricultural properties</p>
        </div>
        <div class="flex items-center gap-3">
            <form method="GET" action="{{ route('commercial-evaluations.index') }}">
                <select name="status" onchange="this.form.submit()" class="px-3 py-2 rounded-md text-sm transition-all duration-300" style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.25); color: #fff;">
                    <option value="active" {{ request('status', 'active') === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="archived" {{ request('status') === 'archived' ? 'selected' : '' }}>Archived</option>
                </select>
            </form>
            <a href="{{ route('commercial-evaluations.create') }}" class="corex-btn-outline text-sm" style="color: #fff; border-color: rgba(255,255,255,0.3);">
                + New Evaluation
            </a>
        </div>
    </div>

    {{-- Content --}}
    <div class="rounded-md overflow-hidden" style="border: 1px solid var(--border); background: var(--surface);">
        @if($evaluations->isEmpty())
            <div class="px-6 py-12 text-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto mb-3" style="color: var(--text-muted);" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z" />
                </svg>
                <p class="text-sm mb-3" style="color: var(--text-muted);">No commercial evaluations yet.</p>
                <a href="{{ route('commercial-evaluations.create') }}" class="corex-btn-primary text-sm">
                    Create Your First Evaluation
                </a>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="ds-table min-w-full text-sm">
                    <thead>
                        <tr style="background: var(--surface-2);">
                            <th class="text-left px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Property</th>
                            <th class="text-left px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Type</th>
                            <th class="text-left px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Town</th>
                            <th class="text-left px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Status</th>
                            <th class="text-right px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Asking Price</th>
                            <th class="text-right px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Recommended Range</th>
                            <th class="text-left px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Created</th>
                            <th class="text-right px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($evaluations as $eval)
                        <tr class="transition-all duration-300" style="border-bottom: 1px solid var(--border);" onmouseover="this.style.background='var(--surface-2)'" onmouseout="this.style.background='transparent'">
                            <td class="px-4 py-3">
                                <a href="{{ route('commercial-evaluations.show', $eval) }}" class="ds-agent-link font-medium transition-all duration-300">
                                    {{ $eval->property_name }}
                                </a>
                            </td>
                            <td class="px-4 py-3">
                                <span class="ds-badge {{ \App\Models\CommercialEvaluation::propertyTypeBadgeColor($eval->property_type) }}">
                                    {{ \App\Models\CommercialEvaluation::propertyTypeLabel($eval->property_type) }}
                                </span>
                            </td>
                            <td class="px-4 py-3" style="color: var(--text-secondary);">{{ $eval->town ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @if($showArchived)
                                    <span class="ds-badge ds-badge-warning">Archived</span>
                                @else
                                    <span class="ds-badge {{ \App\Models\CommercialEvaluation::statusBadgeColor($eval->status) }}">
                                        {{ ucfirst($eval->status) }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-xs" style="color: var(--text-secondary);">{{ $eval->asking_price_display }}</td>
                            <td class="px-4 py-3 text-right font-mono text-xs" style="color: var(--text-secondary);">{{ $eval->recommended_range_display }}</td>
                            <td class="px-4 py-3 text-xs" style="color: var(--text-muted);">{{ $eval->created_at->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 text-right">
                                @if($showArchived)
                                    <form method="POST" action="{{ route('commercial-evaluations.restore', $eval->id) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="corex-btn-primary text-xs px-2.5 py-1">Restore</button>
                                    </form>
                                @else
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('commercial-evaluations.show', $eval) }}" class="corex-btn-outline text-xs px-2.5 py-1">View</a>
                                        <a href="{{ route('commercial-evaluations.edit', $eval) }}" class="corex-btn-outline text-xs px-2.5 py-1">Edit</a>
                                        <form method="POST" action="{{ route('commercial-evaluations.destroy', $eval) }}" class="inline" onsubmit="return confirm('Delete this evaluation?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="corex-btn-outline text-xs px-2.5 py-1" style="color: var(--ds-crimson, #ef4444); border-color: color-mix(in srgb, var(--ds-crimson, #ef4444) 30%, transparent);">Delete</button>
                                        </form>
                                    </div>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($evaluations->hasPages())
                <div class="px-4 py-3" style="border-top: 1px solid var(--border);">
                    {{ $evaluations->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
