# Presentation auto-generation — data lineage audit

> **Status:** read-only investigation artefact.
> **Date:** 2026-05-29.
> **Branch:** `feature/presentation-data-audit`.
> **Purpose:** map every data source the auto-generated seller presentation
> touches, identify the exact wiring points for the four upcoming builds
> (property-type filtering / tickbox selection / condition-driven valuation
> / report-tab toggles), and confirm-or-refute six user-reported bugs from
> the local 10-page example.
>
> Every claim carries a `file:line` citation.

---

## §1 — Entry point map

### Trigger UI

The agent clicks **Generate Presentation** on the property detail page:

[resources/views/corex/properties/show.blade.php:257-270](resources/views/corex/properties/show.blade.php#L257-L270) — Alpine button posting to `corex.properties.generate-presentation`.

### Route

[routes/web.php:1844](routes/web.php#L1844):

```php
Route::post('/{property}/generate-presentation',
    [\App\Http\Controllers\Presentation\PresentationGeneratorController::class, 'generate'])
```

### Controller

[`PresentationGeneratorController::generate`](app/Http/Controllers/Presentation/PresentationGeneratorController.php#L29-L98) — validates `asking_price`, `comp_scope` (`radius_all|suburb_only`), `comp_radius_m` (50–5000), then delegates to the orchestrating service.

Critical behaviour:

- Permission gate: `create_presentations`.
- Agency-scope guard: `(int) $property->agency_id !== (int) $user->effectiveAgencyId()` → 403.
- On success → **redirects directly to `presentations.show`** (no review step). See §3-B.

### Orchestrator

[`PresentationGeneratorService::generateForProperty`](app/Services/Presentations/PresentationGeneratorService.php#L54-L240). Runs the whole pipeline inside a single `DB::transaction`:

1. Upsert `Presentation`.
2. Market-Analytics run (persist=true).
3. Sale-Probability run (persist=true).
4. Geo backfill for subject GPS.
5. `MicSnapshotHydrator` — copy MIC evidence into per-presentation tables.
6. `HoldingCostEstimator` — auto-fill monthly costs.
7. `AnalysisDataService::compile` — produce `computed_json` snapshot.
8. `PresentationCompilerService::compile` — freeze a `PresentationVersion`.
9. Dispatch `PresentationGenerated`.

### PDF/HTML render pipeline

**This is the key correction one of my survey passes initially got wrong.** The 10-page PDF the agent reviewed is **NOT** rendered from `resources/views/presentations/public/show.blade.php` (that's the seller-facing interactive page). It is rendered by **`PresentationPdfService::buildHtml`** — an inline-PHP heredoc that constructs the HTML, then `puppeteer` (Chrome-headless, via `scripts/html-to-pdf.mjs`) prints it to PDF.

| Layer | File |
|---|---|
| Inline PHP HTML builder | [`PresentationPdfService::buildHtml`](app/Services/Presentations/PresentationPdfService.php#L52) |
| PDF entry | [`PresentationPdfService::generate`](app/Services/Presentations/PresentationPdfService.php#L26) |
| Headless Chrome bridge | [`scripts/html-to-pdf.mjs`](scripts/html-to-pdf.mjs) |
| Spatial-comp SVG | [`PresentationPdfService.php:1108`](app/Services/Presentations/PresentationPdfService.php#L1108) → [`SpatialViewSvgRenderer::render`](app/Services/Presentations/Pdf/SpatialViewSvgRenderer.php#L28) |

The PDF file is constructed by string concatenation inside `buildHtml`, with `<?php // PAGE N — ... ?>` comment markers separating sections. The 10-page structure the user saw maps to those markers.

`public/show.blade.php` is a **separate** code path — the live HTML page sent to sellers via the public snapshot link. It and `seller-live.blade.php` (interactive simulator) share concepts with the PDF view but are NOT the source of the 10-page PDF.

---

## §2 — Per-data-block lineage

### 2.1 — Subject property data

[`properties`](database/migrations/2026_02_25_201319_create_properties_table.php#L11-L48) carries `address`, `suburb`, `latitude`, `longitude`, `beds`, `baths`, `garages`, `size_m2`, `erf_size_m2`, `property_type`, `status`, `price` and (via later migrations) `category`. `Property` model `$fillable` at [`app/Models/Property.php:21-148`](app/Models/Property.php#L21-L148).

**`condition` column on properties: DOES NOT EXIST today.** The only `condition` field in the codebase is on `market_report_comp_rows` (per-comp). Subject condition has nowhere to live yet. **This is the §4 BUG and the §3-C wiring requirement.**

The presentation pipeline pulls the subject's enriched fact set in [`AnalysisDataService::compileSubjectProperty`](app/Services/Presentations/AnalysisDataService.php#L79-L98):

```php
return [
    'address'        => $fields->get('subject.address')?->final_value ?? $p->property_address,
    'suburb'         => $fields->get('subject.suburb')?->final_value ?? $p->suburb,
    'erf'            => $fields->get('subject.erf')?->final_value,
    'extent_m2'      => $this->intOrNull($fields->get('subject.extent_m2')?->final_value),
    'gps'            => $fields->get('subject.gps')?->final_value,
    'purchase_date'  => $fields->get('subject.purchase_date')?->final_value,
    'purchase_price' => $this->intOrNull($fields->get('subject.purchase_price')?->final_value),
    'municipal_value' => $this->intOrNull($fields->get('municipal.total_value')?->final_value),
];
```

→ persisted to `PresentationSnapshot.computed_json['subject']`. **But never read by `PresentationPdfService::buildHtml` for a top-of-report facts card** (see BUG-5).

### 2.2 — Property type / category storage

- **Per-property:** free-text string column `properties.property_type` (`house`, `flat`, `vacant_land`, `sectional`, etc.) — no FK, no enum.
- **Per-presentation category UI (Residential / Commercial / Industrial / Retirement / Holiday / Project):** **DOES NOT EXIST in storage today.** No `property_categories` table, no agency-level category config rows. The user's mental model of "settings UI for categories" hasn't been built — only `agencies.presentations_default_*` settings exist.
- **One classification helper exists but is unused:** [`MicSnapshotHydrator.php:231`](app/Services/Presentations/MicSnapshotHydrator.php#L231) computes `'property_type_kind' => $this->classifyType((string) $presentation->property_type)` (sectional vs full bucket from [L274-L279](app/Services/Presentations/MicSnapshotHydrator.php#L274-L279)). It's stored in the hydration config but **never passed to `collectMatchedRows`** — it's dead data.

### 2.3 — Comparable sales query (the core)

[`MicSnapshotHydrator::collectMatchedRows`](app/Services/Presentations/MicSnapshotHydrator.php#L294-L362). The selection chain:

```php
$query = DB::table('market_report_comp_rows')
    ->whereNull('deleted_at')
    ->where('row_type', $rowType)
    ->where('is_demo', $subjectIsDemo);
if ($rowType === MarketReportCompRow::ROW_COMP) {
    $query->whereNotNull('sale_date')->whereNotNull('sale_price');
    $query->where(function ($q) use ($subjectReportIds, $cfg) {
        if (!empty($subjectReportIds)) $q->whereIn('market_report_id', $subjectReportIds);
        $q->orWhere(function ($q2) use ($cfg) {
            $q2->whereBetween('sale_date', [$cfg['date_from'], $cfg['date_to']]);
        });
    });
}
```

Then a post-fetch filter ([L340-L362](app/Services/Presentations/MicSnapshotHydrator.php#L340-L362)) keeps a row if any of:
1. It comes from the subject's source report.
2. Its `suburb_normalised` contains the subject suburb.
3. Scope is `radius_all` and Haversine distance ≤ `comp_radius_m`.

**Filters NOT applied: `property_type`.** This is the **headline bug** the user reported (vacant-land subject matched against sectional-title comps). The dead `property_type_kind` value sits in `$cfg` but never reaches this query.

**Agent control over comp selection BEFORE generation: NONE.** The only pre-flight knobs are `asking_price`, `comp_scope`, `comp_radius_m` (controller validation list at [PresentationGeneratorController.php:43-46](app/Http/Controllers/Presentation/PresentationGeneratorController.php#L43-L46)). The agent never sees a tick-each-comp screen. See §3-B.

### 2.4 — Active listings / competition

Two sources both feed the "Active Competition" page:

- **`PortalCapture` (manual PDF uploads)** — [`AnalysisDataService` L39](app/Services/Presentations/AnalysisDataService.php#L39):
  `PortalCapture::where('presentation_id', $presentation->id)->where('parse_status', 'parsed')->get()`. Agent-driven evidence (scan of a Property24 page, etc.).
- **`presentation_active_listings`** populated by `MicSnapshotHydrator` (same `market_report_comp_rows` with `row_type='listing'`).

**Not pulled from `properties.status='active'`.** HFC's own stock doesn't drive the competition page today.

### 2.5 — P24 inflow (7 / 30 / 90 day)

[`AbsorptionInflowService` L159-L180](app/Services/Presentations/Analytics/AbsorptionInflowService.php#L159-L180):

```php
$query = P24Listing::where('first_seen_date', '>=', $since)
    ->whereBetween('asking_price', [$priceLow, $priceHigh]);
if (!empty($suburbs)) { $query->where(...LOWER(suburb)...); }
if (!empty($types))    { $query->where(...LOWER(property_type)...); }
return $query->count();
```

The query DOES filter by `suburb` AND `property_type` — both LIKE/LOWER. The "0 / 0 / 0" on the local report is **CONFIRMED-no-data**, not a query bug. There are no `p24_listings` rows for the subject's suburb-type combination in local dev (likely "Vacant Land" in the subject's suburb has no captured alerts).

### 2.6 — Sold-data scopes

Three distinct scopes are wired in [`MicSnapshotHydrator::collectMatchedRows` L340-L362](app/Services/Presentations/MicSnapshotHydrator.php#L340-L362):

| Scope | Selector | Notes |
|---|---|---|
| Same-subject | `whereIn('market_report_id', $subjectReportIds)` | Reports the subject appears in. |
| Suburb-wide | `LIKE` on `suburb_normalised` | Default fallback. |
| Vicinity (radius) | Haversine ≤ `comp_radius_m` when `comp_scope='radius_all'` | The "vicinity sales" the report shows. |

**Complex / scheme-wide scope is NOT separately implemented.** Sectional-scheme fingerprinting in [MicSnapshotHydrator.php:278](app/Services/Presentations/MicSnapshotHydrator.php#L278) is for de-dup only; there is no "all units in scheme X" query path.

### 2.7 — Holding cost inputs

Two services collaborate. The **inputs-resolver** is [`HoldingCostEstimator`](app/Services/Presentations/HoldingCostEstimator.php); the **maths reducer** is [`HoldingCostService L20-L43`](app/Services/Presentations/HoldingCostService.php#L20-L43):

```php
$monthly = (float)($inputs['bond_payment']    ?? 0)
         + (float)($inputs['rates']            ?? 0)
         + (float)($inputs['levies']           ?? 0)
         + (float)($inputs['insurance']        ?? 0)
         + (float)($inputs['utilities']        ?? 0)
         + (float)($inputs['opportunity_cost'] ?? 0);
```

The six components are read from `presentations.monthly_*` columns (manual on the presentation row). If blank → defaults to 0.

**Agency-level defaults exist** ([migration 2026_05_23_180001_add_holding_cost_defaults_to_agencies.php](database/migrations/2026_05_23_180001_add_holding_cost_defaults_to_agencies.php#L23-L52)):

| Column | Default | Unit |
|---|---|---|
| `presentations_default_rates_per_million_zar` | 800 | R/month per R1M value |
| `presentations_default_levies_sectional_per_m2_zar` | 25 | R/month per m² |
| `presentations_default_insurance_per_million_zar` | 200 | R/month per R1M |
| `presentations_default_utilities_zar` | 1200 | R/month flat |
| `presentations_default_opportunity_cost_pct` | 8.00 | annual % on equity |

These ARE consumed by `HoldingCostEstimator` to seed the `presentations.monthly_*` columns at generation time.

**Verdict (% of holding-cost inputs auto-filled):** **All six are auto-filled** from agency defaults × property facts (price, erf_size_m2 for sectional levies). When the property row is missing the field (e.g. no `erf_size_m2` on a sectional), the affected component lands at 0. **No component is hardcoded outside the agency setting; no component is "blank by design".** But the agent can still override on the presentation row.

### 2.8 — CMA valuation bands (Lower / Middle / Upper)

[`AnalysisDataService::compileCmaValuation` L267-L303](app/Services/Presentations/AnalysisDataService.php#L267-L303):

```php
$lower  = $this->intOrNull($fields->get('cma.lower_range')?->final_value);
$middle = $this->intOrNull($fields->get('cma.middle_range')?->final_value);
$upper  = $this->intOrNull($fields->get('cma.upper_range')?->final_value);
```

**There is no maths.** The bands are read from the `presentation_fields` table, where they were extracted from uploaded CMA PDFs by [`DocumentExtractor::parseCma` L120-L122](app/Support/Presentation/DocumentExtractor.php#L120-L122):

```php
$this->matchPrice($text, 'Lower\s*Range',  $fields, 'cma.lower_range');
$this->matchPrice($text, 'Middle\s*Range', $fields, 'cma.middle_range');
$this->matchPrice($text, 'Upper\s*Range',  $fields, 'cma.upper_range');
```

If the CMA source PDF uses a slightly different label for the middle band, the regex misses, the field is never created, `?->final_value` returns null, `intOrNull(null)` returns null, the Blade renders `—`. The Lower/Upper bands extract fine — only **Middle** misses. See BUG-1.

**There is no condition / comp-driven / R-per-m² fallback for the bands.** No calc → no recovery path.

### 2.9 — Suburb statistics

[`MicSnapshotHydrator` L445-L521](app/Services/Presentations/MicSnapshotHydrator.php#L445-L521) — sourced from `market_data_points`:

- Median + sales count for the latest year (the populated row): keys `suburb_median_price_year` + `suburb_sales_count_year`.
- Low / High / Maximum range: keys `suburb_low_year`, `suburb_high_year`, `suburb_max_year`.

Same shape as the CMA bands: each key is looked up; if the row doesn't exist, the field is silently skipped. See BUG-6.

### 2.10 — Absorption / selling-probability maths

[`AbsorptionInflowService`](app/Services/Presentations/Analytics/AbsorptionInflowService.php). The formulae are explicit:

```php
$newListingRate     = $count90d > 0 ? round($count90d / 3, 1) : 0;
$netAbsorption      = round($monthlySales - $newListingRate, 1);
$adjustedMonths     = round($activeListings / $netAbsorption, 1);   // months-of-supply
$monthlyRate        = max(0.0, min(1.0, $monthlySales / $activeListings));
$monthlyProbability = round($monthlyRate * 100, 1);
$prob3Months        = round((1 - pow(1 - $monthlyRate, 3)) * 100, 1);
// 3-month adjusted accounts for inflow growing the pool:
$avgPool            = $activeListings + ($newListingRate - $monthlySales) * 1.5;
$adjustedProb3      = round((1 - pow(1 - ($monthlySales / $avgPool), 3)) * 100, 1);
$poolAfter3Months   = (int) round($activeListings + ($newListingRate * 3) - ($monthlySales * 3));
```

`$count90d` comes from the inflow query in §2.5; `$monthlySales` from the sold-comps scope in §2.6.

### 2.11 — Spatial map render

`PresentationPdfService.php` embeds the SVG inline at [L1108](app/Services/Presentations/PresentationPdfService.php#L1108):

```php
<?= (new \App\Services\Presentations\Pdf\SpatialViewSvgRenderer())->render(
    ['lat' => (float) $_subjLat, 'lng' => (float) $_subjLng, 'title' => $address],
    $_svgComps, 540, 360,
) ?>
```

[`SpatialViewSvgRenderer::render` L28-L123](app/Services/Presentations/Pdf/SpatialViewSvgRenderer.php#L28-L123) returns an SVG `<svg>...</svg>` string — pure markup, no raster, no Leaflet. Haversine distance + bearing per comp, then plotted on a fixed polar grid (540×360 px). DomPDF / puppeteer print it as embedded vector.

---

## §3 — Wiring points for the four upcoming builds

### A — Property-type filtering on comp selection

**Single best hook:** [`MicSnapshotHydrator::collectMatchedRows` between L312 and L319](app/Services/Presentations/MicSnapshotHydrator.php#L312-L319), inside the main `$query` builder, before the suburb/radius post-filter. A `->where('property_type', ...)` (or `whereIn` for a category) clause lands here.

**What needs to exist alongside:**

1. **A category→type mapping** (does NOT exist today). The subject's "category" (Residential / Commercial / Industrial / Retirement / Holiday / Project) must map to a set of `market_report_comp_rows.property_type` strings. Best location: a new `config/presentation-categories.php` or an `agency_presentation_category_mappings` table (per-agency overrides).
2. **A presentations-categories settings UI** (does NOT exist today). The user's mental model of "settings → categories" needs a model + migration + Blade. None of those are wired.
3. **Hook the already-computed `property_type_kind`** at [MicSnapshotHydrator.php:231](app/Services/Presentations/MicSnapshotHydrator.php#L231) — currently dead — into the WHERE.

**Open: Property model already has `category`** ([Property.php $fillable L21-L148](app/Models/Property.php#L21-L148)) — confirm what this column means in current usage before assuming it can be re-purposed for the new taxonomy.

### B — Tickbox review UI before generation

**Current flow has no review step.** Confirmed by reading both [`PresentationGeneratorController::generate` L96](app/Http/Controllers/Presentation/PresentationGeneratorController.php#L96) and the routes group at [routes/web.php:2183-2200](routes/web.php#L2183-L2200): the controller redirects directly to `presentations.show`. There is no `presentations.review` route. The agent clicks Generate → version is compiled → redirect to the rendered presentation.

**Single best insertion point:** between compile and dispatch in [`PresentationGeneratorService::generateForProperty` L220-L234](app/Services/Presentations/PresentationGeneratorService.php#L220-L234):

```php
$version = $this->compiler->compile($presentation->id, $agentUserId);
// ← INSERT review-gating here ←
// $version->status = 'pending_review'; $version->save();
PresentationGenerated::dispatch($presentation, $version);
return $version;
```

And the controller's redirect at [PresentationGeneratorController.php:96](app/Http/Controllers/Presentation/PresentationGeneratorController.php#L96) chooses `presentations.review` vs `presentations.show` based on the version's status.

**Review page must cover at minimum:** tick-list of sections (blueprint keys from [PresentationBlueprintService::blueprintV1 L33-L47](app/Services/Presentations/PresentationBlueprintService.php#L33-L47)); tick-list of comps (`presentation_sold_comps` rows); confirm subject category; "Finalise & Generate" button that marks `reviewed` and lets PDF render proceed.

### C — Condition-driven valuation (multiplier on bands)

**Single best hook:** between band extraction and return in [`AnalysisDataService::compileCmaValuation` L267-L282](app/Services/Presentations/AnalysisDataService.php#L267-L282):

```php
$lower  = $this->intOrNull($fields->get('cma.lower_range')?->final_value);
$middle = $this->intOrNull($fields->get('cma.middle_range')?->final_value);
$upper  = $this->intOrNull($fields->get('cma.upper_range')?->final_value);
// ← INSERT condition-multiplier here ←
// $adj = $this->resolveConditionAdjustment($presentation);
// if ($adj !== 1.0) { foreach ($lower/$middle/$upper) *= $adj; }
```

**Storage that needs to exist:**

1. **Subject condition column** — `properties.condition` (enum/string) does not exist. Add migration.
2. **Per-agency condition→multiplier matrix** — model the same way as existing holding-cost defaults. Migration template: [database/migrations/2026_05_23_180001_add_holding_cost_defaults_to_agencies.php](database/migrations/2026_05_23_180001_add_holding_cost_defaults_to_agencies.php). One column per condition tier (`presentations_condition_excellent_pct` = 1.05, `presentations_condition_good_pct` = 1.00, `presentations_condition_fair_pct` = 0.93, `presentations_condition_needs_work_pct` = 0.85 — defaults to ratify with Johan).
3. **`resolveConditionAdjustment($presentation)` helper** — reads the subject's condition + the agency settings. Lives on `AnalysisDataService` or a thin new service.

**Note on UX:** If the agent overrides the band on the presentation row directly, the multiplier still applies — caller must decide whether `_override` columns bypass adjustment. Open question for §5.

### D — Report-tab section toggles

**Single best hook:** the section-emit decision inside the PDF builder. There are TWO candidate sites:

1. **Frozen-version layer** — [`PresentationBlueprintService::blueprintV1` L33-L47](app/Services/Presentations/PresentationBlueprintService.php#L33-L47) returns the canonical list. [`PresentationCompilerService` (around L61)](app/Services/Presentations/PresentationCompilerService.php) calls it. Filtering here gates the SectionsArray BEFORE freeze, so it's persisted with the version.

2. **Render-time layer** — `PresentationPdfService::buildHtml` emits each section between `<?php // PAGE N — ... ?>` markers. A render-time toggle bypasses the persistence layer.

**Recommendation:** filter in the **compiler** so the section list is materialised on the version. Render layer just iterates whatever the version says. Keeps PDF builder dumb.

**Agency-level defaults already exist** — `agencies.presentations_default_show_*` columns are in `Agency.php $fillable` at [Agency.php:104-108](app/Models/Agency.php#L104-L108):

```php
'teaser_default_show_suburb_stats',
'teaser_default_show_market_position',
'teaser_default_show_asking_range',
'teaser_default_show_holding_cost_summary',
```

**But these are TEASER-section defaults, not the full-report toggle pattern.** The full-report section visibility hasn't been wired. Add a parallel `presentations_default_show_<section_key>` set keyed to the blueprint, OR an array column.

---

## §4 — Known bugs — confirmed / refuted

### BUG-1 — CMA Middle band renders "—" (Lower + Upper populate)

**CONFIRMED.** Pure document-extraction miss.

[`DocumentExtractor::parseCma` L120-L122](app/Support/Presentation/DocumentExtractor.php#L120-L122) tries three near-identical regexes. The Middle one (`'Middle\s*Range'`) failed against the CMA PDF for this subject — possibly because the PDF used a synonym ("Mid Range", "Median Range") or because line-break placement broke the match. The Lower and Upper regexes succeeded.

[`AnalysisDataService::compileCmaValuation` L270](app/Services/Presentations/AnalysisDataService.php#L270) reads `$fields->get('cma.middle_range')?->final_value` → null → `intOrNull(null)` returns null → Blade `{{ $middle ?? '—' }}`.

**There is no fallback** — no `(Lower + Upper) / 2` rescue, no comp-median substitute. The intent appears to be "Middle Range is whatever the CMA author wrote; if missing, the report ships with `—`".

**Recommended fix track (out of scope for this audit):** seed `$middle = (int) round(($lower + $upper) / 2)` when the field is missing AND both bands populate. State the substitution in the report copy ("midpoint inferred").

### BUG-2 — Subject erf/extent missing on page 2

**REFUTED (locally) / partially CONFIRMED depending on which page.** The Blade emits the erf conditionally at [`public/show.blade.php:202`](resources/views/presentations/public/show.blade.php#L202):

```blade
@if($presentation->erf_size_m2)<tr><th>Erf size</th><td>{{ $presentation->erf_size_m2 }} m²</td></tr>@endif
```

If the report shows no extent on page 2, the root cause is **`presentations.erf_size_m2` is NULL on this row** — not a missing Blade field. Verify with: `select erf_size_m2 from presentations where id = N`. For vacant-land subjects this often means the source `properties.erf_size_m2` was never captured.

**However — page 1's missing subject facts is real (BUG-5 below).**

### BUG-3 — Vacant-land subject matched against sectional comps

**CONFIRMED.** The comp query at [`MicSnapshotHydrator::collectMatchedRows` L300-L362](app/Services/Presentations/MicSnapshotHydrator.php#L300-L362) applies **zero** `property_type` filtering. The dead `property_type_kind` at [L231](app/Services/Presentations/MicSnapshotHydrator.php#L231) is computed but never reaches the `$query`. Any comp within radius/suburb/date passes regardless of whether the subject is vacant land and the comp is a sectional unit.

This is the §3-A wiring requirement.

### BUG-4 — `Vacant_land` literal underscore in footer

**CONFIRMED.** [`public/show.blade.php:198`](resources/views/presentations/public/show.blade.php#L198):

```blade
@if($presentation->property_type)<tr><th>Type</th><td>{{ ucfirst($presentation->property_type) }}</td></tr>@endif
```

`ucfirst('vacant_land')` → `'Vacant_land'`. Should be `Str::headline($presentation->property_type)` or `Str::title(str_replace('_', ' ', $type))`. Fix is one-line; surface in the build prompt.

### BUG-5 — Subject facts card missing from page 1

**CONFIRMED.** The data exists: [`AnalysisDataService::compileSubjectProperty` L79-L98](app/Services/Presentations/AnalysisDataService.php#L79-L98) compiles a full subject dict (address, suburb, erf, extent_m2, GPS, purchase_date, purchase_price, municipal_value) into `PresentationSnapshot.computed_json['subject']`.

The **PDF builder never renders a page-1 facts card from this dict.** A search for `$subject` reads in [`PresentationPdfService`](app/Services/Presentations/PresentationPdfService.php) shows no top-of-report facts emission for the full dict — only address + property_type appear in the cover header.

The seller-facing `public/show.blade.php` has a Property Summary section ([L193-L204](resources/views/presentations/public/show.blade.php#L193-L204)) that emits Type / Beds / Baths / Floor / Erf conditionally — but NOT extent, GPS, municipal valuation, owner, or last sale. The same gap exists in the PDF.

**Fix shape:** add a `_subject-facts-card.blade.php` partial reading `PresentationSnapshot.computed_json['subject']`, `@include` from page 1 of the PDF builder.

### BUG-6 — Suburb Low / High / Maximum show "—"

**CONFIRMED** — same root cause as BUG-1, different fields. [`MicSnapshotHydrator` L499-L519](app/Services/Presentations/MicSnapshotHydrator.php#L499-L519) tries to read `market_data_points` rows keyed by `suburb_low_year`, `suburb_high_year`, `suburb_max_year`. If the CMA-document parser never extracted those metrics into `market_data_points` for the relevant suburb-year, the lookup fails silently and the field is never written. The Blade renders the missing field as `—`.

**Fix shape:** either back-fill the metrics from comp data (`min/max/percentile` of `sale_price` in the same date window), or surface a "limited data" notice instead of `—`.

---

## §5 — Open questions / contradictions for Johan

1. **CMA Middle fallback policy.** Option A: leave `—` (honest "the CMA author didn't fill it"). Option B: compute midpoint of Lower/Upper and label it "Inferred midpoint". Option C: use median of selected comps. Which?

2. **Property `category` column.** `Property.php $fillable` carries `category` today — what does it currently mean? Is it safe to repurpose for the new presentation-category taxonomy, or is it already used for something (commission scheme, listing channel, …)?

3. **Category → property_type mapping.** The new categories (Residential / Commercial / Industrial / Retirement / Holiday / Project) need a mapping to the existing `market_report_comp_rows.property_type` strings ('house', 'flat', 'townhouse', 'apartment', 'sectional', 'vacant_land', etc.). Single global mapping in `config/`, or per-agency overrides in a settings table?

4. **Condition tier values.** Five tiers (Excellent / Good / Fair / Needs Work / Distressed)? Or four? Specific default multipliers per tier?

5. **Override interaction with condition adjustment.** If the agent overrides `cma.lower_range_override` on the presentation row, does the condition multiplier still apply, or does override mean "this is the final number"?

6. **Section-toggle defaults vs the existing teaser-only `presentations_default_show_*` columns.** Extend with parallel full-report columns, or migrate the teaser ones into a single shared array column?

7. **Tickbox review UI scope.** Is it:
   - just a confirmation page (tick → generate), or
   - a "select comps" page (tick exact comps to include from the auto-selected pool, plus add manual ones), or
   - a full edit-everything pre-flight (agent can override prices, narrative, sections)?

8. **`presentations.erf_size_m2` source of truth.** Today the field is on the presentation row (copied from `properties.erf_size_m2`). For a vacant land subject where the property record has no erf, the presentation also has no erf — but the CMA PDF the agent uploaded might carry the extent under `subject.extent_m2` in `presentation_fields`. Should `compileSubjectProperty` (or a render-time helper) fall back to `subject.extent_m2` when `presentations.erf_size_m2` is null?

---

## Appendix — key files referenced

| Concern | File |
|---|---|
| Trigger button | [resources/views/corex/properties/show.blade.php:257-270](resources/views/corex/properties/show.blade.php#L257-L270) |
| Controller | [app/Http/Controllers/Presentation/PresentationGeneratorController.php](app/Http/Controllers/Presentation/PresentationGeneratorController.php) |
| Orchestrator | [app/Services/Presentations/PresentationGeneratorService.php](app/Services/Presentations/PresentationGeneratorService.php) |
| Compiler / freeze | [app/Services/Presentations/PresentationCompilerService.php](app/Services/Presentations/PresentationCompilerService.php) |
| Blueprint (section list) | [app/Services/Presentations/PresentationBlueprintService.php](app/Services/Presentations/PresentationBlueprintService.php) |
| Snapshot data builder | [app/Services/Presentations/AnalysisDataService.php](app/Services/Presentations/AnalysisDataService.php) |
| MIC hydration / comps | [app/Services/Presentations/MicSnapshotHydrator.php](app/Services/Presentations/MicSnapshotHydrator.php) |
| Holding-cost (maths) | [app/Services/Presentations/HoldingCostService.php](app/Services/Presentations/HoldingCostService.php) |
| Holding-cost (inputs) | [app/Services/Presentations/HoldingCostEstimator.php](app/Services/Presentations/HoldingCostEstimator.php) |
| Absorption / probability | [app/Services/Presentations/Analytics/AbsorptionInflowService.php](app/Services/Presentations/Analytics/AbsorptionInflowService.php) |
| CMA PDF extraction | [app/Support/Presentation/DocumentExtractor.php](app/Support/Presentation/DocumentExtractor.php) |
| Spatial SVG | [app/Services/Presentations/Pdf/SpatialViewSvgRenderer.php](app/Services/Presentations/Pdf/SpatialViewSvgRenderer.php) |
| PDF builder (the 10-page source) | [app/Services/Presentations/PresentationPdfService.php](app/Services/Presentations/PresentationPdfService.php) |
| Headless Chrome bridge | [scripts/html-to-pdf.mjs](scripts/html-to-pdf.mjs) |
| Public seller view (NOT the PDF) | [resources/views/presentations/public/show.blade.php](resources/views/presentations/public/show.blade.php) |
| Properties migration | [database/migrations/2026_02_25_201319_create_properties_table.php](database/migrations/2026_02_25_201319_create_properties_table.php) |
| Holding-cost defaults migration | [database/migrations/2026_05_23_180001_add_holding_cost_defaults_to_agencies.php](database/migrations/2026_05_23_180001_add_holding_cost_defaults_to_agencies.php) |
