# Market Intelligence Cluster — State of the Nation Audit

**Date:** 2026-05-21
**Author:** VS Code Claude (read-only investigation)
**Recipient:** Johan Reichel
**Scope:** Every artefact that touches Market Intelligence — ingestion sources, convergence (Tracked Properties), buyer matching, claim system, UI, suburb aggregates, Ellie hooks, CMA ingestion, permissions, known bugs/gaps, agent walkthrough.
**Constraint:** No code, schema, or DB changes. All counts are real, captured from `nexus_os` on 2026-05-21.

**Pre-reads completed:** `CLAUDE.md`, `.ai/STANDARDS.md`, `.ai/specs/build-f-market-intelligence-redesign-spec.md`, `.ai/specs/market-intelligence-discovery.md` (2026-05-14), `.ai/specs/prospecting-intelligence-spec.md`, `.ai/specs/portal-leads.md`, `.ai/specs/p24-syndication.md`, `.ai/specs/flows-map.md`, `.ai/specs/mobile-core-matches.md` (lightly), `.ai/specs/marketing-permission-spec.md` (out-of-scope, not read).

---

═══════════════════════════════════════════════════════════════
SECTION 1: DATA INGESTION LAYER
═══════════════════════════════════════════════════════════════

## 1.1 P24 Email Alerts (IMAP → parse → store)

**Status:** **LIVE, currently dormant.** Code path exists end-to-end; last successful run **2026-04-16 11:04:57** on `nexus_os` (~5 weeks ago).

