{{-- Build 2 — agent's pre-flight review screen.

     Three vertical sections (NOT tabs — single-page scroll, mobile-friendly):
       1. Subject snapshot — confirm what we know
       2. Comparable sales — tickbox table + live map sync
       3. Generate — finalise or discard

     Routes used:
       - POST presentations.review.toggle-comp
       - POST presentations.review.publish
       - POST presentations.review.revert
       - POST presentations.review.takeover

     The map intentionally inlines a SMALL Leaflet snippet reusing the
     bucket palette from Build 1 — extracting the full pin module
     (resources/views/corex/map/index.blade.php) was deemed risky in
     the Phase A audit, so only the SVG shape generators we need are
     duplicated. Single source of palette: data-bucket attributes +
     the shared @push('head') block above. --}}
@extends('layouts.corex-app')

@push('head')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
      integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<style>
    /* Build 2 review-page styles — sympathetic to the rest of CoreX
       (navy + teal + sharp 2-3px corners). No emojis. */
    .review-card { background: var(--surface); border: 1px solid var(--border); border-radius: 4px; padding: 16px; margin-bottom: 16px; }
    .review-section-header { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
    .review-section-tag { width: 4px; height: 18px; background: #00d4aa; }
    .review-section-title { margin: 0; font-size: 13px; font-weight: 700; color: #0b2a4a; letter-spacing: 0.04em; text-transform: uppercase; }
    .review-warn-banner { padding: 10px 12px; background: color-mix(in srgb, var(--ds-amber, #d97706) 12%, transparent); border: 1px solid color-mix(in srgb, var(--ds-amber, #d97706) 30%, transparent); border-radius: 4px; color: var(--ds-amber, #d97706); font-size: 12px; margin-bottom: 12px; }
    .comp-row { display: grid; grid-template-columns: 28px 1fr 90px 100px 110px 70px 90px 28px; gap: 8px; align-items: center; padding: 8px 4px; border-bottom: 1px solid var(--border); font-size: 12px; }
    .comp-row.excluded { opacity: 0.45; }
    .comp-row.cross-type { background: color-mix(in srgb, var(--ds-amber, #d97706) 6%, transparent); }
    .comp-row input[type="checkbox"] { accent-color: #00d4aa; width: 16px; height: 16px; cursor: pointer; }
    .tt-badge { display: inline-flex; align-items: center; padding: 1px 6px; border-radius: 3px; font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.02em; }
    .tt-badge.full_title      { background: #0b2a4a; color: #fff; }
    .tt-badge.sectional_title { background: #7c3aed; color: #fff; }
    .tt-badge.vacant_land     { background: #06b6d4; color: #0b2a4a; }
    .tt-badge.other           { background: #475569; color: #fff; }
    #review-map { height: 460px; border: 1px solid var(--border); border-radius: 4px; }
    .review-pin { background: transparent !important; border: 0 !important; }
    .review-pin svg { display: block; filter: drop-shadow(0 1px 2px rgba(0,0,0,0.4)); }
    .review-pin-cross { outline: 2px dashed var(--ds-amber, #d97706); outline-offset: 2px; border-radius: 4px; }
    .review-toast { position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%); background: #0b2a4a; color: #fff; padding: 8px 16px; border-radius: 4px; font-size: 12px; opacity: 0; transition: opacity 200ms; pointer-events: none; z-index: 9999; }
    .review-toast.show { opacity: 1; }
</style>
@endpush

@section('corex-content')
<div style="max-width: 1400px; margin: 0 auto; padding: 16px 20px;">

    {{-- Concurrent-reviewer banner. --}}
    @if($isLockedByOther)
        <div class="review-warn-banner">
            <strong>Currently being reviewed by {{ $currentReviewer->name ?? 'another agent' }}</strong>
            — they opened this presentation
            {{ $version->reviewer_locked_at?->diffForHumans() ?? 'recently' }}.
            <form method="POST" action="{{ route('presentations.review.takeover', $version->id) }}" style="display:inline; margin-left:8px;">
                @csrf
                <button type="submit" style="background:transparent;border:1px solid var(--ds-amber,#d97706);color:var(--ds-amber,#d97706);padding:4px 10px;font-size:11px;border-radius:3px;cursor:pointer;font-weight:600;">
                    Take over
                </button>
            </form>
        </div>
    @endif

    {{-- Soft-deleted comp banner. --}}
    @if($unavailableLogged > 0)
        <div class="review-warn-banner">
            {{ $unavailableLogged }} comparable
            {{ $unavailableLogged === 1 ? 'row was' : 'rows were' }}
            removed by the system between generation and this review
            (likely soft-deleted by a parallel update). Logged for audit.
        </div>
    @endif

    {{-- Header --}}
    <div style="margin-bottom: 18px;">
        <h1 style="font-size: 18px; font-weight: 700; color: var(--text-primary); margin: 0;">
            Review Presentation
        </h1>
        <p style="font-size: 13px; color: var(--text-muted); margin: 4px 0 0 0;">
            Confirm the subject facts and the comparable sales we picked, then publish.
            Untick anything you don't want included. Your overrides are logged for
            future learning.
        </p>
    </div>

    {{-- ─────────── SECTION 1 — Subject snapshot ─────────── --}}
    <div class="review-card">
        <div class="review-section-header">
            <div class="review-section-tag"></div>
            <h2 class="review-section-title">1 · Subject Snapshot</h2>
        </div>

        <table style="width: 100%; font-size: 12.5px; color: var(--text-primary); border-collapse: collapse;">
            <tr>
                <td style="padding: 5px 0; width: 18%; color: var(--text-muted); font-weight: 600;">Address</td>
                <td style="padding: 5px 0; width: 32%;">{{ $presentation->property_address ?? '—' }}</td>
                <td style="padding: 5px 0; width: 18%; color: var(--text-muted); font-weight: 600;">Suburb</td>
                <td style="padding: 5px 0; width: 32%;">{{ $presentation->suburb ?? '—' }}</td>
            </tr>
            <tr>
                <td style="padding: 5px 0; color: var(--text-muted); font-weight: 600;">Type</td>
                <td style="padding: 5px 0;">{{ \Illuminate\Support\Str::humanType($presentation->property_type) }}</td>
                <td style="padding: 5px 0; color: var(--text-muted); font-weight: 600;">Category</td>
                <td style="padding: 5px 0;">
                    {{ $presentation->property?->category ?? '— (no category set — comp filter skipped)' }}
                    @if($subjectTitleType)
                        <span class="tt-badge {{ $subjectTitleType }}" style="margin-left:6px;">
                            {{ \App\Models\PropertySettingItem::TITLE_TYPES[$subjectTitleType] ?? $subjectTitleType }}
                        </span>
                    @endif
                </td>
            </tr>
            <tr>
                <td style="padding: 5px 0; color: var(--text-muted); font-weight: 600;">Bedrooms</td>
                <td style="padding: 5px 0;">{{ $presentation->bedrooms ?? '—' }}</td>
                <td style="padding: 5px 0; color: var(--text-muted); font-weight: 600;">Erf size</td>
                <td style="padding: 5px 0;">
                    {{ $presentation->erf_size_m2 ? number_format($presentation->erf_size_m2) . ' m²' : '—' }}
                </td>
            </tr>
            <tr>
                <td style="padding: 5px 0; color: var(--text-muted); font-weight: 600;">Asking price</td>
                <td style="padding: 5px 0;">
                    {{ $presentation->asking_price_inc ? 'R ' . number_format($presentation->asking_price_inc, 0, '.', ' ') : '—' }}
                </td>
                <td style="padding: 5px 0; color: var(--text-muted); font-weight: 600;">Source property</td>
                <td style="padding: 5px 0;">
                    @if($presentation->property)
                        <a href="{{ route('corex.properties.show', $presentation->property) }}" target="_blank" style="color: #00d4aa; text-decoration: none;">
                            Open property record &rarr;
                        </a>
                    @else
                        —
                    @endif
                </td>
            </tr>
        </table>
    </div>

    {{-- ─────────── SECTION 2 — Comparable sales ─────────── --}}
    <div class="review-card">
        <div class="review-section-header">
            <div class="review-section-tag"></div>
            <h2 class="review-section-title">2 · Comparable Sales — {{ count($compRows) }} found</h2>
        </div>

        <div style="display: grid; grid-template-columns: minmax(0, 1fr) minmax(360px, 560px); gap: 16px;">
            <div>
                {{-- Comp table --}}
                <div style="margin-bottom: 8px; display: flex; align-items: center; gap: 8px; font-size: 11px; color: var(--text-muted);">
                    <label style="display: inline-flex; align-items: center; gap: 4px; cursor: pointer;">
                        <input type="checkbox" id="show-excluded" checked>
                        <span>Show excluded rows</span>
                    </label>
                    <span style="margin-left: auto;">
                        Cross-type rows flagged
                        <span class="tt-badge other" style="background:var(--ds-amber,#d97706);color:#0b2a4a;">!</span>
                    </span>
                </div>

                <div id="comp-table" style="border: 1px solid var(--border); border-radius: 4px; overflow: hidden;">
                    <div class="comp-row" style="background: var(--surface-2); font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-muted);">
                        <div></div>
                        <div>Address</div>
                        <div>Sale date</div>
                        <div style="text-align:right;">Sold price</div>
                        <div>Type</div>
                        <div style="text-align:right;">R/m²</div>
                        <div>Title</div>
                        <div></div>
                    </div>
                    @foreach($compRows as $row)
                        <div class="comp-row {{ $row['is_included'] ? '' : 'excluded' }} {{ $row['is_cross_type'] ? 'cross-type' : '' }}"
                             data-comp-id="{{ $row['id'] }}"
                             data-included="{{ $row['is_included'] ? '1' : '0' }}"
                             data-cross-type="{{ $row['is_cross_type'] ? '1' : '0' }}"
                             data-lat="{{ $row['lat'] }}"
                             data-lng="{{ $row['lng'] }}"
                             data-title-type="{{ $row['title_type'] }}">
                            <div>
                                <input type="checkbox" class="comp-toggle" {{ $row['is_included'] ? 'checked' : '' }}>
                            </div>
                            <div title="{{ $row['address'] }}" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                {{ $row['address'] }}
                            </div>
                            <div style="color: var(--text-muted); font-size: 11px;">{{ $row['sale_date'] ?? '—' }}</div>
                            <div style="text-align:right; font-weight: 600;">
                                {{ $row['sold_price_inc'] ? 'R ' . number_format($row['sold_price_inc'], 0, '.', ' ') : '—' }}
                            </div>
                            <div style="color: var(--text-muted);">{{ \Illuminate\Support\Str::humanType($row['property_type']) }}</div>
                            <div style="text-align:right; color: var(--text-muted);">
                                {{ $row['r_per_m2'] ? number_format($row['r_per_m2']) : '—' }}
                            </div>
                            <div>
                                <span class="tt-badge {{ $row['title_type'] }}">
                                    {{ substr(\App\Models\PropertySettingItem::TITLE_TYPES[$row['title_type']] ?? $row['title_type'], 0, 4) }}
                                </span>
                            </div>
                            <div title="{{ $row['is_cross_type'] ? 'Cross-title comparison — not recommended for valuation' : '' }}"
                                 style="font-size: 14px; color: {{ $row['is_cross_type'] ? 'var(--ds-amber,#d97706)' : 'transparent' }};">
                                {{ $row['is_cross_type'] ? '!' : '' }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div>
                {{-- Map --}}
                <div id="review-map"></div>
                <div style="margin-top: 6px; font-size: 11px; color: var(--text-muted); display: flex; gap: 14px; flex-wrap: wrap;">
                    <span><span style="display:inline-block;width:10px;height:10px;background:#00d4aa;clip-path:polygon(50% 0%,100% 25%,100% 75%,50% 100%,0% 75%,0% 25%);vertical-align:middle;margin-right:4px;"></span>Subject</span>
                    <span><span style="display:inline-block;width:8px;height:8px;background:#0b2a4a;vertical-align:middle;margin-right:4px;border-radius:50%;"></span>Full title comp</span>
                    <span><span style="display:inline-block;width:8px;height:8px;background:#7c3aed;vertical-align:middle;margin-right:4px;"></span>Sectional comp</span>
                    <span><span style="display:inline-block;width:8px;height:8px;background:#06b6d4;vertical-align:middle;margin-right:4px;clip-path:polygon(50% 0,100% 100%,0 100%);"></span>Vacant land comp</span>
                </div>
            </div>
        </div>
    </div>

    {{-- ─────────── SECTION 3 — Generate ─────────── --}}
    <div class="review-card" style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
        <button id="btn-publish" type="button"
                style="background:#00d4aa;color:#0b2a4a;border:1px solid #00d4aa;padding:10px 20px;font-size:13px;font-weight:700;border-radius:4px;cursor:pointer;">
            Generate Presentation
        </button>
        <button id="btn-save" type="button"
                style="background:transparent;color:#00d4aa;border:1px solid #00d4aa;padding:10px 16px;font-size:13px;font-weight:600;border-radius:4px;cursor:pointer;">
            Save &amp; continue later
        </button>
        <span style="margin-left:auto;">
            <button id="btn-revert" type="button"
                    style="background:transparent;color:var(--text-muted);border:0;padding:10px 16px;font-size:12px;text-decoration:underline;cursor:pointer;">
                Discard &amp; return to property
            </button>
        </span>
    </div>

    <div id="review-toast" class="review-toast"></div>

</div>

<script>
(function () {
    'use strict';
    const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
    const VERSION_ID = {{ $version->id }};
    const TOGGLE_TPL = @json(route('presentations.review.toggle-comp', ['version' => $version->id, 'comp' => '__COMP_ID__']));
    const PUBLISH_URL = @json(route('presentations.review.publish', $version->id));
    const REVERT_URL  = @json(route('presentations.review.revert',  $version->id));

    const SUBJECT_LAT = {{ (float) ($presentation->latitude ?? -30.84) }};
    const SUBJECT_LNG = {{ (float) ($presentation->longitude ?? 30.39) }};

    const toastEl = document.getElementById('review-toast');
    function toast(msg) {
        toastEl.textContent = msg;
        toastEl.classList.add('show');
        clearTimeout(toastEl._t);
        toastEl._t = setTimeout(() => toastEl.classList.remove('show'), 2200);
    }

    // ── Map init with bucket palette (Build 1) ────────────────────────
    const map = L.map('review-map', { scrollWheelZoom: true })
        .setView([SUBJECT_LAT, SUBJECT_LNG], 14);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors', maxZoom: 19,
    }).addTo(map);

    function bucketSvg(titleType, size) {
        const px = size || 18, sw = 2;
        const fill = ({
            sectional_title: '#7c3aed',
            vacant_land:     '#06b6d4',
            other:           '#475569',
            full_title:      '#0b2a4a',
        })[titleType] || '#0b2a4a';
        const stroke = '#ffffff';

        if (titleType === 'sectional_title') {
            return '<svg xmlns="http://www.w3.org/2000/svg" width="'+px+'" height="'+px+'" viewBox="0 0 '+px+' '+px+'">'
                 + '<rect x="1" y="1" width="'+(px-sw)+'" height="'+(px-sw)+'" rx="2" fill="'+fill+'" stroke="'+stroke+'" stroke-width="'+sw+'"/></svg>';
        }
        if (titleType === 'vacant_land') {
            const cx = px/2;
            return '<svg xmlns="http://www.w3.org/2000/svg" width="'+px+'" height="'+px+'" viewBox="0 0 '+px+' '+px+'">'
                 + '<polygon points="'+cx+',1 '+(px-1)+','+(px-1)+' 1,'+(px-1)+'" fill="'+fill+'" stroke="'+stroke+'" stroke-width="'+sw+'" stroke-linejoin="round"/></svg>';
        }
        // full_title + other → circle
        return '<svg xmlns="http://www.w3.org/2000/svg" width="'+px+'" height="'+px+'" viewBox="0 0 '+px+' '+px+'">'
             + '<circle cx="'+(px/2)+'" cy="'+(px/2)+'" r="'+((px-sw)/2)+'" fill="'+fill+'" stroke="'+stroke+'" stroke-width="'+sw+'"/></svg>';
    }

    // Subject hexagon (teal — Build 1's TRACKED spine colour reused as
    // the subject marker so it pops against the bucket palette).
    function subjectSvg() {
        const px = 22, sw = 2, cx = px/2, cy = px/2, r = Math.min(px, px)/2 - sw/2;
        const pts = [];
        for (let i = 0; i < 6; i++) {
            const a = (Math.PI / 3) * i - Math.PI / 2;
            pts.push((cx + r * Math.cos(a)).toFixed(2) + ',' + (cy + r * Math.sin(a)).toFixed(2));
        }
        return '<svg xmlns="http://www.w3.org/2000/svg" width="'+px+'" height="'+px+'" viewBox="0 0 '+px+' '+px+'">'
             + '<polygon points="'+pts.join(' ')+'" fill="#00d4aa" stroke="#0b2a4a" stroke-width="2"/></svg>';
    }

    // Subject marker (always shown).
    L.marker([SUBJECT_LAT, SUBJECT_LNG], {
        icon: L.divIcon({
            html: subjectSvg(), className: 'review-pin',
            iconSize: [22, 22], iconAnchor: [11, 11],
        }),
        zIndexOffset: 1000,
    }).addTo(map);

    // Comp markers — keyed by comp_id so toggle can hide/show.
    const markers = new Map();
    document.querySelectorAll('#comp-table .comp-row[data-comp-id]').forEach(row => {
        const id  = parseInt(row.dataset.compId, 10);
        const lat = parseFloat(row.dataset.lat);
        const lng = parseFloat(row.dataset.lng);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
        const tt    = row.dataset.titleType || 'full_title';
        const cross = row.dataset.crossType === '1';
        const m = L.marker([lat, lng], {
            icon: L.divIcon({
                html: bucketSvg(tt, 18),
                className: 'review-pin' + (cross ? ' review-pin-cross' : ''),
                iconSize: [18, 18], iconAnchor: [9, 9],
            }),
        });
        m.bindTooltip(row.querySelector('div:nth-of-type(2)')?.textContent.trim() || '', { direction: 'top' });
        if (row.dataset.included === '1') m.addTo(map);
        markers.set(id, m);
    });

    // ── Comp toggle with debounce + optimistic UI ─────────────────────
    let pendingToggles = new Map();
    let toggleTimer = null;
    function flushToggle(compId) {
        const pending = pendingToggles.get(compId);
        if (!pending) return;
        pendingToggles.delete(compId);
        const url = TOGGLE_TPL.replace('__COMP_ID__', String(compId));
        const body = new FormData();
        body.append('_token', csrf);
        body.append('included', pending.included ? '1' : '0');
        fetch(url, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body,
            credentials: 'same-origin',
        }).then(r => r.json()).then(d => {
            if (!d?.ok) {
                // Optimistic rollback.
                pending.rollback();
                toast('Could not save toggle — please retry');
            }
        }).catch(() => {
            pending.rollback();
            toast('Network error saving toggle');
        });
    }

    document.querySelectorAll('.comp-toggle').forEach(cb => {
        cb.addEventListener('change', () => {
            const row    = cb.closest('.comp-row');
            const id     = parseInt(row.dataset.compId, 10);
            const next   = cb.checked;
            const marker = markers.get(id);

            // Optimistic: toggle marker + row class immediately.
            row.dataset.included = next ? '1' : '0';
            row.classList.toggle('excluded', !next);
            if (marker) {
                if (next) marker.addTo(map); else map.removeLayer(marker);
            }

            const rollback = () => {
                cb.checked = !next;
                row.dataset.included = (!next) ? '1' : '0';
                row.classList.toggle('excluded', next);
                if (marker) {
                    if (!next) marker.addTo(map); else map.removeLayer(marker);
                }
            };
            pendingToggles.set(id, { included: next, rollback });
            clearTimeout(toggleTimer);
            toggleTimer = setTimeout(() => {
                Array.from(pendingToggles.keys()).forEach(flushToggle);
            }, 300);
        });
    });

    // Show/hide excluded rows.
    document.getElementById('show-excluded').addEventListener('change', e => {
        const show = e.target.checked;
        document.querySelectorAll('#comp-table .comp-row[data-comp-id]').forEach(row => {
            if (row.dataset.included === '0') row.style.display = show ? '' : 'none';
        });
    });

    // ── Publish / Revert ─────────────────────────────────────────────
    document.getElementById('btn-publish').addEventListener('click', async () => {
        const body = new FormData(); body.append('_token', csrf);
        try {
            const r = await fetch(PUBLISH_URL, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body, credentials: 'same-origin',
            });
            const d = await r.json();
            if (d?.ok && d.public_url) {
                window.location.href = d.public_url;
            } else {
                toast('Publish failed — please retry');
            }
        } catch (e) { toast('Network error publishing'); }
    });

    document.getElementById('btn-revert').addEventListener('click', async () => {
        if (!confirm('Discard this presentation? Your overrides will be logged but the version will be archived.')) return;
        const body = new FormData(); body.append('_token', csrf);
        try {
            const r = await fetch(REVERT_URL, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body, credentials: 'same-origin',
            });
            const d = await r.json();
            if (d?.ok && d.property_url) {
                window.location.href = d.property_url;
            } else {
                toast('Discard failed — please retry');
            }
        } catch (e) { toast('Network error discarding'); }
    });

    document.getElementById('btn-save').addEventListener('click', () => {
        // Save = no-op server-side; the toggle endpoint already persisted
        // each change. Just confirm to the user + close (or stay).
        toast('Saved — your overrides are stored.');
    });
})();
</script>
@endsection
