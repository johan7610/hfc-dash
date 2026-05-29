# Map Visual Identity Spec

> **Status:** spec only — no code in this prompt. Build is the next prompt.
> **Date:** 2026-05-29.
> **Branch:** `feature/map-workspace-overhaul`.
> **Anchors:** CoreX design language (dark navy + teal `#00d4aa`, Plus Jakarta
> Sans, 2-3px corners, no emojis, colour is functional only).
> POPIA/PPRA: nothing in this spec re-introduces owner PII or touches the
> server-side gate from `.ai/specs/map-owner-pii-egress.md`.
> Multi-tenant: H pins display the AGENCY logo from agency settings —
> never a hard-coded letter, never hard-coded to HFC.

---

## 0. The four buckets (decision filter)

The map is read through four functional buckets. Every visual decision in
this spec answers to which bucket the pin belongs to:

| Bucket  | Members on map | Meaning |
|---------|----------------|---------|
| **COMPANY** | H | Own agency's stock — mandate signed, actively listed. |
| **PORTAL**  | P | Unclaimed prospecting opportunities scraped from Property24 / Private Property. The "go win it" bucket. |
| **CMA**     | M, O, S-market | Information only — comps + sectional-scheme owners + market-sourced sold history. Read-only context, no claim possible. |
| **TRACKED** | T | The spine — claimed/working prospects with an active claim or pitch lock. S-own (HFC's own sold history) is the historical tail of the same spine — kept in the CMA-family slot visually but flagged so it reads as "ours". |

S (`sold_comps`) is split by the `source_class` flag (see §A.4): `market`
sits in the CMA bucket, `own` is a distinct visual variant inside CMA but
visually echoes the COMPANY palette to read as "we sold this".

---

## §A — Phase A findings (current state, read-only)

### A.1 — Current pin / cluster / halo rendering code

**Layer config — `resources/views/corex/map/index.blade.php:436-455`**

```javascript
const LAYER_COLOURS = {
    hfc_listings:       '#00d4aa',  // teal — COMPANY
    sold_comps:         '#3b82f6',  // royal blue — CMA-S
    active_listings:    '#f59e0b',  // amber — PORTAL
    mic_subjects:       '#64748b',  // slate — CMA-M
    scheme_owners:      '#8b5cf6',  // violet — CMA-O
    tracked_properties: '#14b8a6',  // teal-cyan — TRACKED  ⚠ visually = hfc_listings
};
const LAYER_LETTERS = { hfc_listings: 'H', sold_comps: 'S', active_listings: 'P',
                        mic_subjects: 'M', scheme_owners: 'O', tracked_properties: 'T' };
const LAYER_NAMES   = { hfc_listings: 'HFC Listing', sold_comps: 'Sold Comp',
                        active_listings: 'Portal Stock', mic_subjects: 'MIC Subject',
                        scheme_owners: 'Sectional Scheme', tracked_properties: 'Tracked' };
const COMPOSITE_BG     = '#334155';   // slate-700
const COMPOSITE_BORDER = '#00d4aa';   // teal accent
```

**Problems this creates (the "teal/dark mush"):**

- `hfc_listings` (`#00d4aa`) and `tracked_properties` (`#14b8a6`) are
  near-identical teals — H and T pins are visually indistinguishable at a
  glance. (The user's main complaint.)
- `mic_subjects` (`#64748b` slate) blends into the dark map background and
  composite pin (`#334155` slate) — M-pins disappear.
- `H` is hard-coded — illegal for multi-tenant since every agency would
  see the same letter regardless of brand.
- COMPANY (mandate signed) and PORTAL (unclaimed) sit on adjacent hues
  (teal vs amber) with the same shape — no shape language reinforces
  bucket membership.

**Pin geometry — `index.blade.php:783-857`**

Three render paths, all `L.divIcon`:

- **Scheme** (`display_as='scheme'`): purple `#5b21b6` rounded rectangle,
  28×24 px, `SS` text label, white badge count top-right.
- **Composite** (`display_as='composite'`): slate-700 square with teal
  border, 24×24 px, `+` glyph, white badge count top-right.
- **Single**: category-coloured circle, 22×22 px, letter label, no count.

Every shape has the same 2px white border + same drop shadow. The only
shape language is "scheme rectangle vs everything else circle/square" —
buckets are NOT shape-distinguishable today.

**Selected-pin halo — `index.blade.php:25-41` (already shipped, commit `b5042404`)**

```css
.corex-pin.corex-pin--selected {
    box-shadow:
        0 0 0 3px #00d4aa,
        0 0 0 6px rgba(0, 212, 170, 0.35),
        0 1px 3px rgba(0, 0, 0, 0.4);
    z-index: 1000 !important;
    border-radius: 50%;
}
.corex-pin.corex-pin-scheme.corex-pin--selected,
.corex-pin.corex-pin-composite.corex-pin--selected {
    border-radius: 7px;
}
```

This survives every change in this spec — see §7.

**Marker cluster — `index.blade.php:778-786`**

```javascript
cluster = L.markerClusterGroup({
    disableClusteringAtZoom: 14,
    maxClusterRadius:        40,
    chunkedLoading:          true,
    spiderfyOnMaxZoom:       false,
    showCoverageOnHover:     false,
    zoomToBoundsOnClick:     true,
});
```

No `iconCreateFunction` is supplied → MarkerCluster.Default.css ships
the orange `cluster-small`/`cluster-medium`/`cluster-large` circles
the user has been seeing on screenshots. They are NOT themed per
dominant bucket.

**Zoom-conditional rendering:** none today. Pins are either clustered
(zoom < 14) or shown as the same divIcon (zoom ≥ 14). No labels appear
at any zoom level. Tooltips are hover-only (`m.bindTooltip` at
`index.blade.php:1411`).

### A.2 — Agency logo source

- **Column:** `agencies.logo_path` — added in
  [`2026_02_25_100001_create_agencies_table.php:17`](database/migrations/2026_02_25_100001_create_agencies_table.php).
- **Model:** [`app/Models/Agency.php:54`](app/Models/Agency.php#L54) — listed in `$fillable`.
- **URL pattern:** `asset('storage/' . $agency->logo_path)` — already used at
  [`resources/views/layouts/onboarding-portal.blade.php:36`](resources/views/layouts/onboarding-portal.blade.php#L36).
- **Per-agency:** ✓ — the column is on `agencies`, not on a global config.
- **Map view context today:** the controller passes **nothing** to the
  view ([`MapController.php:41-44`](app/Http/Controllers/Map/MapController.php#L41-L44)
  — `return view('corex.map.index');`). Build prompt will need to pass
  `$agency` (model with `logo_path`, `name`, `slug`) for fallback chain
  resolution.
- **Initials helper:** none on the model today. Build prompt will add a
  `getInitialsAttribute()` (first letter of each word in `name`, capped
  at 3 chars: "Home Finders Coastal" → "HFC", "Smith Realty" → "SR").

**Fallback chain decision** (already in CoreX pattern, mirror it):

1. `$agency->logo_path` set + file exists → `asset('storage/' . logo_path)`.
2. Else → SVG-rendered agency initials (first 1-3 letters of `name`).
3. Else (degenerate: agency has no `name`) → generic house glyph
   (proposed below).

### A.3 — Sectional scheme data

- **Source columns**: `properties.complex_name`, `scheme_owners.scheme_name`,
  `market_reports.subject_scheme_name`. The MapPinService combines these into
  pin titles at [`MapPinService.php:478-479`](app/Services/Map/MapPinService.php#L478-L479):
  `trim($r->scheme_name . ($r->section_number ? ' § ' . $r->section_number : ''))`.
- **Grouper extraction** —
  [`LocationGrouper.php:250-252`](app/Services/Map/LocationGrouper.php#L250-L252):
  `$loc['scheme_name'] = trim(explode(' § ', $first, 2)[0]) ?: 'Sectional Scheme'`.
  Already attached to every `display_as='scheme'` location's payload, so
  the client can render a label without a re-query.
- **CMA Info reference rendering:** searched the codebase, **no existing
  CMA Info-style scheme label implementation exists** (`grep -rn` returns
  no scheme-label code outside the map). We are designing this from
  scratch — see §3.
- **Proposed zoom threshold for labels:** Z ≥ 16 (street-level detail).
  Reasoning: at zoom 14 the cluster un-collapses; from 14-15 the agent
  wants pin shapes for fast scanning; at 16+ the agent is doing
  block-level work where the scheme name "Sunset Manor" or "The Beach
  House" is the answer to *which* building they're looking at. Numbered
  decision in §8 — Johan rules.

### A.4 — `source_class` (own-sold vs market-sold)

Confirmed reachable in the pin payload:

- **toRecord whitelist** —
  [`MapPinService.php:199`](app/Services/Map/MapPinService.php#L199):
  `'source_class' => $pin['source_class'] ?? null`. The field flows
  through `LocationGrouper` to the client unchanged.
- **Per-source-branch:**
  - MRCR sold comps —
    [`MapPinService.php:497`](app/Services/Map/MapPinService.php#L497):
    `'source_class' => 'market'`.
  - PSC presentation comps —
    [`MapPinService.php:550`](app/Services/Map/MapPinService.php#L550):
    `'source_class' => 'market'`.
  - Deals (HFC's own historical sales) —
    [`MapPinService.php:608`](app/Services/Map/MapPinService.php#L608):
    `'source_class' => 'own'`.

Client can switch on `record.source_class === 'own'` inside
`locationIcon()` to render the own-sold variant.

### A.5 — Cluster styling source

- Default Leaflet.markercluster CSS, loaded at
  [`index.blade.php:12`](resources/views/corex/map/index.blade.php#L12):
  `<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />`.
- No `iconCreateFunction` is configured on the `L.markerClusterGroup`
  options — the plugin chooses `marker-cluster-small` / `-medium` /
  `-large` based on child count, all with the orange palette.
- **Theming hook:** `iconCreateFunction(cluster)` receives a
  `MarkerCluster` instance. We can iterate `cluster.getAllChildMarkers()`,
  read each marker's bucket from its `options.bucket` (we'll stamp this
  at creation), and return an `L.divIcon` whose className/inline-style
  carries the dominant bucket's palette. **Yes, fully themable.**

---

## §1 — Bucket → visual treatment matrix

**Design principle:** every row must be distinguishable at a glance — no two
pins share the same shape + colour combination. Shape carries the bucket;
colour carries the sub-type and state.

| Bucket | Pin | Shape | Fill | Stroke | Glyph / label | Size | Notes |
|--------|-----|-------|------|--------|---------------|------|-------|
| **COMPANY** | H | **House silhouette** — pin-drop with a roof line, 24×28 px | `var(--brand-default)` `#0b2a4a` (CoreX navy) | 2px solid white | **Agency logo** (circle-cropped, 16×16 inside) → initials → generic house glyph | 24×28 | The only pin that carries a brand image. Status overlays for `for_sale` / `under_offer` / `sold` come from §6. |
| **PORTAL** | P | **Diamond** (square rotated 45°) — 22×22 px bbox | `var(--ds-amber)` `#f59e0b` | 2px solid white | white "P" inside, 700 weight | 22 | Diamond = "decision point — claim or skip". Distinct shape from every other bucket. |
| **CMA-M** | M | **Soft-cornered square** (2px radius) | `#475569` slate-600 | 1px solid white at 40% opacity | white "M" 600 weight | 18 (smaller — info only) | Smaller + lower contrast intentional: M is context, never the protagonist. |
| **CMA-O** | O | **Soft-cornered rectangle** 26×20 | `#7c3aed` violet-600 (richer than today's `#8b5cf6`) | 2px solid white | "SS" white 700 + count badge top-right | 26×20 | Inherits the existing scheme rectangle visual identity — already the agent's mental model. |
| **CMA-S (market)** | S-market | **Circle** | `#3b82f6` blue-500 | 2px solid white | white "S" 700 + sold-mark from §6 | 22 | Market-sourced sold comp. |
| **CMA-S (own)** | S-own | **Circle** | `var(--brand-default)` `#0b2a4a` navy + `#00d4aa` teal inner ring | 2px solid teal `#00d4aa` | white "S" 700 + sold-mark from §6 | 22 | HFC's own history. Echoes the COMPANY palette (navy + teal) so the agent reads "ours" instantly. |
| **TRACKED** | T | **Hexagon** — 22×22 bbox | `#00d4aa` (the CoreX teal — the spine colour, dedicated to T now) | 2px solid `var(--brand-default)` `#0b2a4a` | white "T" 700 weight | 22 | Hexagon = "in the workshop" — visually distinct from every other shape on the map. Teal is reclaimed for T-only (the user's "spine" framing). |

**The teal mush is resolved by:**
1. Moving COMPANY off teal → CoreX navy `#0b2a4a`.
2. Reserving teal `#00d4aa` exclusively for TRACKED (the spine).
3. The two were #00d4aa + #14b8a6 — now they're navy + teal, full hue
   separation.

**Shape catalogue (one shape per bucket — never duplicated):**

```
COMPANY  ⌂  (house drop)
PORTAL   ◇  (diamond)
TRACKED  ⬡  (hexagon)
CMA-M    ▪  (small square)
CMA-O    ▭  (rectangle with count)
CMA-S    ●  (circle, sold-mark differentiates own vs market)
```

**Selected-state treatment.** The halo system from `b5042404` already
hits every divIcon className via `corex-pin--selected`. For non-round
shapes the existing rule already overrides `border-radius`. Build
prompt extends:

```css
.corex-pin.corex-pin-house.corex-pin--selected     { border-radius: 4px 4px 50% 50%; }
.corex-pin.corex-pin-diamond.corex-pin--selected   { border-radius: 4px; transform-origin: center; }
.corex-pin.corex-pin-hexagon.corex-pin--selected   { border-radius: 6px; }
```

No state mechanic changes — the halo continues to be driven by
`location_key` (see §7).

---

## §2 — Composite pin treatment

When two or more buckets share a single location after `LocationGrouper`
dedup, render the **primary pin from §1** at the location's coordinate
and overlay subordinate buckets as **corner dots** on the primary.

**Primary selection** (already wired in
[`LocationGrouper::PRIORITY`](app/Services/Map/LocationGrouper.php) for
the `display_as` decision):

```
hfc_listings (100) > tracked_properties (90) > active_listings (60)
> sold_comps (40) > scheme_owners (30) > mic_subjects (20)
```

**Subordinate badges:** small dots, **6px diameter**, positioned at the
corners of the primary pin. Each carries the bucket's fill colour from
§1; no glyph. Maximum 3 badge slots (top-right, bottom-right, bottom-left)
— if more buckets are present the +1/+2 count goes on the top-left as a
small numeric.

```
       ◆          ← P badge (top-right corner)
  ⌂                ← H primary
   ●               ← S-market badge (bottom-right)
```

**Tracked badge is special** — already wired as
`$loc['has_tracked_record']` in
[`LocationGrouper.php:199-201`](app/Services/Map/LocationGrouper.php#L199-L201):

> Set when any record at this location is a tracked_properties record
> AND the location is composite. T-alone (record_count=1) is NOT a badge
> — it's a first-class pin in its own right.

When `has_tracked_record === true` the composite gets a **teal hexagon
badge** in the top-right slot (overrides any other badge in that slot —
TRACKED always wins because it's the spine). The user's framing was
explicit: "the Tracked spine ALWAYS adds a tracked badge if present in
the composite."

**Slate composite square** from the current implementation is RETIRED.
Composites no longer have their own "+" shape — they reuse the primary's
shape and add corner dots. Reasoning: "+" is illegible; the corner-dot
language gives the agent the actual bucket mix at a glance.

**Count badge** (the white `record_count` pill top-right that scheme +
composite both carry today) moves to **top-left** to free top-right for
the TRACKED badge, and is only rendered when `record_count > 1`.

---

## §3 — Zoom-conditional rendering

| Zoom | Behaviour |
|------|-----------|
| < 13 (`N₁`) | Pins cluster (themed per §5). No labels, no shapes — clusters carry their dominant-bucket colour + numeric count. |
| 13 – 15 | Cluster un-collapses (`disableClusteringAtZoom: 13`). Pins render per §1. No labels — the agent is scanning shapes. |
| ≥ 16 (`N₂`) | Pins render per §1 PLUS: **sectional scheme labels** appear at the centroid of every `display_as='scheme'` location. **Price labels** appear under H and S pins. Labels suppressed for any other category to avoid clutter. |

**Sectional scheme label** at Z ≥ 16:

- Anchored at the location's `latitude` / `longitude` (already the
  building centroid for scheme pins).
- Style: 11px Plus Jakarta Sans 600, fill `#ffffff`, text-shadow
  `0 0 2px #0b2a4a, 0 0 4px #0b2a4a` for legibility on satellite tiles.
- Content: `loc.scheme_name` (already on the grouper payload) + the
  unit count in parens — e.g. `Sunset Manor (12)`.
- Rendered via `L.marker` with a label-only `L.divIcon` and a separate
  Leaflet layer group `schemeLabelLayer` so the labels can be toggled
  without rebuilding the marker cluster.
- No interaction — labels are read-only at this zoom; clicks go to the
  underlying pin.

**Threshold changes from current:**
- `disableClusteringAtZoom` drops from 14 to 13 — pins appear one zoom
  earlier so the agent has more time looking at shapes before drilling
  in. Recommendation; numbered decision in §8.
- New `N₂ = 16` adds label rendering — zero today.

---

## §4 — Multi-tenant agency logo on H pins

### Resolution path

```
1.  Controller passes $agency to view:
    return view('corex.map.index', ['agency' => $request->user()->effectiveAgency()]);

2.  Blade exposes an asset URL to JS:
    const AGENCY_LOGO_URL = @json($agency->logo_path ? asset('storage/' . $agency->logo_path) : null);
    const AGENCY_INITIALS = @json($agency->getInitialsAttribute());

3.  locationIcon() renders the H pin:
    if (AGENCY_LOGO_URL)  → <img src="...logo..." class="agency-logo">
    else if (AGENCY_INITIALS) → <span class="agency-initials">HFC</span>
    else                  → <svg class="agency-house-glyph">…</svg>
```

### Rendering

- House silhouette: 24×28 px pin-drop with a small roof triangle on top.
  Inner viewport for the logo: 16×16 px circle, centred 4px from the
  base.
- Logo is `object-fit: cover` inside its circle so any aspect ratio
  works.
- Initials fallback: 10px Plus Jakarta Sans 700, white on
  `var(--brand-default)`, capped at 3 chars.
- Generic house glyph: SVG path of a simple house outline, 1px stroke
  white. Used only when agency has no name AND no logo (degenerate
  case).

### Cache

- Logo is loaded once by the page (single `<img>` reused for every H pin
  via a divIcon `<img src>` reference) — browser HTTP cache handles it.
- No JS preloading needed; `<img>` defaults are sufficient.
- If logo file is missing on disk (404), browser falls back to the
  `<img onerror>` handler — build prompt swaps to the initials fallback.

### Multi-tenant guarantee

- The H pin's content is computed ENTIRELY from `$agency` on the server-
  rendered Blade. There is **no path** by which a hard-coded letter
  ("H", "HFC") reaches the client. Build prompt confirms this with a
  grep on the produced markup.
- For System Owner / cross-agency viewers, `$agency` resolves to
  `effectiveAgency()` (the session-selected agency), so a System Owner
  switching agencies sees the new agency's logo on H pins immediately.

---

## §5 — Cluster styling

Themed per dominant bucket in the cluster's child set.

**Algorithm**

```
on cluster render:
    let counts = { COMPANY:0, PORTAL:0, CMA:0, TRACKED:0 };
    for marker in cluster.getAllChildMarkers():
        counts[marker.options.bucket] += 1;
    let dominant = argmax(counts);
    if (no clear majority — top two within 20% of each other):
        dominant = 'MIXED';
    return divIcon themed by dominant.
```

**Palette**

| Dominant | Fill | Stroke | Numeric colour |
|----------|------|--------|----------------|
| COMPANY  | `var(--brand-default)` `#0b2a4a` | 2px white | white |
| PORTAL   | `var(--ds-amber)` `#f59e0b` | 2px white | navy |
| CMA      | `#475569` slate-600 | 2px white at 60% | white |
| TRACKED  | `#00d4aa` teal | 2px navy | navy |
| MIXED    | `var(--brand-default)` `#0b2a4a` + teal `#00d4aa` 2px ring | 2px white | teal |

The Leaflet default orange clusters (`marker-cluster-small/medium/large`)
are SUPPRESSED via an `iconCreateFunction` that returns our themed
divIcon. The default CSS still loads (we keep the link tag) but our
className wins specificity.

**Shape:** circle for all cluster sizes — Leaflet's small/medium/large
size bands stay (40 / 60 / 80 px), only the palette changes.

**Numeric count:** centred, Plus Jakarta Sans 700, 12px for small, 14px
medium, 16px large. No "+" suffix — show actual count.

**Cluster click → expand** is unchanged: `zoomToBoundsOnClick: true`
still drives the existing zoom-to-fit behaviour.

---

## §6 — Sold-mark for S pins

The "this comp is SOLD" indicator is part of the S pin's icon, NOT the
sub-type colour. Two variants by `source_class`:

| Variant | Mark | Reading |
|---------|------|---------|
| `market` | Small white **diagonal slash** across the circle (top-left → bottom-right, 1.5px stroke), partial coverage so the underlying blue stays readable | "another agency sold this" |
| `own` | **Solid teal `#00d4aa` ring** around the navy circle + a thin **teal slash** | "WE sold this — own history" |

Compare:

```
S-market:    ●    ← blue with white slash
S-own:       ◉    ← navy with teal ring + teal slash
```

The slash is intentionally subtle — it doesn't dominate the pin like a
"SOLD" pill would. Read: still a pin, but you know it's history.

**Why a slash and not a banner / pill:** at the 22 px pin size a pill
clutters the cluster grid. A slash is one line of CSS, scales cleanly,
and reinforces the "this is a historical event, line through it" idiom
already familiar from spreadsheets.

**Why own-sold echoes COMPANY palette:** the agent's question on a
sold pin is "is this OURS or theirs?". Putting own-sold into the
navy+teal palette lets the agent read the answer from across the map
without opening the card.

---

## §7 — Backward compatibility / no regressions

1. **Selected-pin halo** (`corex-pin--selected` from commit `b5042404`)
   continues to work. The CSS rule already targets `.corex-pin` (every
   pin still has this base class), and the rounded-rect override at
   `index.blade.php:36-40` already covers `corex-pin-scheme` and
   `corex-pin-composite` — build prompt adds analogous rules for the
   new `corex-pin-house`, `corex-pin-diamond`, `corex-pin-hexagon`
   classes (matrix in §1). State machine
   (`selectedLocationKey` + `markerByLocationKey`) is unchanged.

2. **Owner-gate (Seller View) — unchanged.** This spec touches no
   server-side endpoint. Agency logo is loaded from
   `$agency->logo_path` (the viewing agent's own agency, not the
   subject's owner) — there is no owner PII path.

3. **Cluster click → expand** — unchanged. We replace the cluster's
   divIcon visual via `iconCreateFunction` but keep
   `zoomToBoundsOnClick: true` and `spiderfyOnMaxZoom: false`.

4. **`location_key` stability** — the selection halo, the marker re-use
   on filter toggle, and the badge/composite logic all key off
   `location_key`. This spec adds no new keying; `location_key` remains
   the identity primitive.

5. **POPIA payload contract** — server returns `view_mode` + redacted
   `scheme_owners` payloads as today. The new client-side rendering
   never reads `owner_name` / `owner_phone` / `owner_email` directly;
   it reads `loc.scheme_name` (already redacted-safe) and the
   per-pin category, both of which are POPIA-safe.

6. **Saved-search schema_version** — no payload contract change. The
   saved-search v2 schema (filters + enabled_layers + display_mode +
   base_layer + map_view) still fully describes the view; visual
   identity isn't persisted because it's derived from the bucket and
   the current zoom.

7. **`record_id` shape and the Pitch flow** (from commit `2adf9698`) —
   unchanged. Visual identity is purely client-side rendering on top
   of the existing payload.

---

## §8 — Open questions for Johan

Numbered. Each carries a recommendation; Johan rules.

1. **Bucket palette assignment.**
   *Recommendation:* COMPANY navy `#0b2a4a`, PORTAL amber `#f59e0b`,
   CMA slate `#475569` / violet `#7c3aed` / blue `#3b82f6` (M/O/S),
   TRACKED teal `#00d4aa`. The big move is COMPANY off teal so TRACKED
   can own teal (the "spine" colour). Confirm or swap any.

2. **Shape per bucket.**
   *Recommendation:* COMPANY = house silhouette (24×28), PORTAL =
   diamond (22 bbox), TRACKED = hexagon (22 bbox), CMA-M = small square
   (18), CMA-O = rectangle (26×20, retained), CMA-S = circle (22).
   Each shape distinct. Alternative if hexagon reads too "honeycomb": T
   could be a teal **shield** silhouette. Confirm.

3. **Zoom thresholds.**
   *Recommendation:* N₁ = 13 (cluster→pin transition, one zoom earlier
   than today's 14), N₂ = 16 (labels appear). Confirm or adjust.

4. **Sectional scheme label content at Z ≥ 16.**
   *Recommendation:* `scheme_name + " (" + unit_count + ")"` — e.g.
   `Sunset Manor (12)`. Alternative: just `scheme_name` (cleaner, but
   loses the density hint). Which?

5. **Sold-mark visual on S pins.**
   *Recommendation:* diagonal slash for both variants; own-sold also
   gets a navy+teal ring to read as "ours". Alternative: small "SOLD"
   pill at the top-right corner (more legible at distance, but eats
   pixels). Pick.

6. **Cluster colour when no bucket dominates.**
   *Recommendation:* MIXED variant = navy with teal ring. Alternative:
   slate-600 (`#475569`) with no accent — "neutral, dig in". Pick.

7. **Generic house glyph fallback.**
   *Recommendation:* simple SVG outline house, 1px white stroke on
   navy. Build prompt picks a public-domain glyph from Lucide or draws
   from scratch — Johan to indicate library preference or "draw your
   own".

8. **House silhouette outer shape.**
   *Recommendation:* classic map-pin teardrop with a roof triangle on
   top — combines "this is a map pin" semantics with "this is a
   property" semantics. Alternative: pure house outline without the
   teardrop (cleaner, but loses pin-affordance). Pick.

9. **Agency-logo upload UX.**
   The spec assumes `agencies.logo_path` is already populated. If it's
   empty for a given agency, the initials path kicks in. *Question
   for build prompt scope:* do we add a logo-upload field to the
   agency settings page in this build, or assume out-of-band uploads
   for now? Recommendation: leave the upload UX out of this build —
   the agency settings page is a separate area; this prompt is map
   visuals.

10. **Composite badge max count.**
    Today no max — every category in a composite gets badged. With 3
    corner slots + 1 numeric overflow, max visible buckets = 3 + count.
    *Recommendation:* hard cap at 3 distinct badges; everything beyond
    rolls up into a `+N` numeric on the top-left. Confirm 3 is the
    right number (vs 4 — 4 corners, all used).

11. **Tracked badge wins top-right always.**
    *Recommendation:* yes — TRACKED is the spine, badge slot priority
    is `TRACKED > PORTAL > CMA-S > CMA-M > CMA-O`. Confirm hierarchy.

12. **Z ≥ 16 price labels under H and S.**
    *Recommendation:* yes — `R 1,420,000` (or `Sold R 1.4M` for S).
    Alternative: no labels at any zoom, keep the map clean. Pick.

---

## Appendix — what changes vs what stays

**Changes (new code in the build prompt):**
- `LAYER_COLOURS` and `LAYER_LETTERS` constants — partially replaced by
  bucket-keyed visuals + a new `BUCKET_OF` map.
- `locationIcon()` adds 4 new shape variants (house, diamond, hexagon,
  small-square) + reads `source_class` for sold variants.
- New `agencyLogoSvg()` / `agencyInitialsSvg()` / `genericHouseSvg()`
  helpers behind a single `companyPinInner()` chooser.
- New `iconCreateFunction` on the marker cluster.
- New `schemeLabelLayer` (`L.layerGroup`) toggled at Z ≥ 16.
- New CSS in `@push('head')` for the additional pin shape classes and
  their selected-state variants.
- `MapController::index` extends to pass `$agency`.
- `Agency::getInitialsAttribute()` added.

**Stays (untouched by this spec):**
- Server payload shape from `MapPinService` and `LocationGrouper`.
- POPIA owner-detail gate, Seller View redaction, permission gates.
- `record_id` shape, Pitch flow, activity log endpoints.
- Saved-search v2 payload.
- Halo state machine (`selectedLocationKey`, `markerByLocationKey`,
  `selectLocation`, `clearSelection`).
- Cluster behaviour (click → expand, no spiderfy at max zoom).
- Tooltips on hover.
