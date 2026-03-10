@extends('layouts.corex')

@section('corex-content')

<div style="background: var(--brand-default, #0b2a4a);" class="rounded-md px-6 py-4 mb-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2">
        <div>
            <h2 class="text-xl font-bold text-white leading-tight">{{ $pageTitle }}</h2>
            <div class="text-sm text-white/60">Compiled presentation packs</div>
        </div>
        <a href="{{ route('presentations.index') }}"
           class="corex-btn-outline" style="color:#fff; border-color:rgba(255,255,255,0.3); background:transparent;">
            &larr; All Presentations
        </a>
    </div>
</div>

{{-- FILTERS --}}
<form method="GET" class="mb-4 flex flex-wrap gap-2 items-end">
    @if($isAdmin)
        <div>
            <label class="ds-label block mb-1">Branch</label>
            <select name="branch_id" class="pres-select px-2 py-1.5 text-xs">
                <option value="">All branches</option>
                @foreach($branches as $branch)
                    <option value="{{ $branch->id }}"
                        {{ ($filters['branch_id'] ?? '') == $branch->id ? 'selected' : '' }}>
                        {{ $branch->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="ds-label block mb-1">User ID</label>
            <input type="number" name="user_id" value="{{ $filters['user_id'] ?? '' }}"
                   placeholder="Any"
                   class="pres-input px-2 py-1.5 text-xs w-24">
        </div>
    @endif

    <div>
        <label class="ds-label block mb-1">Presentation ID</label>
        <input type="number" name="presentation_id" value="{{ $filters['presentation_id'] ?? '' }}"
               placeholder="Any"
               class="pres-input px-2 py-1.5 text-xs w-28">
    </div>

    <div>
        <label class="ds-label block mb-1">Period (YYYY-MM)</label>
        <input type="text" name="period" value="{{ $filters['period'] ?? '' }}"
               placeholder="e.g. 2026-02"
               class="pres-input px-2 py-1.5 text-xs w-28">
    </div>

    <button type="submit" class="corex-btn-primary" style="padding:0.375rem 0.75rem; font-size:0.75rem;">
        Filter
    </button>

    @if(array_filter($filters))
        <a href="{{ request()->url() }}"
           class="corex-btn-outline text-xs px-3 py-1.5">
            Clear
        </a>
    @endif
</form>

{{-- TABLE --}}
<div class="ds-status-card overflow-hidden" style="border-left-color: var(--ds-cyan); padding:0;">
    <table class="ds-table w-full text-sm">
        <thead>
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase" style="color: var(--text-muted);">Compiled</th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase" style="color: var(--text-muted);">Presentation</th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase" style="color: var(--text-muted);">Compiled by</th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase" style="color: var(--text-muted);">Blueprint</th>
                <th class="px-4 py-3 text-right text-xs font-medium uppercase" style="color: var(--text-muted);">Links</th>
            </tr>
        </thead>
        <tbody class="divide-y" style="border-color: var(--border);">
            @forelse($versions as $version)
                <tr class="transition-all duration-300" onmouseover="this.style.background='var(--surface-2)'" onmouseout="this.style.background='transparent'">
                    <td class="px-4 py-3 text-xs whitespace-nowrap" style="color: var(--text-secondary);">
                        {{ $version->compiled_at?->format('Y-m-d H:i') ?? '—' }}
                    </td>
                    <td class="px-4 py-3 text-xs" style="color: var(--text-primary);">
                        {{ $version->presentation?->title ?? '#' . $version->presentation_id }}
                        <span class="ml-1" style="color: var(--text-muted);">({{ $version->presentation?->suburb ?? '' }})</span>
                    </td>
                    <td class="px-4 py-3 text-xs" style="color: var(--text-secondary);">
                        {{ $version->compiledBy?->name ?? 'User #' . $version->compiled_by }}
                    </td>
                    <td class="px-4 py-3 text-xs" style="color: var(--text-muted);">
                        {{ $version->blueprint_version ?? '—' }}
                    </td>
                    <td class="px-4 py-3 text-right text-xs">
                        @if($version->presentation)
                            <a href="{{ route('presentations.show', $version->presentation_id) }}"
                               class="hover:underline mr-3" style="color: var(--brand-icon, #0ea5e9);">
                                Presentation →
                            </a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-4 py-8 text-center text-sm italic" style="color: var(--text-muted);">
                        No compiled versions found.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($versions->hasPages())
    <div class="mt-4">
        {{ $versions->links() }}
    </div>
@endif

@endsection
