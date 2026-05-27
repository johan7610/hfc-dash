{{--
    MIC Phase D6 — Market Pulse tab.
    Folds the legacy /admin/p24 surface into the unified MIC URL.
    Spec: .ai/specs/mic-complete-spec.md §5.6.
--}}
@extends('layouts.corex-app')

@section('corex-content')
<div style="max-width: 1640px; margin: 0 auto; padding: 0 20px;">

    @include('corex.market-intelligence.partials.tabs')

    @if(session('success'))
        <div style="margin-bottom: 12px; padding: 8px 12px; font-size: 0.8125rem;
                    background: color-mix(in srgb, var(--ds-green, #10b981) 12%, transparent);
                    color: var(--ds-green, #10b981);
                    border: 1px solid var(--ds-green, #10b981); border-radius: 4px;">
            {{ session('success') }}
        </div>
    @endif

    {{-- Hero --}}
    <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 16px;
                margin-bottom: 16px;">
        <div>
            <h1 style="font-size: 1.25rem; font-weight: 600; color: var(--text-primary); margin: 0 0 4px 0;">Market Pulse</h1>
            <p style="font-size: 0.8125rem; color: var(--text-muted); margin: 0;">
                Live P24 firehose · {{ number_format($suburbStats->count()) }} suburbs ·
                {{ number_format($kpis['active_listings']) }} active listings.
            </p>
        </div>
        @permission('manage_p24')
            <form method="POST" action="{{ route('admin.p24.import') }}" style="margin: 0;">
                @csrf
                <button type="submit"
                        style="padding: 8px 16px; font-size: 0.8125rem; font-weight: 500;
                               background: var(--brand-button); color: #fff;
                               border: none; border-radius: 4px; cursor: pointer;">
                    Run import
                </button>
            </form>
        @endpermission
    </div>

    @include('corex.market-intelligence.partials.market-pulse-kpis')
    @include('corex.market-intelligence.partials.market-pulse-suburbs')
    @include('corex.market-intelligence.partials.market-pulse-listings')
    @include('corex.market-intelligence.partials.market-pulse-price-changes')
    @include('corex.market-intelligence.partials.market-pulse-import-log')

</div>

@include('corex.market-intelligence.partials.mic-slideover')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-mic-suburb]').forEach(function (el) {
            el.addEventListener('click', function (e) {
                if (e.target.closest('a, button')) return;
                e.preventDefault();
                const suburb = el.dataset.micSuburb;
                if (suburb) {
                    window.dispatchEvent(new CustomEvent('mic-open-suburb', { detail: { suburb } }));
                }
            });
            el.style.cursor = 'pointer';
        });
    });
</script>
@endsection
