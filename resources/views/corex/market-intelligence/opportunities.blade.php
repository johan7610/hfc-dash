{{--
    MIC Phase D4 — Opportunities tab. Replaces the D1 stub.
    Spec: .ai/specs/mic-complete-spec.md §5.4.
--}}
@extends('layouts.corex-app')

@section('corex-content')
<div style="max-width: 1640px; margin: 0 auto; padding: 0 20px;">

    @include('corex.market-intelligence.partials.tabs')

    @if(session('status'))
        <div style="margin-bottom: 12px; padding: 8px 12px; font-size: 0.8125rem;
                    background: color-mix(in srgb, var(--ds-green, #10b981) 12%, transparent);
                    color: var(--ds-green, #10b981);
                    border: 1px solid var(--ds-green, #10b981); border-radius: 4px;">
            {{ session('status') }}
        </div>
    @endif
    @if(session('error'))
        <div style="margin-bottom: 12px; padding: 8px 12px; font-size: 0.8125rem;
                    background: color-mix(in srgb, var(--ds-crimson, #dc2626) 12%, transparent);
                    color: var(--ds-crimson, #dc2626);
                    border: 1px solid var(--ds-crimson, #dc2626); border-radius: 4px;">
            {{ session('error') }}
        </div>
    @endif

    @include('corex.market-intelligence.partials.opportunities-stats-strip')
    @include('corex.market-intelligence.partials.opportunities-filter-chips')
    @include('corex.market-intelligence.partials.opportunities-secondary-filters')

    <div style="display: flex; align-items: baseline; justify-content: space-between; margin-bottom: 6px;">
        <h2 style="font-size: 0.875rem; font-weight: 600; color: var(--text-primary); margin: 0;">
            Showing {{ $tps->total() }} {{ $tps->total() === 1 ? 'property' : 'properties' }}
            @if(($activeFilter ?? 'all') !== 'all')
                <span style="font-weight: 400; color: var(--text-muted);">· filter: {{ str_replace('_', ' ', $activeFilter) }}</span>
            @endif
        </h2>
        <span style="font-size: 0.6875rem; color: var(--text-muted);">
            Sorted by strong-match count, most recent first
        </span>
    </div>

    @include('corex.market-intelligence.partials.opportunities-list')

</div>
@endsection
