{{--
    F.3/F.7 — Stats strip (compacted to single-row 10 tiles).

    DESIGN SYSTEM COMPLIANCE: UI_DESIGN_SYSTEM.md v 2026-04-20
    Every colour resolves via a documented token from §1 with a fallback
    hex per §5.10. No hardcoded brand colours, no invented tokens.

    Row 1 (snapshot) + Row 2 (action presets) merged into one grid of 10
    tiles. Grid uses repeat(auto-fit, minmax(110px, 1fr)) so it stays
    single-row on 1440px+ desktops and wraps to two rows on narrower
    screens. Tile height intentionally reduced ~30% from F.2 to free
    vertical space (Johan F.3 approved).

    The "In stock" tile remains the only interactive Row-1 tile — clicking
    it toggles ?include_in_stock=1 (manager-only). Action-preset tiles
    (Row 2) click to set ?action_preset=<key>; the active preset
    highlights in info-teal.

    Spec: build-f-market-intelligence-redesign-spec.md §8.2.
--}}
@php
    $isManager = auth()->user()?->hasPermission('prospecting_setup.manage') ?? false;
    $kpis = $snapshotKpis ?? ['active'=>0,'buyer_matched'=>0,'in_stock'=>0,'new_today'=>0,'cross_listed'=>0];
    $presets = $actionPresetCounts ?? ['pitch_now_high'=>0,'pitch_now'=>0,'log_outcomes'=>0,'my_claims'=>0,'expiring'=>0];
    $activeActionPreset = $actionPreset ?? null;

    $urlWithPreset = function (string $key) {
        $params = array_merge(request()->except(['action_preset', 'page']), ['action_preset' => $key]);
        return route('market-intelligence.index', $params);
    };
    $urlClearPreset = route('market-intelligence.index', request()->except(['action_preset', 'page']));

    $urlToggleInStock = function () {
        $params = request()->except(['page']);
        if (request()->boolean('include_in_stock')) {
            unset($params['include_in_stock']);
        } else {
            $params['include_in_stock'] = '1';
        }
        return route('market-intelligence.index', $params);
    };

    // F.7 — defensive token + fallback pattern per UI_DESIGN_SYSTEM.md §5.10
    // so the tiles render correctly even if a token fails to resolve at runtime.
    $tileBaseStyle = 'background: var(--surface, #ffffff); border: 1px solid var(--border, rgba(0,0,0,0.07)); padding: 6px 8px; border-radius: 6px; min-width: 0;';
    $tileActiveStyle = 'background: color-mix(in srgb, var(--brand-icon, #0ea5e9) 12%, var(--surface, #ffffff)); border: 1px solid var(--brand-icon, #0ea5e9); padding: 6px 8px; border-radius: 6px; min-width: 0;';
    $labelStyle = 'font-size: 0.6125rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.02em; color: var(--text-muted, #9ca3af); margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;';
    $valueStyle = 'font-size: 1rem; font-weight: 600; color: var(--text-primary, #111827); line-height: 1.1;';

    $tiles = [
        ['type'=>'snapshot', 'label'=>'Active',        'value'=>$kpis['active'],        'accent'=>'var(--text-primary)'],
        ['type'=>'snapshot', 'label'=>'Buyer matched', 'value'=>$kpis['buyer_matched'], 'accent'=>'var(--ds-green, #10b981)'],
        ['type'=>'instock',  'label'=>'In stock',      'value'=>$kpis['in_stock'],      'accent'=>'var(--text-primary)'],
        ['type'=>'snapshot', 'label'=>'New today',     'value'=>$kpis['new_today'],     'accent'=>'var(--text-primary)'],
        ['type'=>'snapshot', 'label'=>'Cross-listed',  'value'=>$kpis['cross_listed'],  'accent'=>'var(--ds-amber, #f59e0b)'],
        ['type'=>'preset', 'key'=>'pitch_now_high', 'label'=>'Pitch now · high', 'value'=>$presets['pitch_now_high'], 'accent'=>'var(--ds-green, #10b981)'],
        ['type'=>'preset', 'key'=>'pitch_now',      'label'=>'Pitch now',         'value'=>$presets['pitch_now'],      'accent'=>'var(--ds-green, #10b981)'],
        ['type'=>'preset', 'key'=>'log_outcomes',   'label'=>'Log outcomes',      'value'=>$presets['log_outcomes'],   'accent'=>'var(--ds-amber, #f59e0b)'],
        ['type'=>'preset', 'key'=>'my_claims',      'label'=>'My claims',         'value'=>$presets['my_claims'],      'accent'=>'var(--brand-icon, #0ea5e9)'],
        ['type'=>'preset', 'key'=>'expiring',       'label'=>'Expiring',          'value'=>$presets['expiring'],       'accent'=>'var(--ds-crimson, #dc2626)'],
    ];
@endphp

<div class="mi-stats-strip"
     style="padding: 8px 12px; background: var(--surface-2, #f0f2f8); border-bottom: 1px solid var(--border, rgba(0,0,0,0.07));
            display: grid; grid-template-columns: repeat(auto-fit, minmax(110px, 1fr)); gap: 6px;">

    @foreach($tiles as $tile)
        @if($tile['type'] === 'snapshot')
            <div style="{{ $tileBaseStyle }}">
                <div style="{{ $labelStyle }}">{{ $tile['label'] }}</div>
                <div style="{{ $valueStyle }}; color: {{ $tile['value'] > 0 ? $tile['accent'] : 'var(--text-muted)' }};">{{ number_format($tile['value']) }}</div>
            </div>
        @elseif($tile['type'] === 'instock')
            @if($isManager)
            <a href="{{ $urlToggleInStock() }}"
               style="text-decoration: none; {{ request()->boolean('include_in_stock') ? $tileActiveStyle : $tileBaseStyle }} cursor: pointer; display: block;"
               title="Click to {{ request()->boolean('include_in_stock') ? 'hide' : 'include' }} in-stock listings (audit mode)">
                <div style="{{ $labelStyle }}">{{ $tile['label'] }}{{ request()->boolean('include_in_stock') ? ' · audit' : '' }}</div>
                <div style="{{ $valueStyle }}">{{ number_format($tile['value']) }}</div>
            </a>
            @else
            <div style="{{ $tileBaseStyle }}">
                <div style="{{ $labelStyle }}">{{ $tile['label'] }}</div>
                <div style="{{ $valueStyle }}">{{ number_format($tile['value']) }}</div>
            </div>
            @endif
        @else
            @php
                $isActive = $activeActionPreset === $tile['key'];
                $href = $isActive ? $urlClearPreset : $urlWithPreset($tile['key']);
            @endphp
            <a href="{{ $href }}"
               style="text-decoration: none; {{ $isActive ? $tileActiveStyle : $tileBaseStyle }} display: block; cursor: pointer;"
               title="{{ $isActive ? 'Click to clear filter' : 'Click to filter listings to this preset' }}">
                <div style="{{ $labelStyle }}; color: {{ $isActive ? 'var(--brand-icon)' : 'var(--text-muted)' }};">{{ $tile['label'] }}</div>
                <div style="{{ $valueStyle }}; color: {{ $tile['value'] > 0 ? $tile['accent'] : 'var(--text-muted)' }};">{{ number_format($tile['value']) }}</div>
            </a>
        @endif
    @endforeach
</div>
