{{-- Phase 3g V1 — CoreX Map module (Leaflet + OSM/Esri).
     Spec: .ai/specs/presentations.md (Phase 3g build prompt). --}}
@extends('layouts.corex-app')

@push('head')
{{-- Leaflet 1.9.4 + MarkerCluster 1.5.3, free-tier OSM + Esri tiles.
     Must load in <head> BEFORE the body inline init script — otherwise
     the init runs while `L` is still undefined. --}}
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
      integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
{{-- Phase 3g V2 — heatmap overlay. --}}
<script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>
@endpush

@section('corex-content')
<div id="corex-map-root" style="position: relative; height: calc(100vh - 64px); margin: -16px -20px -16px; display: flex; flex-direction: column; overflow: hidden; min-height: 0;">

    {{-- ── Header bar ────────────────────────────────────────────────────── --}}
    <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; background: var(--surface); border-bottom: 1px solid var(--border); flex-shrink: 0; z-index: 500;">
        <div style="display: flex; align-items: center; gap: 16px;">
            <h1 style="font-size: 1.05rem; font-weight: 600; color: var(--text-primary); margin: 0;">CoreX Map</h1>
            <div id="map-loading-pill" style="display: none; padding: 4px 10px; font-size: 0.6875rem; font-weight: 500; background: var(--surface-2); color: var(--text-secondary); border-radius: 999px;">Loading pins…</div>
        </div>
        <div style="display: flex; align-items: center; gap: 8px;">
            {{-- Base-layer toggle --}}
            <div id="base-layer-toggle" style="display: inline-flex; background: var(--surface-2); border: 1px solid var(--border); border-radius: 6px; padding: 2px;">
                <button data-base="streets" class="base-pill active" style="padding: 4px 10px; font-size: 0.75rem; font-weight: 500; background: var(--brand-button); color: #fff; border: 0; border-radius: 4px; cursor: pointer;">Streets</button>
                <button data-base="satellite" class="base-pill" style="padding: 4px 10px; font-size: 0.75rem; font-weight: 500; background: transparent; color: var(--text-secondary); border: 0; border-radius: 4px; cursor: pointer;">Satellite</button>
            </div>
            {{-- View-mode toggle --}}
            <div id="view-mode-toggle" style="display: inline-flex; background: var(--surface-2); border: 1px solid var(--border); border-radius: 6px; padding: 2px;">
                <button data-mode="agent" class="mode-pill active" style="padding: 4px 10px; font-size: 0.75rem; font-weight: 500; background: var(--brand-button); color: #fff; border: 0; border-radius: 4px; cursor: pointer;">Agent View</button>
                <button data-mode="seller" class="mode-pill" style="padding: 4px 10px; font-size: 0.75rem; font-weight: 500; background: transparent; color: var(--text-secondary); border: 0; border-radius: 4px; cursor: pointer;">Seller View</button>
            </div>
            <button id="reset-bounds-btn" style="padding: 6px 10px; font-size: 0.75rem; font-weight: 500; color: var(--text-secondary); background: var(--surface-2); border: 1px solid var(--border); border-radius: 4px; cursor: pointer;">Reset to HFC area</button>
        </div>
    </div>

    {{-- ── Seller View banner ────────────────────────────────────────────── --}}
    <div id="seller-banner" style="display: none; padding: 6px 16px; background: color-mix(in srgb, var(--ds-amber, #d97706) 12%, transparent); color: var(--ds-amber, #d97706); border-bottom: 1px solid var(--ds-amber, #d97706); font-size: 0.75rem; text-align: center;">
        Seller view active — owner/contact info hidden
    </div>

    {{-- ── Body ──────────────────────────────────────────────────────────── --}}
    <div style="display: flex; flex: 1; overflow: hidden;">

        {{-- Left rail --}}
        <aside id="map-left-rail" style="width: 240px; flex-shrink: 0; background: var(--surface); border-right: 1px solid var(--border); padding: 12px; overflow-y: auto;">
            <div style="font-size: 0.6875rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); font-weight: 600; margin-bottom: 8px;">Layers</div>
            <div id="layer-list">
                @php
                    $layerDefs = [
                        ['key' => 'hfc_listings',    'label' => 'HFC Listings',    'colour' => '#00d4aa', 'letter' => 'H'],
                        ['key' => 'sold_comps',      'label' => 'Sold Comps',      'colour' => '#3b82f6', 'letter' => 'S'],
                        ['key' => 'active_listings', 'label' => 'Active Listings', 'colour' => '#f59e0b', 'letter' => 'A'],
                        ['key' => 'mic_subjects',    'label' => 'MIC Subjects',    'colour' => '#64748b', 'letter' => 'M'],
                        ['key' => 'scheme_owners',   'label' => 'Scheme Owners',   'colour' => '#8b5cf6', 'letter' => 'O', 'sensitive' => true],
                    ];
                @endphp
                @foreach($layerDefs as $l)
                <label data-layer="{{ $l['key'] }}"
                       @if($l['sensitive'] ?? false) data-sensitive="1" @endif
                       style="display: flex; align-items: center; gap: 8px; padding: 6px 6px; border-radius: 4px; cursor: pointer; font-size: 0.8125rem;">
                    <input type="checkbox" checked data-layer-cb="{{ $l['key'] }}" style="margin: 0;">
                    <span style="display: inline-flex; align-items: center; justify-content: center; width: 18px; height: 18px; background: {{ $l['colour'] }}; color: #fff; border-radius: 50%; font-size: 0.6875rem; font-weight: 700;">{{ $l['letter'] }}</span>
                    <span style="flex: 1; color: var(--text-primary);">{{ $l['label'] }}</span>
                    <span data-layer-count="{{ $l['key'] }}" style="font-size: 0.6875rem; color: var(--text-muted); font-variant-numeric: tabular-nums;">—</span>
                </label>
                @endforeach
            </div>
            <div id="layer-cap-notice" style="display: none; margin-top: 10px; padding: 8px; font-size: 0.6875rem; color: var(--ds-amber, #d97706); background: color-mix(in srgb, var(--ds-amber, #d97706) 8%, transparent); border-radius: 4px;"></div>

            {{-- Phase 3g V2 Part A — display mode radio. --}}
            <div style="margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--border);">
                <div style="font-size: 0.6875rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); font-weight: 600; margin-bottom: 8px;">Display mode</div>
                <div id="display-mode-group" style="display: flex; flex-direction: column; gap: 4px;">
                    @foreach(['pins' => 'Pins', 'heatmap' => 'Heatmap', 'both' => 'Both'] as $key => $label)
                    <label style="display: flex; align-items: center; gap: 8px; padding: 4px 0; cursor: pointer; font-size: 0.8125rem;">
                        <input type="radio" name="display-mode" value="{{ $key }}" {{ $key === 'pins' ? 'checked' : '' }} style="margin: 0;">
                        <span style="color: var(--text-primary);">{{ $label }}</span>
                    </label>
                    @endforeach
                </div>
            </div>

            {{-- Phase 3g V2 Part B — filters section, collapsible. --}}
            <div style="margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--border);">
                <button id="filters-toggle" type="button"
                        style="display: flex; align-items: center; gap: 6px; width: 100%; padding: 4px 0; background: none; border: 0; cursor: pointer; text-align: left; font-size: 0.6875rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); font-weight: 600;">
                    <span id="filters-caret" style="display: inline-block; transition: transform 150ms;">▸</span>
                    <span style="flex: 1;">Filters</span>
                    <span id="filters-count" style="display: none; font-size: 0.625rem; padding: 1px 6px; background: var(--brand-button); color: #fff; border-radius: 999px; text-transform: none; letter-spacing: 0;">0</span>
                </button>
                <div id="filters-body" style="display: none; margin-top: 10px;">
                    <div style="margin-bottom: 12px;">
                        <div style="font-size: 0.6875rem; color: var(--text-secondary); margin-bottom: 4px;">Date range</div>
                        <div style="display: flex; gap: 6px; align-items: center; font-size: 0.75rem;">
                            <input type="number" id="filter-year-from" min="2018" max="2030" value="{{ now()->year - 5 }}" style="width: 60px; padding: 3px 6px; border: 1px solid var(--border); border-radius: 3px; background: var(--surface-2); color: var(--text-primary); font-size: 0.75rem;">
                            <span style="color: var(--text-muted);">to</span>
                            <input type="number" id="filter-year-to" min="2018" max="2030" value="{{ now()->year }}" style="width: 60px; padding: 3px 6px; border: 1px solid var(--border); border-radius: 3px; background: var(--surface-2); color: var(--text-primary); font-size: 0.75rem;">
                        </div>
                    </div>

                    <div style="margin-bottom: 12px;">
                        <div style="font-size: 0.6875rem; color: var(--text-secondary); margin-bottom: 4px;">Property type</div>
                        <div style="display: flex; flex-direction: column; gap: 3px; font-size: 0.75rem;">
                            @foreach(['house' => 'House', 'sectional' => 'Apartment / Sectional', 'townhouse' => 'Townhouse', 'vacant' => 'Vacant Land'] as $val => $label)
                            <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                                <input type="checkbox" data-filter-type="{{ $val }}" checked style="margin: 0;">
                                <span style="color: var(--text-primary);">{{ $label }}</span>
                            </label>
                            @endforeach
                        </div>
                    </div>

                    <div style="margin-bottom: 12px;">
                        <div style="font-size: 0.6875rem; color: var(--text-secondary); margin-bottom: 4px;">Price band (R)</div>
                        <div style="display: flex; gap: 6px; align-items: center; font-size: 0.75rem;">
                            <input type="number" id="filter-price-min" min="0" max="10000000" step="100000" value="0" style="width: 88px; padding: 3px 6px; border: 1px solid var(--border); border-radius: 3px; background: var(--surface-2); color: var(--text-primary); font-size: 0.75rem;">
                            <span style="color: var(--text-muted);">–</span>
                            <input type="number" id="filter-price-max" min="0" max="10000000" step="100000" value="10000000" style="width: 88px; padding: 3px 6px; border: 1px solid var(--border); border-radius: 3px; background: var(--surface-2); color: var(--text-primary); font-size: 0.75rem;">
                        </div>
                    </div>

                    <div style="margin-bottom: 12px;">
                        <div style="font-size: 0.6875rem; color: var(--text-secondary); margin-bottom: 4px;">Bedrooms</div>
                        <div id="filter-bedrooms" style="display: flex; gap: 4px;">
                            @foreach([1, 2, 3, 4, 5] as $b)
                            <button type="button" data-bed="{{ $b }}" class="bed-pill" data-on="1"
                                style="padding: 4px 8px; font-size: 0.75rem; font-weight: 500; background: var(--brand-button); color: #fff; border: 0; border-radius: 4px; cursor: pointer;">{{ $b === 5 ? '5+' : $b }}</button>
                            @endforeach
                        </div>
                    </div>

                    <button type="button" id="filter-clear"
                        style="width: 100%; padding: 6px 10px; font-size: 0.75rem; font-weight: 500; color: var(--text-secondary); background: var(--surface-2); border: 1px solid var(--border); border-radius: 4px; cursor: pointer;">
                        Clear filters
                    </button>
                </div>
            </div>

            {{-- Phase 3h Step 10 — demo data toggle. Default ON locally + on
                 staging; default OFF in production. The data attribute below
                 lets the JS read the env-aware default. --}}
            <div style="margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--border);">
                <label style="display: flex; align-items: center; gap: 8px; padding: 4px 0; cursor: pointer; font-size: 0.8125rem;">
                    <input type="checkbox" id="demo-toggle"
                           data-default-on="{{ app()->environment('production') ? '0' : '1' }}"
                           style="margin: 0;">
                    <span style="flex: 1; color: var(--text-primary);">Show demo data</span>
                </label>
                <div style="font-size: 0.6875rem; color: var(--text-muted); margin-top: 2px;">Synthetic pins for testing. Toggle off to see real data only.</div>
            </div>

            <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--border); font-size: 0.6875rem; color: var(--text-muted);">
                <div style="font-weight: 600; margin-bottom: 4px;">Tips</div>
                <ul style="padding-left: 16px; margin: 0; line-height: 1.5;">
                    <li>Pan / zoom to refresh pins</li>
                    <li>Click a pin for details</li>
                    <li>Seller View hides owner data</li>
                </ul>
            </div>
        </aside>

        {{-- Main canvas --}}
        <main style="flex: 1; position: relative; background: #e5e7eb;">
            <div id="corex-map" style="position: absolute; inset: 0;"></div>
            <div id="empty-state" style="display: none; position: absolute; inset: 50% 0 0 0; transform: translateY(-50%); text-align: center; color: var(--text-muted); font-size: 0.875rem; pointer-events: none;">
                No data in this area yet — try importing CMA reports for this suburb.
            </div>

            {{-- Phase 3g V2 Part A5 — heatmap legend. Inverted gradient:
                 red = sparse (ops should target this area), green = dense. --}}
            <div id="heat-legend" style="display: none; position: absolute; bottom: 16px; right: 16px; background: var(--surface); border: 1px solid var(--border); border-radius: 6px; padding: 8px 10px; font-size: 0.6875rem; color: var(--text-secondary); box-shadow: 0 2px 6px rgba(0,0,0,.12); z-index: 1000;">
                <div style="font-weight: 600; color: var(--text-primary); margin-bottom: 4px;">Coverage density</div>
                <div style="display: flex; align-items: center; gap: 6px;">
                    <div style="width: 80px; height: 8px; border-radius: 4px; background: linear-gradient(to right, #dc2626, #f59e0b, #84cc16, #16a34a);"></div>
                </div>
                <div style="display: flex; justify-content: space-between; gap: 6px; margin-top: 3px; font-size: 0.625rem;">
                    <span>Sparse</span><span>Limited</span><span>Covered</span><span>Dense</span>
                </div>
            </div>
        </main>

        {{-- Right detail panel --}}
        <aside id="map-detail-panel"
               style="width: 360px; flex-shrink: 0; background: var(--surface); border-left: 1px solid var(--border); transform: translateX(100%); transition: transform 220ms ease; overflow-y: auto; position: relative;">
            <div style="display: flex; align-items: flex-start; justify-content: space-between; padding: 14px 16px; border-bottom: 1px solid var(--border); position: sticky; top: 0; background: var(--surface); z-index: 1;">
                <div style="flex: 1; min-width: 0;">
                    <div id="detail-title" style="font-size: 0.9375rem; font-weight: 600; color: var(--text-primary); overflow: hidden; text-overflow: ellipsis;"></div>
                    <div id="detail-subtitle" style="font-size: 0.75rem; color: var(--text-muted); margin-top: 2px;"></div>
                </div>
                <button id="detail-close-btn" aria-label="Close panel" style="margin-left: 8px; padding: 4px; background: transparent; border: 0; color: var(--text-muted); cursor: pointer; font-size: 1rem; line-height: 1;">×</button>
            </div>
            <div style="padding: 14px 16px;">
                <div id="detail-address" style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 12px; display: none;"></div>
                <div id="detail-facts"></div>
                <div id="detail-sensitive" style="display: none; margin-top: 14px; padding: 10px; background: color-mix(in srgb, var(--ds-purple, #8b5cf6) 8%, transparent); border-left: 3px solid var(--ds-purple, #8b5cf6); border-radius: 4px;">
                    <div style="font-size: 0.625rem; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; color: var(--ds-purple, #8b5cf6); margin-bottom: 6px;">Agent only</div>
                    <div id="detail-sensitive-facts"></div>
                </div>
                <div id="detail-relationships" style="margin-top: 14px;"></div>
            </div>
        </aside>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    // ── Leaflet load guard ────────────────────────────────────────────────
    if (typeof L === 'undefined') {
        console.error('CoreX Map: Leaflet failed to load. Check network / ad-blocker.');
        var mapEl = document.getElementById('corex-map');
        if (mapEl) {
            mapEl.innerHTML = '<div style="padding:24px;color:#64748b;font-size:0.875rem;line-height:1.5;">'
                + '<strong>Map library failed to load.</strong><br>'
                + 'Disable ad-blockers for this host and retry, or check network connectivity.'
                + '</div>';
        }
        return;
    }

    // ── Config / constants ────────────────────────────────────────────────
    const HFC_BOUNDS = { south: -31.0, north: -30.4, west: 30.0, east: 30.9 };
    const PINS_URL = @json(route('corex.map.pins'));
    const CACHE_MAX = 5;

    const LAYER_COLOURS = {
        hfc_listings:    '#00d4aa',
        sold_comps:      '#3b82f6',
        active_listings: '#f59e0b',
        mic_subjects:    '#64748b',
        scheme_owners:   '#8b5cf6',
    };
    const LAYER_LETTERS = {
        hfc_listings: 'H', sold_comps: 'S', active_listings: 'A',
        mic_subjects: 'M', scheme_owners: 'O',
    };

    // ── State ─────────────────────────────────────────────────────────────
    let viewMode = localStorage.getItem('corex.map.view_mode') || 'agent';
    let baseLayerKey = localStorage.getItem('corex.map.base_layer') || 'streets';

    // Phase 3h Step 10 — demo-data toggle. Default comes from a data attr
    // on the checkbox so the server can decide ON/OFF per environment.
    const demoToggleEl = document.getElementById('demo-toggle');
    const demoDefaultOn = (demoToggleEl?.dataset.defaultOn ?? '1') === '1';
    let includeDemo = (function () {
        const stored = localStorage.getItem('corex.map.include_demo');
        if (stored === '0') return false;
        if (stored === '1') return true;
        return demoDefaultOn;
    })();
    if (demoToggleEl) demoToggleEl.checked = includeDemo;

    // Phase 3g V2 — display mode + filter state.
    const CURRENT_YEAR = {{ now()->year }};
    const FILTER_DEFAULTS = {
        yearFrom: CURRENT_YEAR - 5,
        yearTo:   CURRENT_YEAR,
        types:    ['house','sectional','townhouse','vacant'],
        priceMin: 0,
        priceMax: 10000000,
        bedrooms: [1,2,3,4,5],
    };
    let displayMode = localStorage.getItem('corex.map.display_mode') || 'pins';
    let filters = loadFiltersFromStorage();
    let heatLayer = null;

    function loadFiltersFromStorage() {
        try {
            const raw = localStorage.getItem('corex.map.filters_v1');
            if (!raw) return { ...FILTER_DEFAULTS };
            const f = JSON.parse(raw);
            return {
                yearFrom: Number.isInteger(f.yearFrom) ? f.yearFrom : FILTER_DEFAULTS.yearFrom,
                yearTo:   Number.isInteger(f.yearTo)   ? f.yearTo   : FILTER_DEFAULTS.yearTo,
                types:    Array.isArray(f.types)    && f.types.length    ? f.types    : [...FILTER_DEFAULTS.types],
                priceMin: Number.isInteger(f.priceMin) ? f.priceMin : FILTER_DEFAULTS.priceMin,
                priceMax: Number.isInteger(f.priceMax) ? f.priceMax : FILTER_DEFAULTS.priceMax,
                bedrooms: Array.isArray(f.bedrooms) && f.bedrooms.length ? f.bedrooms : [...FILTER_DEFAULTS.bedrooms],
            };
        } catch (e) {
            return { ...FILTER_DEFAULTS };
        }
    }
    function persistFilters() {
        localStorage.setItem('corex.map.filters_v1', JSON.stringify(filters));
    }
    function countActiveFilters() {
        let n = 0;
        if (filters.yearFrom !== FILTER_DEFAULTS.yearFrom) n++;
        if (filters.yearTo   !== FILTER_DEFAULTS.yearTo)   n++;
        if (filters.types.length !== FILTER_DEFAULTS.types.length) n++;
        if (filters.priceMin !== FILTER_DEFAULTS.priceMin) n++;
        if (filters.priceMax !== FILTER_DEFAULTS.priceMax) n++;
        if (filters.bedrooms.length !== FILTER_DEFAULTS.bedrooms.length) n++;
        return n;
    }
    function syncFilterUi() {
        document.getElementById('filter-year-from').value = filters.yearFrom;
        document.getElementById('filter-year-to').value   = filters.yearTo;
        document.querySelectorAll('[data-filter-type]').forEach(cb => {
            cb.checked = filters.types.includes(cb.dataset.filterType);
        });
        document.getElementById('filter-price-min').value = filters.priceMin;
        document.getElementById('filter-price-max').value = filters.priceMax;
        document.querySelectorAll('#filter-bedrooms .bed-pill').forEach(btn => {
            const on = filters.bedrooms.includes(parseInt(btn.dataset.bed, 10));
            btn.dataset.on = on ? '1' : '0';
            btn.style.background = on ? 'var(--brand-button)' : 'var(--surface-2)';
            btn.style.color = on ? '#fff' : 'var(--text-secondary)';
        });
        const cnt = countActiveFilters();
        const badge = document.getElementById('filters-count');
        badge.textContent = String(cnt);
        badge.style.display = cnt > 0 ? 'inline-block' : 'none';
    }
    const enabledLayers = new Set([
        'hfc_listings', 'sold_comps', 'active_listings', 'mic_subjects', 'scheme_owners',
    ]);
    const clusterByLayer = {}; // key → L.markerClusterGroup
    const cache = []; // [{ key, payload }]
    let fetchTimer = null;
    let inFlight = null;

    // ── Map init ──────────────────────────────────────────────────────────
    const map = L.map('corex-map', { zoomControl: true, attributionControl: true }).fitBounds([
        [HFC_BOUNDS.south, HFC_BOUNDS.west],
        [HFC_BOUNDS.north, HFC_BOUNDS.east],
    ]);

    const streetsLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19,
    });
    const satelliteLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: 'Tiles © Esri',
        maxZoom: 19,
    });
    const baseLayers = { streets: streetsLayer, satellite: satelliteLayer };
    baseLayers[baseLayerKey].addTo(map);

    // Pre-create one cluster group per layer so toggles act independently.
    // Map hotfix:
    //   - spiderfyOnMaxZoom + spiderfyDistanceMultiplier: when 2+ pins of the
    //     SAME layer share GPS (e.g. 14 owners of a scheme), they fan out
    //     clickably at max zoom.
    //   - maxClusterRadius dropped to 40px so only genuinely-close pins cluster.
    //   - zIndexOffset by priority so HFC pins render on top of MIC/owner pins
    //     even when the cross-layer coalesce can't physically separate them.
    const Z_INDEX_BY_LAYER = {
        hfc_listings:    1000,
        active_listings:  800,
        sold_comps:       600,
        mic_subjects:     400,
        scheme_owners:    200,
    };
    ['hfc_listings', 'sold_comps', 'active_listings', 'mic_subjects', 'scheme_owners'].forEach(k => {
        clusterByLayer[k] = L.markerClusterGroup({
            disableClusteringAtZoom: 14,
            maxClusterRadius: 40,
            chunkedLoading: true,
            spiderfyOnMaxZoom: true,
            showCoverageOnHover: false,
            zoomToBoundsOnClick: true,
            spiderfyDistanceMultiplier: 1.8,
        });
        // Per-cluster z-index offset — affects markers added to the group.
        clusterByLayer[k].on('layeradd', (e) => {
            if (e.layer && typeof e.layer.setZIndexOffset === 'function') {
                e.layer.setZIndexOffset(Z_INDEX_BY_LAYER[k] || 0);
            }
        });
        clusterByLayer[k].addTo(map);
    });

    const LAYER_NAMES = {
        hfc_listings:    'HFC Listing',
        sold_comps:      'Sold Comp',
        active_listings: 'Active Listing',
        mic_subjects:    'MIC Subject',
        scheme_owners:   'Scheme Owner',
    };

    // ── Helpers ───────────────────────────────────────────────────────────
    /**
     * Build the pin icon HTML from a full pin payload (not just the layer
     * key). Handles two server-driven states:
     *   - pin.colocated_count > 0 → render a "+N" badge in the top-right
     *   - pin.shifted=true        → render a dashed ring around the pin
     */
    function pinIcon(pin) {
        const colour = LAYER_COLOURS[pin.layer] || '#64748b';
        const letter = LAYER_LETTERS[pin.layer] || '?';
        const isShifted = !!pin.shifted;
        const colocated = pin.colocated_count || 0;

        // Outer wrapper has positioning context for the +N badge.
        const borderStyle = isShifted
            ? '2px dashed #ffffff'
            : '2px solid #ffffff';
        const ringShadow = isShifted
            ? '0 0 0 2px rgba(148,163,184,0.55), 0 1px 3px rgba(0,0,0,.4)'
            : '0 1px 3px rgba(0,0,0,.4)';

        let html = '<div style="position:relative;display:flex;align-items:center;justify-content:center;width:22px;height:22px;background:'
            + colour + ';color:#fff;border:' + borderStyle + ';border-radius:50%;font-size:11px;font-weight:700;box-shadow:'
            + ringShadow + ';">' + letter;

        if (colocated > 0) {
            html += '<span style="position:absolute;top:-7px;right:-9px;background:#fff;color:#0f172a;font-size:9px;font-weight:700;border-radius:8px;padding:1px 4px;line-height:1;box-shadow:0 1px 2px rgba(0,0,0,.3);white-space:nowrap;">+'
                + colocated + '</span>';
        }
        html += '</div>';

        return L.divIcon({ html: html, className: 'corex-pin', iconSize: [22, 22], iconAnchor: [11, 11] });
    }

    function currentBounds() {
        const b = map.getBounds();
        return {
            north: b.getNorth(), south: b.getSouth(),
            east: b.getEast(),   west: b.getWest(),
        };
    }

    function boundsKey(b) {
        const filterFp = [
            filters.yearFrom, filters.yearTo,
            filters.types.slice().sort().join(','),
            filters.priceMin, filters.priceMax,
            filters.bedrooms.slice().sort().join(','),
        ].join(':');
        return [b.south, b.west, b.north, b.east, viewMode, includeDemo ? '1' : '0', displayMode, filterFp, Array.from(enabledLayers).sort().join(',')]
            .map(v => typeof v === 'number' ? v.toFixed(4) : v).join('|');
    }

    function setLoading(on) {
        document.getElementById('map-loading-pill').style.display = on ? 'inline-flex' : 'none';
    }

    function clearAllPins() {
        Object.values(clusterByLayer).forEach(c => c.clearLayers());
    }

    function renderPayload(payload) {
        clearAllPins();
        let total = 0;
        let cappedLayers = [];
        const heatPoints = [];
        const showPins   = displayMode === 'pins' || displayMode === 'both';
        const showHeat   = displayMode === 'heatmap' || displayMode === 'both';

        (payload.layers || []).forEach(layer => {
            const cluster = clusterByLayer[layer.key];
            if (!cluster) return;

            // Update count badge
            const badge = document.querySelector('[data-layer-count="' + layer.key + '"]');
            if (badge) {
                badge.textContent = layer.capped
                    ? layer.count + ' / ' + layer.total
                    : String(layer.count);
            }
            if (layer.capped) cappedLayers.push(layer.key + ' (' + layer.count + '/' + layer.total + ')');

            // Skip rendering if layer disabled via checkbox
            if (!enabledLayers.has(layer.key)) return;

            // Always feed heat points (across enabled layers, regardless of pin display).
            (layer.pins || []).forEach(p => heatPoints.push([p.lat, p.lng, 1.0]));

            if (!showPins) {
                total += (layer.pins || []).length;
                return;
            }

            (layer.pins || []).forEach(pin => {
                const m = L.marker([pin.lat, pin.lng], { icon: pinIcon(pin) });
                // Tooltip hints — shifted pins explain themselves, +N pins
                // promise more sources on click.
                let tooltipBody = pin.title + '\n' + (pin.subtitle || '');
                if (pin.shifted) {
                    tooltipBody += '\n(Shifted — multiple data sources at this address)';
                } else if (pin.colocated_count) {
                    tooltipBody += '\n+' + pin.colocated_count + ' more source' + (pin.colocated_count === 1 ? '' : 's') + ' here — click to choose';
                }
                m.bindTooltip(tooltipBody, { direction: 'top' });
                m.on('click', () => {
                    if (pin.colocated_count && pin.colocated_count > 0 && Array.isArray(pin.colocated_layers)) {
                        openMultiSourcePicker(pin);
                    } else {
                        openDetail(pin);
                    }
                });
                cluster.addLayer(m);
            });
            total += layer.pins.length;
        });

        document.getElementById('empty-state').style.display = total === 0 ? 'block' : 'none';

        const cap = document.getElementById('layer-cap-notice');
        if (cappedLayers.length > 0) {
            cap.textContent = 'Some layers truncated: ' + cappedLayers.join(', ');
            cap.style.display = 'block';
        } else {
            cap.style.display = 'none';
        }

        // Phase 3g V2 — heatmap render. Reuse the same layer instance —
        // setLatLngs() avoids tile redraw flicker.
        renderHeatmap(showHeat ? heatPoints : []);
        document.getElementById('heat-legend').style.display = showHeat ? 'block' : 'none';
    }

    function renderHeatmap(points) {
        if (points.length === 0) {
            if (heatLayer) { map.removeLayer(heatLayer); heatLayer = null; }
            return;
        }
        if (!heatLayer) {
            heatLayer = L.heatLayer(points, {
                radius: 25,
                blur: 18,
                maxZoom: 17,
                max: 5.0,
                // Inverted gradient — red where data is THIN (action needed),
                // green where coverage is dense. Reads against operator instinct
                // ("hot" usually = warning) but matches our actual goal: red
                // means "pull more CMA reports here".
                gradient: {
                    0.0: '#dc2626',
                    0.3: '#f59e0b',
                    0.6: '#84cc16',
                    1.0: '#16a34a',
                },
            }).addTo(map);
        } else {
            heatLayer.setLatLngs(points);
            if (!map.hasLayer(heatLayer)) heatLayer.addTo(map);
        }
    }

    // ── Fetching ──────────────────────────────────────────────────────────
    async function fetchPins() {
        if (inFlight) inFlight.abort();
        const b = currentBounds();
        const key = boundsKey(b);

        // Cache hit
        const hit = cache.find(c => c.key === key);
        if (hit) {
            renderPayload(hit.payload);
            return;
        }

        const params = new URLSearchParams({
            north: b.north.toFixed(6),
            south: b.south.toFixed(6),
            east:  b.east.toFixed(6),
            west:  b.west.toFixed(6),
            viewMode: viewMode,
            limit: '2000',
            include_demo: includeDemo ? '1' : '0',
        });
        // Always send all five layer keys so server gives counts for each;
        // the UI hides disabled ones at render time.
        ['hfc_listings','sold_comps','active_listings','mic_subjects','scheme_owners'].forEach(k => params.append('layers[]', k));

        // Filters — only send when narrowed beyond defaults so the URL stays
        // clean in the common case.
        if (filters.yearFrom !== FILTER_DEFAULTS.yearFrom) params.set('dateFromYear', String(filters.yearFrom));
        if (filters.yearTo   !== FILTER_DEFAULTS.yearTo)   params.set('dateToYear',   String(filters.yearTo));
        if (filters.types.length !== FILTER_DEFAULTS.types.length) {
            filters.types.forEach(t => params.append('propertyTypes[]', t));
        }
        if (filters.priceMin !== FILTER_DEFAULTS.priceMin) params.set('priceMin', String(filters.priceMin));
        if (filters.priceMax !== FILTER_DEFAULTS.priceMax) params.set('priceMax', String(filters.priceMax));
        if (filters.bedrooms.length !== FILTER_DEFAULTS.bedrooms.length) {
            filters.bedrooms.forEach(b => params.append('bedrooms[]', String(b)));
        }

        setLoading(true);
        try {
            const ctl = new AbortController();
            inFlight = ctl;
            const resp = await fetch(PINS_URL + '?' + params.toString(), {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin',
                signal: ctl.signal,
            });
            if (!resp.ok) throw new Error('HTTP ' + resp.status);
            const payload = await resp.json();
            inFlight = null;

            // LRU cache
            cache.push({ key: key, payload: payload });
            if (cache.length > CACHE_MAX) cache.shift();

            renderPayload(payload);
        } catch (e) {
            if (e.name === 'AbortError') return; // superseded by newer fetch
            console.warn('Map fetch error', e);
        } finally {
            setLoading(false);
        }
    }

    function debouncedFetch() {
        clearTimeout(fetchTimer);
        fetchTimer = setTimeout(fetchPins, 350);
    }

    // ── Detail panel ──────────────────────────────────────────────────────
    const detailPanel = document.getElementById('map-detail-panel');
    document.getElementById('detail-close-btn').addEventListener('click', () => {
        detailPanel.style.transform = 'translateX(100%)';
    });

    /**
     * Multi-source picker — shown when a primary pin has colocated_count > 0.
     * Lists every source at that GPS so the user can pick which detail card
     * to load. Each list item is a button that swaps the panel back to the
     * normal detail flow for the chosen source.
     */
    function openMultiSourcePicker(primary) {
        const all = [
            {
                layer:      primary.layer,
                id:         primary.id,
                title:      primary.title,
                subtitle:   primary.subtitle,
                detail_url: primary.detail_url,
                primary:    true,
            },
            ...primary.colocated_layers.map(c => ({ ...c, primary: false })),
        ];

        document.getElementById('detail-title').textContent = primary.title || '';
        document.getElementById('detail-subtitle').textContent =
            all.length + ' data sources at this address';

        const addrEl = document.getElementById('detail-address');
        addrEl.style.display = 'none';
        document.getElementById('detail-sensitive').style.display = 'none';
        document.getElementById('detail-relationships').innerHTML = '';

        const itemsHtml = all.map((src, idx) => {
            const colour = LAYER_COLOURS[src.layer] || '#64748b';
            const letter = LAYER_LETTERS[src.layer] || '?';
            const name   = LAYER_NAMES[src.layer] || src.layer;
            const primaryBadge = src.primary
                ? '<span style="margin-left:6px;font-size:0.625rem;text-transform:uppercase;letter-spacing:0.05em;color:var(--text-muted);">primary</span>'
                : '';
            return '<button type="button" data-pick-idx="' + idx + '" '
                + 'style="display:flex;align-items:center;gap:10px;width:100%;text-align:left;padding:10px 12px;margin-bottom:6px;background:var(--surface-2);border:1px solid var(--border);border-radius:4px;cursor:pointer;font-family:inherit;">'
                + '<span style="display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;background:' + colour + ';color:#fff;border:2px solid #fff;border-radius:50%;font-size:11px;font-weight:700;flex-shrink:0;">' + letter + '</span>'
                + '<span style="flex:1;min-width:0;">'
                +   '<div style="font-size:0.8125rem;font-weight:500;color:var(--text-primary);">' + escapeHtml(name) + primaryBadge + '</div>'
                +   '<div style="font-size:0.75rem;color:var(--text-muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + escapeHtml(src.title || '') + '</div>'
                + '</span>'
                + '<span style="color:var(--text-muted);">→</span>'
                + '</button>';
        }).join('');

        document.getElementById('detail-facts').innerHTML =
            '<div style="font-size:0.6875rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;font-weight:600;margin-bottom:8px;">Choose a source</div>'
            + itemsHtml;

        // Wire each picker button — clicking loads that source's detail.
        document.querySelectorAll('[data-pick-idx]').forEach(btn => {
            btn.addEventListener('click', () => {
                const idx = parseInt(btn.dataset.pickIdx, 10);
                const src = all[idx];
                openDetail({
                    layer:      src.layer,
                    id:         src.id,
                    title:      src.title,
                    subtitle:   src.subtitle,
                    detail_url: src.detail_url,
                });
            });
        });

        detailPanel.style.transform = 'translateX(0)';
    }

    async function openDetail(pin) {
        document.getElementById('detail-title').textContent = pin.title || '';
        document.getElementById('detail-subtitle').textContent = pin.subtitle || '';
        document.getElementById('detail-facts').innerHTML = '<div style="font-size:0.75rem;color:var(--text-muted);">Loading…</div>';
        document.getElementById('detail-relationships').innerHTML = '';
        document.getElementById('detail-sensitive').style.display = 'none';
        document.getElementById('detail-address').style.display = 'none';
        detailPanel.style.transform = 'translateX(0)';

        try {
            const url = pin.detail_url + (pin.detail_url.includes('?') ? '&' : '?') + 'viewMode=' + viewMode;
            const resp = await fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
            if (!resp.ok) {
                document.getElementById('detail-facts').innerHTML = '<div style="color:var(--ds-red,#dc2626);font-size:0.75rem;">Could not load details.</div>';
                return;
            }
            const card = await resp.json();
            renderCard(card);
        } catch (e) {
            document.getElementById('detail-facts').innerHTML = '<div style="color:var(--ds-red,#dc2626);font-size:0.75rem;">' + (e.message || 'Error') + '</div>';
        }
    }

    function renderCard(card) {
        document.getElementById('detail-title').textContent = card.title || '';
        document.getElementById('detail-subtitle').textContent = card.subtitle || '';

        const addrEl = document.getElementById('detail-address');
        if (card.address) {
            addrEl.textContent = card.address;
            addrEl.style.display = 'block';
        }

        const factsHtml = (card.facts || []).map(f =>
            '<div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border);font-size:0.8125rem;">'
            + '<span style="color:var(--text-muted);">' + escapeHtml(f.label) + '</span>'
            + '<span style="color:var(--text-primary);font-weight:500;">' + escapeHtml(String(f.value)) + '</span>'
            + '</div>'
        ).join('');
        document.getElementById('detail-facts').innerHTML = factsHtml
            || '<div style="font-size:0.75rem;color:var(--text-muted);">No facts available.</div>';

        if (card.sensitive_facts && card.sensitive_facts.length > 0) {
            const sensitiveHtml = card.sensitive_facts.map(f =>
                '<div style="display:flex;justify-content:space-between;padding:4px 0;font-size:0.8125rem;">'
                + '<span style="color:var(--text-muted);">' + escapeHtml(f.label) + '</span>'
                + '<span style="color:var(--text-primary);font-weight:500;">' + escapeHtml(String(f.value)) + '</span>'
                + '</div>'
            ).join('');
            document.getElementById('detail-sensitive-facts').innerHTML = sensitiveHtml;
            document.getElementById('detail-sensitive').style.display = 'block';
        }

        const relsHtml = (card.relationships || []).map(r =>
            '<a href="' + escapeAttr(r.url) + '" style="display:block;padding:8px 10px;margin-bottom:6px;background:var(--surface-2);border:1px solid var(--border);border-radius:4px;font-size:0.8125rem;color:var(--brand-button);text-decoration:none;">'
            + escapeHtml(r.label) + ' →</a>'
        ).join('');
        document.getElementById('detail-relationships').innerHTML = relsHtml;
    }

    function escapeHtml(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
    function escapeAttr(s) { return escapeHtml(s); }

    // ── Toggles ───────────────────────────────────────────────────────────
    document.querySelectorAll('[data-layer-cb]').forEach(cb => {
        cb.addEventListener('change', e => {
            const key = e.target.dataset.layerCb;
            if (e.target.checked) enabledLayers.add(key);
            else enabledLayers.delete(key);
            // Re-render without re-fetching — payload is cached.
            const hit = cache[cache.length - 1];
            if (hit) renderPayload(hit.payload);
        });
    });

    document.querySelectorAll('#base-layer-toggle .base-pill').forEach(btn => {
        btn.addEventListener('click', () => {
            const target = btn.dataset.base;
            if (target === baseLayerKey) return;
            map.removeLayer(baseLayers[baseLayerKey]);
            baseLayers[target].addTo(map);
            baseLayerKey = target;
            localStorage.setItem('corex.map.base_layer', target);
            // Style refresh
            document.querySelectorAll('#base-layer-toggle .base-pill').forEach(b => {
                const active = b.dataset.base === target;
                b.classList.toggle('active', active);
                b.style.background = active ? 'var(--brand-button)' : 'transparent';
                b.style.color = active ? '#fff' : 'var(--text-secondary)';
            });
        });
    });

    document.querySelectorAll('#view-mode-toggle .mode-pill').forEach(btn => {
        btn.addEventListener('click', () => {
            const target = btn.dataset.mode;
            if (target === viewMode) return;
            viewMode = target;
            localStorage.setItem('corex.map.view_mode', target);
            document.getElementById('seller-banner').style.display = target === 'seller' ? 'block' : 'none';
            document.querySelectorAll('#view-mode-toggle .mode-pill').forEach(b => {
                const active = b.dataset.mode === target;
                b.classList.toggle('active', active);
                b.style.background = active ? 'var(--brand-button)' : 'transparent';
                b.style.color = active ? '#fff' : 'var(--text-secondary)';
            });
            // Hide scheme_owners layer entry in seller view (it'll be empty anyway).
            document.querySelectorAll('[data-sensitive="1"]').forEach(el => {
                el.style.display = target === 'seller' ? 'none' : 'flex';
            });
            // Clear cache (server response shape differs) + refetch.
            cache.length = 0;
            fetchPins();
        });
    });

    document.getElementById('reset-bounds-btn').addEventListener('click', () => {
        map.fitBounds([[HFC_BOUNDS.south, HFC_BOUNDS.west], [HFC_BOUNDS.north, HFC_BOUNDS.east]]);
    });

    // Phase 3g V2 — display-mode radios.
    document.querySelectorAll('#display-mode-group input[type="radio"]').forEach(r => {
        if (r.value === displayMode) r.checked = true;
        r.addEventListener('change', e => {
            if (!e.target.checked) return;
            displayMode = e.target.value;
            localStorage.setItem('corex.map.display_mode', displayMode);
            cache.length = 0;
            fetchPins();
        });
    });

    // Phase 3g V2 — filters: collapse toggle + change handlers + clear button.
    const filtersToggleBtn = document.getElementById('filters-toggle');
    const filtersBody      = document.getElementById('filters-body');
    const filtersCaret     = document.getElementById('filters-caret');
    filtersToggleBtn.addEventListener('click', () => {
        const open = filtersBody.style.display === 'block';
        filtersBody.style.display = open ? 'none' : 'block';
        filtersCaret.style.transform = open ? 'rotate(0deg)' : 'rotate(90deg)';
    });

    function onFilterChange() {
        const yf = parseInt(document.getElementById('filter-year-from').value, 10);
        const yt = parseInt(document.getElementById('filter-year-to').value, 10);
        const types = Array.from(document.querySelectorAll('[data-filter-type]'))
            .filter(cb => cb.checked).map(cb => cb.dataset.filterType);
        const pMin = parseInt(document.getElementById('filter-price-min').value, 10);
        const pMax = parseInt(document.getElementById('filter-price-max').value, 10);
        const beds = Array.from(document.querySelectorAll('#filter-bedrooms .bed-pill'))
            .filter(b => b.dataset.on === '1').map(b => parseInt(b.dataset.bed, 10));

        filters = {
            yearFrom: Number.isInteger(yf) ? yf : FILTER_DEFAULTS.yearFrom,
            yearTo:   Number.isInteger(yt) ? yt : FILTER_DEFAULTS.yearTo,
            types:    types.length ? types : [...FILTER_DEFAULTS.types],
            priceMin: Number.isInteger(pMin) ? pMin : FILTER_DEFAULTS.priceMin,
            priceMax: Number.isInteger(pMax) ? pMax : FILTER_DEFAULTS.priceMax,
            bedrooms: beds.length ? beds : [...FILTER_DEFAULTS.bedrooms],
        };
        persistFilters();
        syncFilterUi();
        cache.length = 0;
        fetchPins();
    }

    ['filter-year-from','filter-year-to','filter-price-min','filter-price-max'].forEach(id => {
        document.getElementById(id).addEventListener('change', onFilterChange);
    });
    document.querySelectorAll('[data-filter-type]').forEach(cb => cb.addEventListener('change', onFilterChange));
    document.querySelectorAll('#filter-bedrooms .bed-pill').forEach(btn => {
        btn.addEventListener('click', () => {
            btn.dataset.on = btn.dataset.on === '1' ? '0' : '1';
            const on = btn.dataset.on === '1';
            btn.style.background = on ? 'var(--brand-button)' : 'var(--surface-2)';
            btn.style.color      = on ? '#fff' : 'var(--text-secondary)';
            onFilterChange();
        });
    });
    document.getElementById('filter-clear').addEventListener('click', () => {
        filters = { ...FILTER_DEFAULTS, types: [...FILTER_DEFAULTS.types], bedrooms: [...FILTER_DEFAULTS.bedrooms] };
        persistFilters();
        syncFilterUi();
        cache.length = 0;
        fetchPins();
    });

    syncFilterUi();

    // Phase 3h Step 10 — demo toggle click handler.
    if (demoToggleEl) {
        demoToggleEl.addEventListener('change', e => {
            includeDemo = !!e.target.checked;
            localStorage.setItem('corex.map.include_demo', includeDemo ? '1' : '0');
            cache.length = 0;
            fetchPins();
        });
    }

    map.on('moveend zoomend', debouncedFetch);

    // Initial render of seller view from persisted preference.
    if (viewMode === 'seller') {
        document.querySelectorAll('#view-mode-toggle .mode-pill').forEach(b => {
            const active = b.dataset.mode === viewMode;
            b.style.background = active ? 'var(--brand-button)' : 'transparent';
            b.style.color = active ? '#fff' : 'var(--text-secondary)';
        });
        document.getElementById('seller-banner').style.display = 'block';
        document.querySelectorAll('[data-sensitive="1"]').forEach(el => el.style.display = 'none');
    }
    if (baseLayerKey === 'satellite') {
        document.querySelectorAll('#base-layer-toggle .base-pill').forEach(b => {
            const active = b.dataset.base === baseLayerKey;
            b.style.background = active ? 'var(--brand-button)' : 'transparent';
            b.style.color = active ? '#fff' : 'var(--text-secondary)';
        });
    }

    // First fetch.
    fetchPins();
});
</script>
@endsection