**Entry point:**
- Job: [app/Jobs/ImportP24AlertsJob.php](app/Jobs/ImportP24AlertsJob.php)
- Console: [app/Console/Commands/ImportP24Alerts.php](app/Console/Commands/ImportP24Alerts.php)
- Service: [app/Services/P24/P24ImapImportService.php:25-210](app/Services/P24/P24ImapImportService.php#L25-L210)
- Parser: [app/Services/P24/P24EmailParserService.php](app/Services/P24/P24EmailParserService.php) (regex on email body)
- Manual trigger: [P24Controller::runImport()](app/Http/Controllers/Admin/P24Controller.php#L179-L185) → `POST /admin/p24/import`
- Config: `config/services.php` → `p24_imap` block (env vars `P24_IMAP_*`, `P24_IMPORT_ENABLED`).

**Storage tables (legacy island, NOT agency-scoped):**
- `p24_listings` — every listing referenced in an email (no `agency_id` column, shared across whole DB)
- `p24_price_changes` — observed price changes (FK → p24_listings)
- `p24_import_log` — per-email audit trail with `email_uid` dedup key, status, listings_found/new/updated

**Volume on local DB:**
- `p24_listings`: **3,878 rows**
- `p24_import_log`: last successful entry 2026-04-16 11:04:57 ("Import run: ..." run-summary rows)

**Address handling:** P24 emails hide the full street address — `p24_listings` typically holds **suburb only**. Cross-referenced into `prospecting_listings` via `portal_ref` LIKE `P24-%` (MarketIntelligenceController.php:248-265).

**Known bugs / gaps:**
- **Not scheduled in `routes/console.php` / `app/Console/Kernel.php`** — no cron. Imports only when an admin clicks the runImport button. The 5-week stale `last_seen_at` confirms it.
- **No `agency_id` on `p24_listings`** — the table predates multi-tenancy. Cross-agency leakage potential if HFC ever onboards another agency.
- **No structured address — only suburb + price + beds + type.** The Tracked Property convergence depends on `portal_ref` because there's nothing else to match on.

## 1.2 P24 Syndication API (outbound — sibling system)

**Status:** LIVE. Out of MIC scope but lives next door.

- Service: [app/Services/Syndication/Property24/Property24ApiClient.php](app/Services/Syndication/Property24/Property24ApiClient.php)
- Service: [app/Services/Syndication/Property24/Property24SyndicationService.php](app/Services/Syndication/Property24/Property24SyndicationService.php)
- Service: [app/Services/Syndication/Property24/Property24ListingMapper.php](app/Services/Syndication/Property24/Property24ListingMapper.php)
- Service: [app/Services/Syndication/Property24/P24LeadService.php](app/Services/Syndication/Property24/P24LeadService.php) — referenced by Portal Leads spec (§1.7 below)
- Spec: `.ai/specs/p24-syndication.md`

The outbound feed pushes HFC's mandated stock to Property24. The lead-pull (`P24LeadService::pullLeads`) is the planned inbound side that drives Portal Leads (§1.7).

## 1.3 Chrome Capture / Portal Capture scraper

**Status:** **LIVE — by volume the largest ingestion channel.**

**Entry point:**
- Route: `POST /portal-captures/ingest` ([routes/web.php:2617-2618](routes/web.php#L2617-L2618)), middleware `auth.portal_capture` (custom token-based)
- Controller: [app/Http/Controllers/Presentation/PortalCaptureController.php](app/Http/Controllers/Presentation/PortalCaptureController.php) `ingest` ~line 2493 of routes (controller method ingest in same file)
- Presentation routes: `GET /presentations/{id}/portal-captures` etc. at [routes/web.php:2075-2082](routes/web.php#L2075-L2082)
- Extractors: `app/Services/Presentations/Evidence/Extractors/Property24SearchExtractorV1.php`, `Parsers/Property24ListingParserV1.php`, `Parsers/Property24SearchParserV1.php`

**Storage tables:**
- `portal_captures` — raw per-capture (HTML on disk, screenshot PNG, extracted_fields, jsonld, found_image_urls)
- `portal_listings` — dedup'd listing identity by `portal_listing_id` with `first_seen_at`/`last_seen_at`/`current_fields_json`
- `portal_listing_observations` — historical price/availability changes over time
- `prospecting_listings` — the "active prospecting pipeline" view, also written here

**Volume on local DB:**
- `portal_captures`: **554 rows**
- `portal_listings`: **4,691 rows**
- Routes through `TrackedPropertyMatchOrCreateService` → produces the bulk of tracked properties (see §2.7)

**Known bugs / gaps:**
- Extractor strategy is multi-layered (`og:price` → JSON-LD → DOM class → regex fallback) — brittle to portal HTML changes. Failures are silent (extracted_fields just empty).
- Cross-portal dedup uses `prospecting_listings.normalized_address` + `property_group_id` (separate from the Tracked Property graph — see §10).

## 1.4 Private Property feed

**Status:** SPLIT — outbound SOAP syndication LIVE; **inbound listing discovery NOT IMPLEMENTED**.

**Outbound (syndication, sibling system):**
- [app/Services/PrivateProperty/PrivatePropertySoapClient.php](app/Services/PrivateProperty/PrivatePropertySoapClient.php)
- Push: `UpdateListing`, `UpdateAgent`, status updates, image uploads.
- Inbound from outbound feed: [app/Jobs/ProcessPrivatePropertyEventFeed.php:23-58](app/Jobs/ProcessPrivatePropertyEventFeed.php#L23-L58) polls `GetListingEventFeed` but only for HFC's own stock (`pp_syndication_status` updates).
- **Webhook**: `POST /api/pp/webhook` creates a `Contact` + `CommandTask` (§1.7 builds Portal Leads on top of this).

**Inbound listing discovery:** **None.** `GetActiveListings` is wrapped in [PrivatePropertySoapClient.php:107-314](app/Services/PrivateProperty/PrivatePropertySoapClient.php#L107-L314) but no code calls it for MIC purposes.

The 85 PP rows in `prospecting_listings` come from the **Chrome Portal Capture extension** scraping portal.pp.co.za pages, NOT from SOAP.

**Known bugs / gaps:** the SOAP-side inbound discovery is not used. If TVA-style owner enrichment lands, this is the place it would plug in.

## 1.5 CMA Info report imports

**Status:** **Partially built — only via presentation upload, not a standalone pipeline.**

**Entry point:**
- Upload only via the presentation UI: `POST /presentations/{presentation}/upload` ([app/Http/Controllers/Presentation/PresentationController.php:601-626](app/Http/Controllers/Presentation/PresentationController.php#L601-L626)).
- No standalone "upload CMA report" route, no email-attachment ingester, no scheduled folder poll for CMAInfo PDFs.

**Parser chain:**
- Text: [app/Domain/Presentation/TextExtractionService.php:20-46](app/Domain/Presentation/TextExtractionService.php#L20-L46) (pdftotext → Smalot fallback)
- Field router: `app/Services/Presentations/Evidence/UploadExtractionService.php`
- Per-type parsers: `app/Services/Presentations/Evidence/Parsers/CmaParserV1.php`, `SalesReportParserV1.php`, `SuburbStockParserV1.php`
- Field-level regex: [app/Support/Presentation/DocumentExtractor.php:67-403](app/Support/Presentation/DocumentExtractor.php#L67-L403) (flag `presentation_doc_extract_v1`)
- Back-propagation: [app/Services/Presentation/PropertyCmaPropagationService.php](app/Services/Presentation/PropertyCmaPropagationService.php) — feeds the Tracked Property graph via `TrackedPropertyMatchOrCreateService`.

**Storage tables:** `presentation_uploads`, `presentation_sold_comps`, `presentation_active_listings`, `presentation_fields`. None carry `agency_id` directly (inherit via the presentation's `branch_id` — multi-tenancy gap flagged in the 2026-05-14 discovery audit §1.4).

**Volume on local DB:**
- `presentation_uploads`: **60 rows**
- `presentation_sold_comps`: **212 rows**
- `presentation_active_listings`: **528 rows**
- `presentation_fields`: **277 rows**

**Source of the 12 CMAINFO Tracked Property rows:** `PropertyCmaPropagationService` is the only writer that emits source.type='cmainfo' — see §8.4.

**Known bugs / gaps:**
- CMA data is locked inside individual presentations. Cross-presentation aggregation is not implemented.
- Per-comp erf/GPS not parsed (data is in `raw_row_json` but only price/date/type/beds/baths/size are pulled out).
- No CMA "upload" UI outside a presentation context.

## 1.6 Manual prospect entry

**Status:** EXISTS — via the Tracked Property + Prospecting flow.

**Entry point:** No dedicated "Add tracked property" form was found. Manual entries reach the graph via:
- The seller-outreach composer when an agent pitches a non-mandate property.
- Demo seeder: [`DemoDataSeeder` writes source type `manual_prospect_entry`](database/seeders/DemoDataSeeder.php) (1 row in local data, confirming the path runs).
- Any code path that calls `TrackedPropertyMatchOrCreateService::matchOrCreate()` with `source.type='manual_prospect_entry'`.

**Volume on local DB:** **1 row** flagged `manual_prospect_entry` in `tracked_property_external_refs`.

**Known gaps:** No UI exists for an agent to manually add a Tracked Property today. If an agent walks a street and notes a property by hand, there's no front door.

## 1.7 Portal Leads (P24 + PP unified enquiry ingest)

**Status:** **SPECCED + MIGRATION WRITTEN, BUT NOT YET MIGRATED LOCALLY.** Migration file dated 2026-05-20. Table absent from `nexus_os`.

**Entry point:**
- Spec: `.ai/specs/portal-leads.md` (approved 2026-05-20, owner Andre)
- Migration: [database/migrations/2026_05_20_000001_create_portal_leads_table.php](database/migrations/2026_05_20_000001_create_portal_leads_table.php)
- Model: [app/Models/PortalLead.php](app/Models/PortalLead.php)
- Controller: [app/Http/Controllers/CoreX/PortalLeadController.php](app/Http/Controllers/CoreX/PortalLeadController.php) (index/poll/markNotified — already written)
- Routes: [routes/web.php:1843-1851](routes/web.php#L1843-L1851)
- Sidebar nav: [resources/views/layouts/corex-sidebar.blade.php:409-411](resources/views/layouts/corex-sidebar.blade.php#L409-L411) (rendered only when route is registered)
- Permission: `access_portal_leads` registered at [config/corex-permissions.php:276-277](config/corex-permissions.php#L276-L277)

**Storage table (per migration):** `portal_leads` — id, agency_id, portal enum('p24','pp'), lead_type, listing_id, listing_portal_ref, contact_id, contact_exists, existing_contact_agent_id, name/email/phone/message, is_whatsapp, lead_source_raw (json), received_at, notified_at, timestamps + softDeletes.

**Volume on local DB:** Table does not exist (`MIGRATION NOT YET RUN`).

**Known bugs / gaps:**
- **Migration unrun on local** — every reference to `PortalLead::query()` will explode on local until `php artisan migrate` runs.
- `P24LeadService::pullLeads()` is referenced in the spec but the file's pull cadence (`PullP24LeadsJob` every 15 min via `routes/console.php`) is not yet visible in the codebase — Andre's work in flight.
- PP webhook integration uses an observer (`CommandTaskPortalLeadObserver`) per spec — also flagged for Andre.

## 1.8 CoreX internal listing data (own stock)

**Status:** LIVE (the Property pillar — separate from MIC by design).

**Table:** `properties` (~129 columns; the canonical pillar table).
- Identifier columns: `id`, `external_id` (UUID), `p24_ref`, `p24_listing_number`, `pp_ref`, `pp_listing_feed_ref`, `property_number`, `stand_number`, `pp_suburb_id`.
- Address columns: `address`, `street_number`, `street_name`, `suburb`, `town`, `city`, `district`, `province`, `region`, `unit_number`, `floor_number`, `unit_section_block`, `complex_name`.
- Geo: `latitude`, `longitude` decimal(10,7) — populated only from P24 CSV import per the discovery audit §6.

**Volume on local DB:** **52 properties** (not soft-deleted) across **1 agency**.

MIC's "in-stock filter" excludes any prospecting listing whose `matched_property_id` is non-null — i.e. properties that have already become CoreX stock disappear from the canvassing surface ([MarketIntelligenceController.php:587-596](app/Http/Controllers/CoreX/MarketIntelligenceController.php#L587-L596)).

---

═══════════════════════════════════════════════════════════════
SECTION 2: CONVERGENCE / ADDRESS SPINE
═══════════════════════════════════════════════════════════════

## 2.1 Table name + schema

**`tracked_properties`** — migration [database/migrations/2026_05_14_170000_create_tracked_properties_table.php](database/migrations/2026_05_14_170000_create_tracked_properties_table.php).

Every column:

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `agency_id` | FK → agencies, cascadeOnDelete | multi-tenancy via BelongsToAgency |
| `external_id` | UUID UNIQUE | auto-generated in `booted()` |
| `street_number` | string(50) nullable | |
| `street_name` | string(200) nullable | normalised on write (St→Street, Rd→Road, …) |
| `unit_number` | string(50) nullable | |
| `complex_name` | string(200) nullable | |
| `suburb` | string(100) nullable | |
| `suburb_normalised` | string(100) nullable | lowercase+strip-punct+trim, set on every save |
| `town` | string(100) nullable | |
| `province` | string(100) nullable | |
| `postal_code` | string(20) nullable | |
| `latitude` / `longitude` | decimal(10,7) | portal-derived |
| `cma_gps_lat` / `cma_gps_lng` | decimal(10,7) | deeds-office authoritative from CMAInfo OCR |
| `erf_number` | string(100) nullable | |
| `title_deed_number` | string(100) nullable | |
| `cadastral_extent` | string(50) nullable | kept as string to preserve "1 116 m²" formatting |
| `municipal_valuation` | decimal(15,2) | |
| `municipal_valuation_year` | usmallint | |
| `last_known_asking_price` | decimal(15,2) | newer-source-wins |
| `last_known_sold_price` | decimal(15,2) | newer-source-wins |
| `last_known_sold_date` | date | |
| `property_type` | string(50) | |
| `bedrooms` / `bathrooms` / `garages` | utinyint | |
| `floor_size_m2` / `erf_size_m2` | decimal(10,2) | |
| `promoted_to_property_id` | FK → properties, nullOnDelete | linkage to Agency Stock |
| `promoted_at` | timestamp | |
| `promoted_by_user_id` | FK → users, nullOnDelete | |
| `source_chain` | json | append-only audit of every contribution |
| `first_seen_at` | timestamp | |
| `last_enriched_at` | timestamp | |
| `last_enrichment_source` | string(100) | |
| `status` | enum('active','archived','duplicate','promoted') | default 'active' |
| `duplicate_of_tracked_property_id` | unsignedBigInteger nullable | |
| `created_at`/`updated_at`/`deleted_at` | timestamps + softDeletes | |

**Indexes** (migration lines 92-97):
- `idx_tracked_props_agency_suburb` (agency_id, suburb_normalised)
- `idx_tracked_props_agency_erf` (agency_id, erf_number)
- `idx_tracked_props_agency_status` (agency_id, status)
- `idx_tracked_props_promoted` (promoted_to_property_id)
- `idx_tracked_props_geo` (latitude, longitude) — NB: not a spatial index
- `idx_tracked_props_cma_geo` (cma_gps_lat, cma_gps_lng)

## 2.2 Source tagging

**`tracked_property_external_refs`** — migration [database/migrations/2026_05_14_170001_create_tracked_property_external_refs_table.php](database/migrations/2026_05_14_170001_create_tracked_property_external_refs_table.php).

Columns: id, agency_id, tracked_property_id (FK), source_type (string), source_ref (string), source_payload (json), first_seen_at, last_seen_at, timestamps + softDeletes.

One row per `(agency_id, source_type, source_ref)` — the mapping table for "which source IDs point to this canonical property". Plus `tracked_properties.source_chain` (json append-only) is the audit log.

## 2.3 Address normalisation logic

| Logic | File:line |
|---|---|
| Suburb normalisation | [TrackedProperty::normaliseSuburb()](app/Models/Prospecting/TrackedProperty.php#L99-L106) — lowercase, trim, strip punctuation, collapse spaces |
| Street normalisation | [TrackedPropertyMatchOrCreateService::normaliseStreetName()](app/Services/Prospecting/TrackedPropertyMatchOrCreateService.php#L349-L360) — `\bst\.?\b` → Street, `\brd\.?\b` → Road, etc.; title-cased |
| Suburb on save | [TrackedProperty::booted()](app/Models/Prospecting/TrackedProperty.php#L74-L93) — fires on creating + updating |
| Token extraction | [extractAddressTokens()](app/Services/Prospecting/TrackedPropertyMatchOrCreateService.php#L362-L368) — strip non-word, split, drop tokens < 3 chars |
| Legacy duplicate logic | [ProspectingListing::normalizeAddress()](app/Models/ProspectingListing.php#L100) — lowercase + strip + append suburb (separate from TP normalisation) |
| Cross-portal dedup | `prospecting_listings.normalized_address` + `property_group_id` (different mechanism from TP graph) |

## 2.4 Deduplication logic — 5-strategy match-or-create

[TrackedPropertyMatchOrCreateService::resolveMatch()](app/Services/Prospecting/TrackedPropertyMatchOrCreateService.php#L98-L189) — first match wins:

1. **Source-ref exact** ([TPMOCS:101-114](app/Services/Prospecting/TrackedPropertyMatchOrCreateService.php#L101-L114)) — `tracked_property_external_refs(agency_id, source_type, source_ref)`. Strongest signal.
2. **GPS proximity** ([:117-137](app/Services/Prospecting/TrackedPropertyMatchOrCreateService.php#L117-L137)) — within `GPS_TOLERANCE_DEGREES = 0.00005` (≈5m) on `cma_gps_lat/lng` first, then `latitude/longitude`.
3. **Erf + suburb** ([:139-148](app/Services/Prospecting/TrackedPropertyMatchOrCreateService.php#L139-L148)) — exact erf_number + normalised suburb.
4. **Normalised structured address** ([:150-160](app/Services/Prospecting/TrackedPropertyMatchOrCreateService.php#L150-L160)) — street_number + normalised street_name + normalised suburb.
5. **Token overlap** ([:162-186](app/Services/Prospecting/TrackedPropertyMatchOrCreateService.php#L162-L186)) — same normalised suburb + ≥ 2 significant tokens (≥ 3 chars) shared.

**Enrich vs Create:** ([TPMOCS:217-271](app/Services/Prospecting/TrackedPropertyMatchOrCreateService.php#L217-L271)) — first-source-wins for stable identifiers; `NEWER_WINS_FIELDS = ['municipal_valuation','municipal_valuation_year','last_known_asking_price','last_known_sold_price','last_known_sold_date']` overwrite on enrich.

## 2.5 Agent override paths

**Present:**
- `TrackedPropertyController::promote()` ([TrackedPropertyController.php:120-148](app/Http/Controllers/CoreX/TrackedPropertyController.php#L120-L148)) — the only write action exposed via the Tracked Properties list. Calls `promoteToStock()`.

**Missing:**
- **No "edit address" UI** on the Tracked Property detail page.
- **No "force-merge two TPs" UI** (the `duplicate_of_tracked_property_id` column exists, no UI sets it).
- **No "force-split" UI.**
- The `TrackedPropertyController::show()` view ([:102-118](app/Http/Controllers/CoreX/TrackedPropertyController.php#L102-L118)) is read-only.

## 2.6 Promotion to stock

[TrackedPropertyMatchOrCreateService::promoteToStock()](app/Services/Prospecting/TrackedPropertyMatchOrCreateService.php#L381-L450).

- Creates a `Property` row (`status='draft'`, `listing_type='sale'`, with agent_id/branch_id from the promoting user).
- Sets `tracked_properties.promoted_to_property_id`, `promoted_at`, `promoted_by_user_id`, `status='promoted'`.
- Fires `TrackedPropertyPromotedToStock` domain event.
- TP record is **preserved** as the audit trail (source_chain intact); the Property is the operational record.
- Throws `\DomainException` if the promoting user has no branch_id and none is supplied via `$propertyFields`.

## 2.7 Counts on local DB

```
Total tracked:              4,912 active+promoted
By source (DISTINCT TP):
  chrome_capture            4,530   ← Chrome extension dominates
  p24                         356
  pp                           50
  cmainfo                      12
  manual_prospect_entry         1
By status:
  active                    4,911
  promoted                      1
With address (street_name not null):     327
Without address:                       4,585  ← suburb-only TPs
External refs total:                   5,526  (some TPs have multiple source refs)
```

**Interpretation:**
- 93% of TPs are address-less (suburb-only). They sit on the graph waiting for an address-bearing source (Chrome capture of the listing page) to enrich them.
- The 1:1 ratio of source refs to TPs (5,526 refs / 4,912 TPs) means most TPs have a single source — true cross-source convergence is small.
- Only **1 promoted** TP on local — the promote path is exercised but not tested at scale.
- Only **12 CMAINFO** rows — corresponds to the screenshot tag the user referenced.

---

═══════════════════════════════════════════════════════════════
SECTION 3: BUYER MATCHING (CoreMatch)
═══════════════════════════════════════════════════════════════

## 3.1 Buyer requirements table

**`contact_matches`** — the unified wishlist table (one row per buyer × wishlist scenario; a buyer can have multiple wishlists).

Key columns:
- `id`, `contact_id`, `agency_id`, `name`, `status` (`active`/`paused`/`fulfilled`/`expired`), `is_primary`, `share_token`, `share_slug`
- `listing_type` (sale/rental), `category`, `property_type` (deprecated), `property_types` (json)
- `price_min`, `price_max`
- `beds_min`, `bedrooms_max`, `baths_min`, `garages_min`, `parking_min`
- `floor_size_min/max`, `erf_size_min/max`
- `suburb` (deprecated), `suburbs` (json)
- `must_have_features` / `nice_to_have_features` / `deal_breakers` (json)
- `notes`, `hidden_property_ids` (json), `property_view_counts` (json)
- `last_engaged_at`, `auto_archive_at`
- `created_by_user_id`, `updated_by_user_id`, timestamps + soft deletes

Model: [app/Models/ContactMatch.php](app/Models/ContactMatch.php) — STATUS_ACTIVE/PAUSED/FULFILLED/EXPIRED.

## 3.2 Matching service file path + main function

Primary matching pipeline:
- **Scoring entrypoint:** `App\Services\PropertyMatchScoringService` (referenced from [MarketIntelligenceController.php:390](app/Http/Controllers/CoreX/MarketIntelligenceController.php#L390) `->isRegenerating()`, also [:1292](app/Http/Controllers/CoreX/MarketIntelligenceController.php#L1292) `->getProspectingDemand()`).
- **Stock matching against own properties:** [app/Services/Prospecting/ProspectingStockMatchService.php](app/Services/Prospecting/ProspectingStockMatchService.php) — 2-pass: exact normalised → fuzzy suburb+street overlap. Runs when a prospecting listing is promoted to a Property.
- **Tier classification:** [app/Services/Prospecting/BuyerMatchTierService.php](app/Services/Prospecting/BuyerMatchTierService.php) — strong/mid/weak/total counts per listing (used by `SuggestedActionResolver`).

## 3.3 Scoring tiers — thresholds + definitions

**Tier enum on `prospecting_buyer_matches`:** `perfect`, `strong`, `approximate`.

**Threshold table:** `buyer_match_tiers` ([database/migrations/2026_05_14_150000_create_buyer_match_tiers_table.php](database/migrations/2026_05_14_150000_create_buyer_match_tiers_table.php)). Per-agency rows define the score boundaries. Seeded by `BuyerMatchTiersSeeder`.

**`MarketIntelligenceController` uses score-based thresholds inline:**
- Strong = `score ≥ 80` ([MarketIntelligenceController.php:628](app/Http/Controllers/CoreX/MarketIntelligenceController.php#L628), [:756](app/Http/Controllers/CoreX/MarketIntelligenceController.php#L756), [:898](app/Http/Controllers/CoreX/MarketIntelligenceController.php#L898))
- Match floor = `score ≥ 50` ([MarketIntelligenceController.php:292](app/Http/Controllers/CoreX/MarketIntelligenceController.php#L292), [show():1283](app/Http/Controllers/CoreX/MarketIntelligenceController.php#L1283))
- "High value" = `strong_count ≥ thresholds.high_value_strong_min` (from `suggested_action_thresholds`)

## 3.4 Where matches are stored

| Table | Purpose | Key cols |
|---|---|---|
| `contact_matches` | wishlists (the requirements) | contact_id, agency_id, status, all the *_min/*_max/json fields |
| `prospecting_buyer_matches` | wishlist × prospecting_listing scoring | prospecting_listing_id, contact_id, agency_id, score, tier (`perfect`/`strong`/`approximate`), matched_features (json), missing_features (json), matched_at, last_recompute_at, dismissed_at |
| `property_buyer_matches` | wishlist × Property scoring (Agency Stock) | property_id, contact_id, agency_id, score, … (same shape) |
| `contact_match_feedback` | per-property reaction (interested/not_interested/saved) + note | one row per reaction |
| `contact_match_notifications` | per-property notification log + match `score` 0-100 | one row per send |

## 3.5 What surfaces consume match results today

- **Market Intelligence index (Work mode)** — listing rows show `buyer_match_count` + `buyer_match_top_score` via the controller's groupby on `prospecting_buyer_matches` ([MarketIntelligenceController.php:284-306](app/Http/Controllers/CoreX/MarketIntelligenceController.php#L284-L306)).
- **Slide-over Buyers tab** — [`buyerMatches()`](app/Http/Controllers/CoreX/MarketIntelligenceController.php#L925-L941) → `BuyerMatchTierService::buyersForListing()` → partial `prospecting._buyer-matches-panel`.
- **Listing detail page** — [`show()`](app/Http/Controllers/CoreX/MarketIntelligenceController.php#L1273-L1302) joins `prospecting_buyer_matches` × `contacts` for tier-ranked buyer list.
- **Action presets** — "Pitch now · high" / "Pitch now" use strong-tier counts ([MarketIntelligenceController.php:622-643](app/Http/Controllers/CoreX/MarketIntelligenceController.php#L622-L643)).
- **Demand-supply matrix** — `DemandSupplyMatrixService` (Analyse mode).
- **Opportunity pockets** — `computeDemandPockets()` ([:888-923](app/Http/Controllers/CoreX/MarketIntelligenceController.php#L888-L923)) + `OpportunityPocketService`.
- **Strategic brief narrative** — `StrategicBriefService` (Analyse hero) — buyer counts.
- **Contact pillar** — Core Matches tab on the contact detail page (mobile API: `App\Http\Controllers\Api\MobileCoreMatchController`).

## 3.6 Count of `contact_matches` on local DB

- **Total:** 33
- **Active + not soft-deleted:** 32

## 3.7 Count of match records on local DB

- **`prospecting_buyer_matches`:** **21,259** (perfect 1,641 / strong 8,967 / approximate 10,651)
- **`property_buyer_matches`:** **673**

## 3.8 Does CoreMatch join Tracked Properties — or only own listings?

**Neither directly.** CoreMatch joins to:
- `prospecting_listings` (via `prospecting_buyer_matches.prospecting_listing_id`) — the **prospecting** pillar, separate from TP.
- `properties` (via `property_buyer_matches.property_id`) — Agency Stock.

**There is NO `tracked_property_buyer_matches` table.** Tracked Properties are reached only **indirectly** through `prospecting_listings.tracked_property_id` (added by migration 2026_05_14_180000) — see [`TrackedPropertyController::buildIntelligence()`](app/Http/Controllers/CoreX/TrackedPropertyController.php#L154-L178) which reads `prospecting_listings WHERE tracked_property_id = ?`.

So a Tracked Property surfaces buyer matches only if it also has a `prospecting_listing` row. Pure-CMA TPs (12 today) have no listing, therefore no buyer matches.

---

═══════════════════════════════════════════════════════════════
SECTION 4: CLAIM SYSTEM
═══════════════════════════════════════════════════════════════

## 4.1 Claim table

**`prospecting_claims`** — migration [database/migrations/2026_03_18_140000_create_prospecting_claims_table.php](database/migrations/2026_03_18_140000_create_prospecting_claims_table.php).

Columns: id, agency_id, prospecting_listing_id, user_id, status, notes, claimed_at, feedback_at, last_updated_at, released_at, flagged_at, is_active.

Status enum (from [ProspectingClaim model](app/Models/ProspectingClaim.php)): `claimed | contacted | meeting_set | listing | not_interested | lost`.

Plus **`prospecting_pitch_locks`** — soft 30-min lock when an agent clicks Pitch (created by [`ProspectingClaimService::createTempLock()`](app/Services/Prospecting/ProspectingClaimService.php#L39-L81)).

## 4.2 48-hour auto-expiry logic — where, scheduled how

**Defined but not strongly enforced.**

- `ProspectingClaim::isExpired()` returns true after 48h with no feedback (referenced in [MarketIntelligenceController::claim() line 1172](app/Http/Controllers/CoreX/MarketIntelligenceController.php#L1172) — when a new agent tries to claim, the controller marks the existing expired claim as released).
- **Temp pitch locks** auto-expire on read: [ProspectingClaimService:42-51](app/Services/Prospecting/ProspectingClaimService.php#L42-L51) reaps expired rows at every `createTempLock()` call.
- **No scheduled job** is registered to sweep claims at 48h + flag the BM. The expiry only triggers on user action (the next claim attempt, or the "Expiring" action preset surfacing in the UI).
- **Threshold tunable** via `suggested_action_thresholds.expiry_warning_hours` — read by `applyActionPreset('expiring', ...)` ([MarketIntelligenceController.php:663-673](app/Http/Controllers/CoreX/MarketIntelligenceController.php#L663-L673)).

## 4.3 WhatsApp-action permanent-claim path

**Exists, partly.** [`ProspectingClaimService::consumeLockAsPermanentClaim()`](app/Services/Prospecting/ProspectingClaimService.php#L94-L142) is the upgrade-temp-to-permanent path. Triggered from `ComposerController::submit` (the seller-outreach composer) when a contact is resolved via `matched_property_id` — see the docblock at [ProspectingClaimService.php:84-93](app/Services/Prospecting/ProspectingClaimService.php#L84-L93).

The WhatsApp side of this is in the seller-outreach module; a "WhatsApp prospecting modal" is mentioned in `.ai/specs/prospecting-intelligence-spec.md` as a *future* spec but no `.ai/specs/whatsapp-prospecting-spec.md` exists yet. The pitch-via-WhatsApp flow piggybacks on the email composer with channel='whatsapp'.

## 4.4 Feedback obligation tracking

Feedback is tracked on the claim itself:
- `prospecting_claims.feedback_at` — timestamp set when feedback recorded
- `prospecting_claims.notes` — append-only timeline (PREpended by [`ProspectingClaimService::recordActionOnClaim`](app/Services/Prospecting/ProspectingClaimService.php#L151-L165))
- `prospecting_claims.status` — moves through the enum as feedback comes in

**No separate `claim_feedback` table.** The claim row IS the feedback log.

## 4.5 Branch manager view of claimed-without-feedback

**Exists via the action preset, not a dedicated screen.**

- Action preset `expiring` (Row 2 of stats strip) filters to "current user's claims, no feedback, hours_left < threshold" ([MarketIntelligenceController.php:663-673](app/Http/Controllers/CoreX/MarketIntelligenceController.php#L663-L673)).
- **No BM-wide "show all stale claims across the agency" view** is implemented in MIC — `expiring` is owner-scoped. A BM viewing the same screen sees only their own claims.
- Stats strip shows agency-wide totals via `$claimStats` ([MarketIntelligenceController.php:380-388](app/Http/Controllers/CoreX/MarketIntelligenceController.php#L380-L388)):
  - `my_claims` (owner-scoped)
  - `total_claimed` (agency-wide)
  - `expiring_soon` (claimed >24h ago, no feedback) — agency-wide

Manager release with reason: `releaseAsManager` ([:1242-1271](app/Http/Controllers/CoreX/MarketIntelligenceController.php#L1242-L1271)) — requires `prospecting_setup.manage`. UI mount unclear.

**Spec gap noted in build-f-spec §12:** a `flagToManager()` endpoint + modal was scoped but the controller method is NOT in the current `MarketIntelligenceController.php`. The strategic brief mentions "Review stale claims" link → `action_preset=my_claims` ([StrategicBriefService.php:127-129](app/Services/MarketIntelligence/StrategicBriefService.php#L127-L129)).

## 4.6 Counts on local DB

```
Total claims:                        6
Active (is_active=1 + no released_at):  4
  Of which past 48h, no feedback:    4     ← all 4 active claims are expired-by-time
  Expiring in 24-48h window, no fb:  0
Released:                            2
With feedback recorded:              1
Without feedback (active only):      4     ← 100% of active claims have no feedback
```

**Interpretation:** The local sample is very small (6 claims total) and every single active claim is past the 48h SLA without feedback. With no scheduled BM flag/email, these just sit there.

---

═══════════════════════════════════════════════════════════════
SECTION 5: MARKET INTELLIGENCE SCREENS (UI)
═══════════════════════════════════════════════════════════════

## 5.1 `/admin/p24` — P24 Alerts dashboard (legacy)

- Route: `GET /admin/p24` → `admin.p24.index` ([routes/web.php:317](routes/web.php#L317))
- Middleware: `permission:manage_p24`
- Controller: [Admin\P24Controller::index()](app/Http/Controllers/Admin/P24Controller.php#L16-L149)
- View: `admin.p24.index` (not confirmed read but referenced [:128](app/Http/Controllers/Admin/P24Controller.php#L128))
- Companion routes: `GET /admin/p24/listings` ([:318](routes/web.php#L318)), `POST /admin/p24/import` ([:319](routes/web.php#L319))
- Companion controller: `Admin\P24SuburbController` — suburb CRUD at `/admin/p24-suburbs/*` ([routes/web.php:703-709](routes/web.php#L703-L709))

What it shows:
- KPIs: last import timestamp, emails processed 30d, total listings, active listings, IMAP configured?, new this month, monthly change %, avg asking price, most-active suburb.
- Suburb stats table (avg/min/max/count, new this month) — group-by suburb on `p24_listings` ([:52-64](app/Http/Controllers/Admin/P24Controller.php#L52-L64)).
- Listings by suburb collapsible.
- Recent listings (last 200, client-side filtered).
- Price changes (last 200).
- Import log (last 50).

## 5.2 `/corex/market-intelligence` — the live cockpit (Work mode)

- Route: `GET /corex/market-intelligence` → `market-intelligence.index` ([routes/web.php:2516](routes/web.php#L2516))
- Middleware: `permission:access_prospecting`
- Controller: [MarketIntelligenceController::index()](app/Http/Controllers/CoreX/MarketIntelligenceController.php#L37-L501)
- View: `corex.market-intelligence.index`

What it shows (Work mode, default):
- Top bar + mode toggle (Work / Analyse)
- Stats strip Row 1: active, buyer_matched, in_stock, new_today, cross_listed
- Stats strip Row 2 (action presets, clickable filters): pitch_now_high / pitch_now / log_outcomes / my_claims / expiring
- Filter rail (suburbs / types / beds aggregates + demand pockets)
- Action list (listing rows with Build-E suggested-action chips + inline icon buttons)
- Detail slide-over (loaded async via `details()` endpoint)
- Flag-to-BM modal — **scaffold exists but endpoint not yet wired in the controller** (spec §12)

## 5.3 `/corex/market-intelligence?mode=analyse` — Analyse mode

- Same route, dispatched via the `if ($request->query('mode') === 'analyse')` branch ([MarketIntelligenceController.php:45-52](app/Http/Controllers/CoreX/MarketIntelligenceController.php#L45-L52)).
- Controller handler: [`analyse()` :513-574](app/Http/Controllers/CoreX/MarketIntelligenceController.php#L513-L574).
- Orchestrator: [app/Services/MarketIntelligence/AnalyseModeOrchestrator.php](app/Services/MarketIntelligence/AnalyseModeOrchestrator.php).

Body services (all in `app/Services/MarketIntelligence/`):
- [StrategicBriefService.php](app/Services/MarketIntelligence/StrategicBriefService.php) — "Ellie weekly brief" (see §7.2)
- [OpportunityPocketService.php](app/Services/MarketIntelligence/OpportunityPocketService.php)
- [DemandSupplyMatrixService.php](app/Services/MarketIntelligence/DemandSupplyMatrixService.php)
- [MarketVelocityService.php](app/Services/MarketIntelligence/MarketVelocityService.php)
- [CompetitiveLandscapeService.php](app/Services/MarketIntelligence/CompetitiveLandscapeService.php)

## 5.4 `/corex/tracked-properties` — the convergence list

- Route: `GET /corex/tracked-properties` → `corex.tracked-properties.index` ([routes/web.php:2495](routes/web.php#L2495))
- Middleware: `auth` + `permission:access_prospecting`
- Controller: [TrackedPropertyController::index()](app/Http/Controllers/CoreX/TrackedPropertyController.php#L24-L100)
- View: `corex.tracked-properties.index`
- Detail: `GET /corex/tracked-properties/{tp}` → [show()](app/Http/Controllers/CoreX/TrackedPropertyController.php#L102-L118) → view `corex.tracked-properties.show`
- Promote: `POST /corex/tracked-properties/{tp}/promote` → [promote()](app/Http/Controllers/CoreX/TrackedPropertyController.php#L120-L148)

What it shows:
- Filtered list by suburb / status / source / search (street, suburb, erf, deed, external_id)
- Stats: total / unpromoted / promoted
- `sourceCounts` by source_type (the screenshot tags)
- `suburbCounts` top 30
- Detail page aggregates linked prospecting listings + external refs + promoted Property + source chain.

## 5.5 Other MIC-adjacent screens

- `/corex/portal-leads` — `corex.portal-leads.index` ([routes/web.php:1848](routes/web.php#L1848)) — Andre's in-flight build (migration unrun locally).
- `/admin/p24-suburbs` — P24 suburb CRUD ([routes/web.php:703-709](routes/web.php#L703-L709)).
- `/command-center/settings/market-intelligence` — Command Centre "Market Intelligence" settings tab ([routes/web.php:975-987](routes/web.php#L975-L987)). Per the inline closure, this manages "records" (unclear without reading the view).
- `/settings/prospecting/*` — MIC config (towns, suburbs, property types, bedroom segments, price bands, suggested actions thresholds) ([routes/web.php:1509-1512+](routes/web.php#L1509-L1512)).
- `/prospecting/*` — **legacy mount still active** ([routes/web.php:2551-2580](routes/web.php#L2551-L2580)); URL `GET /prospecting` 301-redirects to `/corex/market-intelligence` ([:2599-2602](routes/web.php#L2599-L2602)) but every other prospecting sub-path is still served by the legacy `ProspectingController`. This means **two controllers serve the same business**, gated by URL.
- `/evaluation/index#tab=prospecting` — older "Property Evaluation" page with a Prospecting tab ([resources/views/layouts/corex-sidebar.blade.php:1321](resources/views/layouts/corex-sidebar.blade.php#L1321)).
- Internal/dev: `/portal-captures/ingest` (no UI, extension-only endpoint).

## 5.6 Sidebar navigation entries pointing to MIC screens

All in [resources/views/layouts/corex-sidebar.blade.php](resources/views/layouts/corex-sidebar.blade.php):

| Label | Target route | File:line |
|---|---|---|
| Market intelligence | `route('market-intelligence.index')` | [corex-sidebar.blade.php:377-378](resources/views/layouts/corex-sidebar.blade.php#L377-L378) |
| Tracked Properties | `route('corex.tracked-properties.index')` | [:385-386](resources/views/layouts/corex-sidebar.blade.php#L385-L386) |
| Portal Leads | `route('corex.portal-leads.index')` (guarded by `Route::has`) | [:409-410](resources/views/layouts/corex-sidebar.blade.php#L409-L410) |
| Market Intelligence (cmd-center settings) | `route('command-center.settings.market-intelligence')` | [:1116](resources/views/layouts/corex-sidebar.blade.php#L1116) |
| Prospecting (evaluation tab — legacy) | `route('evaluation.index')#tab=prospecting` | [:1321](resources/views/layouts/corex-sidebar.blade.php#L1321) |

Active-state line: matches both `market-intelligence.*` and `prospecting.*` ([:377](resources/views/layouts/corex-sidebar.blade.php#L377)) so legacy URLs highlight correctly during the migration window.

Permission gate wrapper: `@permission('access_prospecting')` ([:355](resources/views/layouts/corex-sidebar.blade.php#L355)).

There is **no** sidebar link to `/admin/p24` — that screen is reachable only via direct URL or the role's permission menu.

---

═══════════════════════════════════════════════════════════════
SECTION 6: SUBURB AGGREGATE DATA
═══════════════════════════════════════════════════════════════

## 6.1 Source of truth — computed on-the-fly

**Computed on every page render.** Not stored. No materialised aggregates.

## 6.2 If stored: N/A

There is no `suburb_aggregates` / `suburb_stats` table — DB scan confirms tables do not exist.

## 6.3 If computed: query location + cache

| Surface | Query location | Cache |
|---|---|---|
| `/admin/p24` suburb stats (Margate 463 active, R 1.25M avg, …) | [P24Controller::index() lines 52-64](app/Http/Controllers/Admin/P24Controller.php#L52-L64) — `P24Listing::active() group by suburb` | none |
| MIC Work mode filter rail (suburb counts) | [MarketIntelligenceController::computeFilterRailAggregates() :837-877](app/Http/Controllers/CoreX/MarketIntelligenceController.php#L837-L877) | none |
| MIC stats strip | [MarketIntelligenceController::computeSnapshotKpis() :684-736](app/Http/Controllers/CoreX/MarketIntelligenceController.php#L684-L736) | none |
| MIC sidebar count badge | inline `Cache::remember("mi.sidebar_count.{$agencyId}", 60, …)` ([:476-484](app/Http/Controllers/CoreX/MarketIntelligenceController.php#L476-L484)) | **60s** |
| MIC demand pockets | [computeDemandPockets() :888-923](app/Http/Controllers/CoreX/MarketIntelligenceController.php#L888-L923) | **1h** (`mi.demand_pockets.{$agencyId}`) |
| Analyse Strategic Brief | [StrategicBriefService::buildFor() :31-36](app/Services/MarketIntelligence/StrategicBriefService.php#L31-L36) | **6h** (`mi.brief.{$agencyId}`) |
| P24MarketDataService (per-suburb stats) | [P24MarketDataService::getSuburbStats() :13-86](app/Services/P24/P24MarketDataService.php#L13-L86) | none |
| TrackedPropertyController suburb counts | [TrackedPropertyController::index() :86-95](app/Http/Controllers/CoreX/TrackedPropertyController.php#L86-L95) | none |

## 6.4 What suburbs are seeded

- `p24_suburbs` (a separate lookup of P24's location taxonomy): **19 rows**, seeded by `database/seeders/P24SuburbSeeder.php` from `config/p24_suburbs.php`. Region: `kzn-south-coast`.
- `town_suburbs` (MIC's own town↔suburb mapping for prospecting): **27 rows**.
- `prospecting_listings` has **11 distinct non-null suburbs** populated by real ingestion.

## 6.5 Per-suburb fields available

From `/admin/p24` (computed from `p24_listings`):
- listing_count
- avg_price, min_price, max_price
- new_this_month (count first_seen_date in current month)

From `P24MarketDataService::getSuburbStats()`:
- total_listings, avg_price, median_price, min_price, max_price
- new_listings_per_month (6-month rolling)
- avg_days_on_market (only if `days_on_market` column populated)
- price_trend (`up`/`stable`/`down` from last 3 vs prev 3 months)
- common_property_types (groupBy)

From MIC Work mode:
- by_suburb count, by_type count, by_beds count
- demand pockets (suburb × bedrooms, with strong-buyer count vs listing count)

---

═══════════════════════════════════════════════════════════════
SECTION 7: ELLIE / AI INTEGRATION HOOKS
═══════════════════════════════════════════════════════════════

## 7.1 Existing AI calls in MIC code

**None.** Grep of `app/Services/MarketIntelligence`, `app/Services/Prospecting`, `app/Http/Controllers/CoreX/MarketIntelligenceController.php`, `app/Http/Controllers/EllieController.php` returns **zero** OpenAI / Claude / Anthropic / gpt-* references for MIC purposes.

The only AI-aware controller in the codebase is `app/Http/Controllers/EllieController.php` (used by the Ellie chat surface), and it isn't called from any MIC code path.

## 7.2 The "Weekly Market Brief" Ellie narrative on the Analyse screen

**NOT AI-generated.** Currently **templated PHP text** built from real query results.

[StrategicBriefService.php:18-21](app/Services/MarketIntelligence/StrategicBriefService.php#L18-L21):
> "F.6 ships as templated text. When EllieService lands, the hook in compose() can pass the facts to it for natural-language re-rendering."

The narrative is composed from four canned sentence templates ([StrategicBriefService.php:38-143](app/Services/MarketIntelligence/StrategicBriefService.php#L38-L143)):
1. Top opportunity pocket (suburb × beds, demand × supply ratio).
2. 30-day inflow leader.
3. Top competitor share in the leading suburb.
4. Stale-mandate count.
5. Fallback when there isn't enough data.

Plus 2-3 action buttons that link back to Work mode with filters preset.

**Cache:** 6 hours per agency (`mi.brief.{$agencyId}`) — [:33](app/Services/MarketIntelligence/StrategicBriefService.php#L33).

## 7.3 Embedding / vector pipeline touchpoints

**None for MIC.** No `pgvector`/`weaviate`/`pinecone` integration. Ellie has its own KB pipeline via the Python service at `/opt/hf-ai/app.py` (per CLAUDE.md / SYSTEM.md), but no MIC code touches that surface.

## 7.4 Cost-bearing AI calls today

**Zero in MIC.** The Strategic Brief is the only "AI-shaped" feature on the Analyse page and it does not actually call an AI today. No tokens spent.

---

═══════════════════════════════════════════════════════════════
SECTION 8: CMA REPORT INGESTION (CURRENT STATE)
═══════════════════════════════════════════════════════════════

## 8.1 CMA Info PDF parser

**Exists, but only via presentations.**

| Component | File:line |
|---|---|
| Upload route | `POST /presentations/{p}/upload` → [PresentationController.php:601-626](app/Http/Controllers/Presentation/PresentationController.php#L601-L626) |
| Text extraction | [TextExtractionService::extractText()](app/Domain/Presentation/TextExtractionService.php#L20-L46) (pdftotext → Smalot) |
| Field extraction | [DocumentExtractor.php:67-403](app/Support/Presentation/DocumentExtractor.php#L67-L403) — regex-based (feature-flagged) |
| CMA parser | `app/Services/Presentations/Evidence/Parsers/CmaParserV1.php` |
| Sales (Vicinity) parser | `app/Services/Presentations/Evidence/Parsers/SalesReportParserV1.php` |
| Suburb stock parser | `app/Services/Presentations/Evidence/Parsers/SuburbStockParserV1.php` |
| Back-prop to graph | [PropertyCmaPropagationService](app/Services/Presentation/PropertyCmaPropagationService.php) — calls `TrackedPropertyMatchOrCreateService` with source.type='cmainfo' |

## 8.2 `market_reports` / `market_data_points` tables

**None.** DB scan confirms no tables matching these names. CMA data lives in:

| Table | Rows on local |
|---|---|
| `presentation_uploads` | 60 |
| `presentation_fields` | 277 |
| `presentation_sold_comps` | 212 |
| `presentation_active_listings` | 528 |

…and the extracted subject-property facts (erf, GPS, municipal valuation, CMA bands) are propagated into the `tracked_properties` graph via `PropertyCmaPropagationService` (only for the subject property — not for the per-comp rows).

## 8.3 Upload UI for reports

**Only within a presentation context.** The form is part of the Presentation detail page; there is no "Documents → Upload CMA report" surface. From a Property pillar page, you cannot directly attach a CMA PDF.

## 8.4 Source of the 12 CMAINFO rows in Tracked Properties

12 rows in `tracked_property_external_refs WHERE source_type = 'cmainfo'`. They land there via the only writer that emits that source_type:
- `PropertyCmaPropagationService` extracts `subject.erf` / `subject.gps` / `municipal.total_value` from a presentation's `presentation_fields`, builds a facts array, and calls `TrackedPropertyMatchOrCreateService::matchOrCreate(agencyId, facts, ['type' => 'cmainfo', 'ref' => <reference>])`.
- Trigger point: a presentation save / save-and-extract flow.

Concretely: every time someone uploaded a CMA Info PDF to a presentation in the agency, **if the subject-property OCR ran cleanly**, one TP was created or enriched and an `external_ref` row with `source_type='cmainfo'` was written. The 12 rows represent successful subject-property extractions from ~60 uploaded presentations (the 5:1 ratio reflects how often the field-extractor's regex finds the subject's GPS/erf cleanly).

---

═══════════════════════════════════════════════════════════════
SECTION 9: PERMISSIONS & MULTI-TENANCY
═══════════════════════════════════════════════════════════════

## 9.1 Which roles see which MIC screens

From [config/corex-permissions.php](config/corex-permissions.php):

| Permission | Key | File:line |
|---|---|---|
| Access Prospecting (gates MIC + Tracked Properties) | `access_prospecting` | [config/corex-permissions.php:302](config/corex-permissions.php#L302) |
| Manage Prospecting Setup (toggle in-stock, BM-only features) | `prospecting_setup.manage` | [:352](config/corex-permissions.php#L352) |
| Manage P24 Market Intel (gates /admin/p24) | `manage_p24` | [:297](config/corex-permissions.php#L297) |
| P24 view | `p24.view` | [:298](config/corex-permissions.php#L298) |
| P24 manage | `p24.manage` | [:299](config/corex-permissions.php#L299) |
| Access Portal Leads | `access_portal_leads` | [:276](config/corex-permissions.php#L276) |
| Portal Leads view | `portal_leads.view` | [:277](config/corex-permissions.php#L277) |
| P24 syndication config | `agency.p24.configure` | [:356](config/corex-permissions.php#L356) |
| P24 location sync | `agency.p24.sync` | [:357](config/corex-permissions.php#L357) |

**Role assignments** ([config/corex-permissions.php:522-643](config/corex-permissions.php#L522-L643)):
- All three of `super_admin` / `admin` / `manager` get `access_prospecting`, `access_portal_leads`, `portal_leads.view`, `p24.view`.
- Only the explicit settings-section role gets `prospecting_setup.manage` (the BM/admin canvass-toggle gate).
- `manage_p24` is required for `/admin/p24` (not granted by default to non-admins — agents typically cannot see this screen).

## 9.2 Agency scoping — which MIC tables carry `agency_id`

| Table | agency_id? | Notes |
|---|---|---|
| `tracked_properties` | ✓ | BelongsToAgency, FK cascade |
| `tracked_property_external_refs` | ✓ | BelongsToAgency |
| `prospecting_listings` | ✓ | with unique (agency_id, portal_source, portal_ref) |
| `prospecting_buyer_matches` | ✓ | added 2026-05-13 |
| `prospecting_claims` | ✓ | |
| `prospecting_pitch_locks` | ✓ | |
| `prospecting_searches` | ✓ | |
| `prospecting_price_history` | ✗ | not directly — inherits via prospecting_listing |
| `property_buyer_matches` | ✓ | |
| `contact_matches` | ✓ | |
| `properties` | ✓ | |
| `portal_captures` | ✓ | per [.ai/specs/market-intelligence-discovery.md §5](.ai/specs/market-intelligence-discovery.md) |
| `portal_listings` | ✓ | same |
| `portal_leads` | ✓ | per migration |
| `p24_listings` | **✗** | legacy table predates multi-tenancy — global |
| `p24_import_log` | ✗ | global |
| `p24_suburbs` | ✗ | global lookup (region='kzn-south-coast') |
| `p24_alert_*` tables | ✗ | global (legacy) |
| `presentations` | **✗** (branch_id only) | gap flagged in 2026-05-14 discovery audit §1.4 |
| `presentation_*` child tables | inherit via presentation_id | same gap |

**Risk hotspots:** `p24_listings`, `p24_import_log`, and `presentations` are agency-blind. Once HFC onboards another agency, the P24 import would mix data and presentations would need a branch-of-agency-X filter that doesn't exist yet.

## 9.3 Branch scoping

- `prospecting_claims` and `prospecting_pitch_locks` are user-scoped — the claim implicitly belongs to a branch via the user's `branch_id`.
- `presentations` carries `branch_id` directly.
- Most MIC reads are agency-scoped, not branch-scoped. A user with `prospecting_setup.manage` can see all of an agency's claims, listings, and TPs regardless of their branch.

---

═══════════════════════════════════════════════════════════════
SECTION 10: KNOWN BUGS / GAPS / TODOs
═══════════════════════════════════════════════════════════════

## 10.1 Grep for TODO/FIXME/HACK/BUG/XXX/@todo

Across `app/Services/Prospecting`, `app/Services/P24`, `app/Services/MarketIntelligence`, `app/Services/Ellie`, the MIC controllers, and CmaParserV1:

**The grep returns only ONE hit in the MIC surface area** — [app/Services/P24/P24EmailParserService.php:26](app/Services/P24/P24EmailParserService.php#L26) — and it's a code comment ("Extract listing numbers from URLs: listingNumber=P24-XXXXXXXXX"), not a TODO.

That doesn't mean there are no defects — it means the codebase is unusually disciplined about not leaving TODO markers. Real gaps are documented inline in dockblocks (see §10.2) and in the discovery / build audits.

## 10.2 Inline-documented gaps (the "soft TODOs")

| Gap | File:line |
|---|---|
| Flag-to-BM endpoint (spec §12) not yet wired | scoped in build-f-spec but not in `MarketIntelligenceController` |
| Suggested-action thresholds settings tab (spec §13) | route registered ([:1509+](routes/web.php#L1509)) but no `_suggested-actions.blade.php` partial confirmed |
| StrategicBriefService templated, not AI | [StrategicBriefService.php:18-21](app/Services/MarketIntelligence/StrategicBriefService.php#L18-L21) |
| `OpportunityPocketService` replaces `computeDemandPockets()` | inline note [:879-887](app/Http/Controllers/CoreX/MarketIntelligenceController.php#L879-L887) — both currently coexist |
| PortalLeads migration not run on local | `portal_leads` table absent from `nexus_os` |
| No scheduled P24 import — manual trigger only | [P24Controller::runImport()](app/Http/Controllers/Admin/P24Controller.php#L179-L185) is the only entry |
| Legacy `ProspectingController` still mounted in parallel with `MarketIntelligenceController` | [routes/web.php:2551-2580](routes/web.php#L2551-L2580) |
| Per-CMA-comp erf/GPS not parsed | discovery audit §1.5 |
| CMA data is locked inside presentations | discovery audit §13.2 gap 2 |
| `presentations` table lacks `agency_id` | discovery audit §1.4 |
| `properties` lacks `erf_number` / `title_deed_number` / `municipal_valuation` columns | discovery audit §6.3 |
| `prospecting_listings.address = 'Address not available'` for many P24 rows | discovery audit §13.3 risk 3 |
| `tracked_properties` indexes are not spatial (just btree on lat/long) | migration §2.1 |
| No `tracked_property_buyer_matches` table | §3.8 |
| 4/4 active claims past 48h with no feedback, no scheduled job to flag | §4.5–4.6 |
| 93% of tracked properties (4,585) have no address — suburb only | §2.7 |

## 10.3 Schema mismatches between what UI shows and what data exists

- **`/admin/p24` shows "463 active listings in Margate"** — but `p24_listings` has no `agency_id`, so any agency viewing this dashboard sees the same (mixed) numbers. Single-tenant safe today; cross-tenant unsafe.
- **MIC sidebar count badge** caches for 60s and reflects "canvass-pool, not in stock". Aligned with the screen's default filter. ✓
- **Demand-supply matrix / Strategic Brief** count strong-tier buyers using `prospecting_buyer_matches` score ≥ 80 — but the **buyer score** is computed elsewhere (`PropertyMatchScoringService`) on a different cadence. If `isRegenerating()` is true the matrix becomes momentarily inconsistent — the Work mode has a banner; the Analyse mode does not.
- **Tracked Properties list** lets agents search by `external_id`, `title_deed_number`, `erf_number` — but `title_deed_number` and `erf_number` are mostly null (4,585 of 4,912 rows have no street, almost certainly no erf either). Search returns nothing for most agents.

---

═══════════════════════════════════════════════════════════════
SECTION 11: WHAT THE AGENT SEES TODAY — END-TO-END WALKTHROUGH
═══════════════════════════════════════════════════════════════

Agent (role: `agent`) — has `access_prospecting`, `access_portal_leads`, `portal_leads.view`, `p24.view`. Does **not** have `manage_p24` or `prospecting_setup.manage`.

### 11.1 `/corex/market-intelligence` (Work mode, the default landing)

- **Loads:** stats strip Row 1 (active, buyer_matched, in_stock=0 ideally, new_today, cross_listed), Row 2 action presets (pitch_now_high, pitch_now, log_outcomes [owner-scoped], my_claims, expiring), filter rail (suburbs / types / beds + demand pockets), 50-row paginated listing list.
- **Actions:**
  - Claim a listing (one-click bookmark icon).
  - Open detail slide-over (click row body) — sees Overview, Buyers (with phone / WhatsApp icons), Activity, Market, Source tabs (some partials may be stubs).
  - Capture address (placeholder per prospecting-intelligence-spec P6).
  - Add a note to a claim they own.
  - Release their own claim.
  - Pitch (initiates the temp lock → seller-outreach composer flow).
- **What's broken / placeholder:**
  - "Show in-stock too" toggle hidden (no `prospecting_setup.manage`).
  - "Flag to BM" chip click — endpoint scaffold exists but the controller method is not implemented; the chip may surface an action that no-ops.
  - Sidebar count badge displays the canvass-pool count but may be slightly stale (60s cache).

### 11.2 `/corex/market-intelligence?mode=analyse`

- **Loads:** same stats strip; body shows Strategic Brief, Demand-Supply Matrix, Opportunity Pockets, Market Velocity, Competitive Landscape, Buyer Funnel.
- **Strategic Brief:** templated, real data, no AI. Includes action buttons that link back to Work mode pre-filtered.
- **Heat matrix:** suburb × beds. Click a cell → Work mode pre-filtered.
- **Competitive landscape:** stacked bars per agency for the selected suburb.
- **What's broken / placeholder:**
  - The "AI brief" framing is misleading — it's not AI today.
  - Buyer Funnel relies on `Contact.buyer_state` + `created_at`; without enough buyers in a window, segments render as zero (no banner).
  - No "regenerating" banner in Analyse mode (Work mode has one).

### 11.3 `/corex/tracked-properties`

- **Loads:** filter + paginated list of 4,912 TPs. 4,585 of them show "(no address)" in the table because `street_name` is null.
- **Actions:**
  - Search by street/suburb/erf/title-deed/external-id.
  - Filter by suburb / status / source.
  - Click a TP → detail page.
  - Promote-to-Stock (only on the detail page — see 11.4).
- **What's broken / placeholder:**
  - List dominated by address-less rows (chrome_capture origin).
  - No "edit address" / "merge" / "split" actions.
  - Title-deed/erf search is mostly useless because the columns are mostly empty.

### 11.4 `/corex/tracked-properties/{id}`

- **Loads:** TP details, source chain audit (json), linked prospecting listings, linked Property if promoted, external refs grouped by source_type.
- **Actions:**
  - Promote-to-Stock (single button, creates a draft Property).
- **What's broken / placeholder:**
  - Source-chain JSON is rendered raw (no parsed timeline).
  - No edit, no merge.

### 11.5 `/corex/portal-leads`

- **Loads (when migration has run):** filter bar (portal / from / to / agent / status), paginated leads table with row expansion for raw payload.
- **On local right now:** controller calls `PortalLead::query()`. Without the migration, this will throw a SQL error at first hit.
- **What's broken / placeholder:**
  - Migration unrun on local — see §1.7.
  - Andre's spec calls for a toast notification + per-property panel; the controller has a `poll` endpoint but no toast Alpine component was confirmed in the layout (`portal-lead-toast.blade.php` may not exist yet).

### 11.6 `/admin/p24`

- **Not accessible** to a vanilla agent (requires `manage_p24`).
- For BMs/admins who have it: shows the suburb-stats dashboard (the screenshot the user referenced — "Margate 463 active, R 1.25M avg, …").
- Manual "Run Import" button works.

### 11.7 `/prospecting`

- 301-redirects to `/corex/market-intelligence` ([routes/web.php:2599-2602](routes/web.php#L2599-L2602)).
- Sub-paths (e.g. `/prospecting/{listing}`) are still served by the legacy `ProspectingController` during the F.1 migration window — so any old links shared internally still resolve.

### 11.8 What the agent CANNOT do today

- Manually add a tracked property (no UI).
- Upload a CMA Info report without going through a presentation.
- Edit a TP's address or merge duplicates.
- See an agency-wide "claims-without-feedback" list (the action preset is owner-scoped).
- Get a real AI-generated brief.

---

═══════════════════════════════════════════════════════════════
SECTION 12: HEADLINE FINDINGS
═══════════════════════════════════════════════════════════════

## What's solid and ready to build on

1. **The Tracked Property graph is real and central** — 4,912 nodes, 5,526 source refs, 5-strategy match-or-create, append-only source_chain, `TrackedPropertyMatchOrCreateService` as the single front door (CLAUDE.md HARD RULE #10). Every redesign decision can lean on this as the spine.
2. **Chrome capture is doing the heavy lifting** — 92% of TPs come from it (4,530/4,912). The pipeline (extension → ingest → portal_captures → portal_listings → TP graph) is the proven ingress.
3. **Buyer-side data is dense** — 21,259 prospecting_buyer_matches across 32 active wishlists. The scoring tiers (strong ≥ 80, mid 50-79) are consistently consumed by the Suggested-Action chip, action presets, and demand pockets.
4. **MIC route + view structure (Build F.1-F.7) is shipped** — `MarketIntelligenceController` is the canonical surface, sidebar relabelled, legacy redirects in place. The redesign already lives at the right URL.
5. **The Analyse mode plumbing exists** — six services in `app/Services/MarketIntelligence/` are in place. They render real numbers.

## What's half-built and needs finishing

1. **Tracked Properties UI is a list + read-only detail.** The graph is great; the UI to manipulate it (edit address, merge, split, force a source) doesn't exist.
2. **The Strategic Brief markets itself as AI but isn't.** The hook for `EllieService` is reserved ([StrategicBriefService.php:18-21](app/Services/MarketIntelligence/StrategicBriefService.php#L18-L21)). Either wire it or restyle the copy.
3. **Portal Leads (Andre's branch)** — migration written but unrun locally; controller / model exist; toast + per-property panel may be partial. Demo-blocking if not finished before Monday 26 May.
4. **P24 email ingestion is dormant** — manual import, last ran 5 weeks ago. No cron in `routes/console.php` for `ImportP24AlertsJob`.
5. **Flag-to-BM endpoint specced (build-f-spec §12) but not implemented** — the chip UI will look enabled and do nothing.
6. **Stale-claim sweeping is on-read, not on-time** — 4/4 active claims have been past 48h with no scheduled job to nudge BM.
7. **Property pillar is missing identifier columns** — no `erf_number`, no `title_deed_number`, no `municipal_valuation`. The TP graph has them; the Property pillar doesn't. Two-place truth.

## What's missing entirely

1. **CMA upload UI outside a presentation.** No "Documents → CMA Reports → Upload" surface.
2. **Per-comp parsing (CMA Vicinity rows)** — sold comps exist in `presentation_sold_comps` but per-row erf / GPS / address aren't pulled out. The historical comp data is locked in raw_row_json.
3. **TVA / Lightstone API integration** — entirely manual today (RMCP describes them by name only).
4. **Real Ellie integration in MIC** — zero AI calls, zero embeddings, zero cost-bearing models in any MIC code path.
5. **Forward / reverse geocoder** — neither exists. Addresses without GPS can't be matched by proximity.
6. **`tracked_property_buyer_matches` table or join** — buyer matches are not connected to TPs directly; only to prospecting_listings.
7. **Materialised suburb stats / cache layer.** Every page recomputes from scratch (with two exceptions: 60s sidebar count, 1h demand pockets, 6h brief). Won't scale to multiple agencies.

## What's confusing / redundant and could be removed

1. **Two controllers serve the same business** — `ProspectingController` (legacy) + `MarketIntelligenceController` (current) both mounted, [routes/web.php:2512-2580](routes/web.php#L2512-L2580). Mirror routes exist for the F.1 migration window. Retiring the legacy is overdue.
2. **Two address-normalisation systems** — `ProspectingListing::normalizeAddress()` (cross-portal dedup via `property_group_id`) and the TP graph's `TrackedProperty::normaliseSuburb()` + `normaliseStreetName()`. They're solving similar problems with different rules.
3. **`/admin/p24` is a parallel mini-MIC** — its suburb stats, its listings table, its price changes are functionally a second Market Intelligence dashboard built on `p24_listings` (which isn't even agency-scoped). It will confuse the demo by showing different numbers from `/corex/market-intelligence`.
4. **`/evaluation` sidebar link still has a "Prospecting" tab** — adds a third place the word lives.
5. **`/command-center/settings/market-intelligence`** is yet another "Market Intelligence" surface — a closure-defined route managing a "records" table. Distinct from prospecting setup, distinct from Build F MI. Naming collision.

## Top 5 risks to the Monday 26 May target

1. **`portal_leads` migration unrun on local.** Any merge of Andre's branch that introduces a route hit before `php artisan migrate` runs will surface a `Base table or view not found` exception. **Mitigation:** run `php artisan migrate` immediately after the next pull; confirm controller's `index` route resolves on local before deploying.
2. **The legacy `ProspectingController` is still mounted.** Build F.1's "rebuilt from scratch" reality has the old controller serving sub-paths. Demo traffic that takes a deep link or follows an internal route name (`route('prospecting.show', …)`) hits OLD code with the OLD view, which has not been retested. **Mitigation:** retire the legacy group before demo, OR test every link path from MIC's slide-over and detail navigations to confirm none routes to legacy.
3. **The "AI brief" looks AI but isn't.** Showing this to a customer expecting AI will undermine the demo's core pitch ("the system tells you where the money is"). **Mitigation:** either wire the actual Ellie / OpenAI call (1-day spike if endpoints exist) OR rebrand the section to "Weekly Brief" without the AI framing.
4. **93% of Tracked Properties are address-less.** The screenshot the user wants to demo (`/corex/tracked-properties`) will be dominated by "(no address)" rows. **Mitigation:** default the list to `street_name IS NOT NULL` and expose an "Address pending" toggle.
5. **Stale claims show up unsolved.** With 4/4 active claims past 48h and no scheduled job to flag the BM, any demo navigation to the claim status surfaces an obvious gap. **Mitigation:** either ship the flag-to-BM endpoint or hide the "expiring" preset from the demo agent's view.

---

═══════════════════════════════════════════════════════════════
APPENDIX A: NOTES
═══════════════════════════════════════════════════════════════

**A.1 — The "demo / corex_demo" reference in this audit.**
The MIC redesign target is the demo at `demo1.corexos.co.za` (per prior session's banner addition). All counts in this audit are from `nexus_os` (local). The demo DB (`nexus_os_demo`) is reseeded clean from `DemoDataSeeder` and will mirror the same SHAPE of data but with different volumes. The 4,912 / 5,526 / 21,259 figures are the local development reality; the demo currently seeds far fewer (e.g. ~131 tracked_properties per recent demo:seed log).

**A.2 — The branching state at audit time.**
`HFC2402` is in sync with `origin/HFC2402`. The previous sync audit identified 27 commits on `origin/Staging` (all Andre's "fix" commits) not yet in `HFC2402`. Andre's Portal Leads migration (`2026_05_20_000001_create_portal_leads_table.php`) IS in `HFC2402` (file exists). The local `nexus_os` simply hasn't had `php artisan migrate` run since the file landed. So this is a **local-state** gap, not a code gap.

**A.3 — There is no "P24 Alerts" table.**
The user's brief references "1.1 P24 Email Alerts" as if there might be a `p24_alerts` table. Confirmed not present. P24 alerts are stored in `p24_listings` + `p24_import_log`. The "alert" framing exists in older migrations (`2026_02_25_500001_create_p24_alert_tables.php`) but the live system uses `p24_listings`.

**A.4 — Two domain-events worth knowing.**
[TrackedPropertyCreated](app/Events/Prospecting/TrackedPropertyCreated.php), [TrackedPropertyEnriched](app/Events/Prospecting/TrackedPropertyEnriched.php), [TrackedPropertyPromotedToStock](app/Events/Prospecting/TrackedPropertyPromotedToStock.php) are fired by `TrackedPropertyMatchOrCreateService` but **no listeners** are currently registered for any of them in the codebase. The events catalogue (CLAUDE.md HARD RULE #9 / `.ai/specs/corex-domain-events-spec.md`) is in place but the cross-pillar reactivity hasn't been wired. Anyone planning Build H / I on this should know the events exist and fire — just nothing listens yet.

**A.5 — `MarketIntelligenceController` is ~1,300 lines.**
Most of the file is filter / preset / aggregate logic. The Build F redesign delivered the structure but not the simplification — every former `ProspectingController` query is still there, plus the new analytical computations. Build G/H will likely want to break this into per-section services (one per panel).

**A.6 — One scheduled job seems wired for MIC: `prospecting:recompute-matches`.**
Referenced from [DemoDataSeeder.php:1156](database/seeders/DemoDataSeeder.php#L1156). Not visible in the routes-console.php cadence (would need a `routes/console.php` read to confirm). The buyer-match population paths run in Tinker / seeders today; for production they'd want a cron.

---

**End of audit.**
