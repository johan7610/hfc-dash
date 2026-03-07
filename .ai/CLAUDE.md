# HF Coastal Nexus — Claude Instructions

## MANDATORY: Read before every task

Before doing anything, read and follow these files in order:

1. [.ai/CLAUDE_EXECUTION.md](.ai/CLAUDE_EXECUTION.md) — execution rules, output format, done criteria
2. [.ai/COMMAND_GATE.md](.ai/COMMAND_GATE.md) — allowed/blocked commands
3. [.ai/DIAG_CHECKLIST_UI.md](.ai/DIAG_CHECKLIST_UI.md) — UI diagnosis checklist (use when page shows 0/blank)

## MANDATORY: Before declaring any task done

Run `scripts/dev-check.ps1` (or VS Code task: **Dev Check**).

## Output format

Every task response must follow the format defined in `.ai/CLAUDE_EXECUTION.md`:

```
PLAN:
FILES TO TOUCH:
CHANGES MADE:
COMMANDS RUN (with results):
DIFF SUMMARY:
RISKS / NOTES:
DONE CRITERIA CHECK:
```

## Key rules (from CLAUDE_EXECUTION.md)

- Minimal changes only. No refactors unless explicitly requested.
- No regex patching. Edit files normally.
- LOCAL dev only — never touch production.
- After any change: `php -l` on PHP files, `php artisan view:clear` on Blade, `php artisan route:clear` on routes/controllers.
- Nexus sidebar = `resources/views/layouts/sidebar.blade.php`
- Agency Tracker sidebar = `resources/views/layouts/nexus-sidebar.blade.php` — DO NOT modify unless explicitly told.

---

# PROJECT KNOWLEDGE — Presentation System

> **Last updated:** 2026-02-23
> **Owner:** Johan Reichel, Home Finders Coastal (Shelly Beach, KZN)
> **Stack:** Laravel (PHP) + Blade templates, running on Windows (localhost:8000)
> **This section is the single source of truth for project architecture. When spec conflicts with codebase, codebase wins.**

---

## 1. PROJECT OVERVIEW

**Nexus OS** is an internal agency management platform for Home Finders Coastal, a real estate agency on the KZN South Coast. The **Presentation System** is a module that produces data-driven seller presentations — statistical packs that agents download as PDF and email/print for listing appointments.

### Business Context
- ~20 agents currently, expanding significantly in 2026
- Agents compete for seller mandates against franchise agencies (Pam Golding, Seeff, Rawson, etc.)
- The presentation system is the competitive differentiator — most agencies show generic CMAs
- Presentations must be professional, data-backed, and visually impressive
- All currency is ZAR (South African Rand), formatted as "R 1,300,000"
- Geography: KZN South Coast suburbs (Uvongo, Margate, Shelly Beach, Ramsgate, etc.)
- Data source for PDFs: **CMA Info** — a South African property data provider

### Core Philosophy
- **No fake numbers.** No hallucinated values. No guessed statistics.
- **No silent failures.** If parsing fails, surface it. Never catch-and-ignore.
- **Extraction first, compute second, display last.** Data pipeline must be verified before UI work.
- **Deterministic math only.** All statistics must be reproducible from stored extracted data.
- **No UI-based calculations.** All computation is service-driven (PHP backend).

---

## 2. SYSTEM ARCHITECTURE

### Tech Stack
- **Framework:** Laravel (PHP 8.x) with Blade templates
- **Database:** MySQL/MariaDB
- **PDF text extraction:** `pdftotext` CLI (Poppler) -> `smalot/pdfparser` (PHP fallback)
- **OCR:** Tesseract (available but NOT in presentation pipeline currently)
- **Headless browser:** Playwright on port 3105 (for URL snapshots only)
- **AI chat (Ellie):** Separate module on port 3100, NOT connected to presentations
- **AI fallback extraction:** `AIExtractionService` calls Anthropic API (claude-haiku-4-5-20251001) ONLY when deterministic parsers return 0 rows
- **Chrome Extension:** Manifest V3, captures P24 pages -> POSTs to `/portal-captures/ingest`

### Key Directories
```
app/Http/Controllers/Presentation/     -> 5 controllers
app/Services/Presentations/            -> 31+ services
app/Services/Presentations/Evidence/Parsers/  -> CmaParserV1, SalesReportParserV1, SuburbStockParserV1, etc.
app/Services/Presentations/Evidence/Extractors/ -> Property24SearchExtractorV1
app/Domain/Presentation/               -> TextExtractionService, PresentationService, UploadProcessor
app/Support/Presentation/              -> DocumentExtractor, LinkImportedFieldPresenter
app/Models/                            -> 12 Eloquent models for presentations + 3 portal models
chrome-extension/portal-capture/       -> Chrome extension source
```

### Controllers (5)
| Controller | Key Methods |
|---|---|
| PresentationController | index, create, store, show, analysis, compute, compile, simulate, brain, upload, storeLink, destroyLink, reExtractUpload, reExtractLink |
| PresentationSnapshotController | saveSnapshot, showSnapshot |
| PresentationVersionController | index (admin/BM), mine (agent) |
| PresentationPdfController | download |
| PortalCaptureController | ingest, liveSnapshot, index, attach |

---

## 3. DATABASE SCHEMA (ACTUAL — 13 presentation tables + 3 portal tables)

> **IMPORTANT:** The original master spec references `presentation_documents` and `presentation_metrics` tables. These DO NOT EXIST. The system uses `presentation_uploads` for documents and `presentation_snapshots` + analytics services for computed metrics.

### Core Tables
| Table | Purpose | Key Columns |
|---|---|---|
| presentations | Main record | branch_id, created_by_user_id, listing_id, title, status (draft/finalized), currency |
| presentation_sections | Section definitions | presentation_id, section_key, data_json, sort_order |
| presentation_snapshots | Frozen analysis results | presentation_id, snapshot_json, computed_json |
| presentation_uploads | PDF documents | presentation_id, type, storage_path, text_extracted, extraction_json, extraction_status |
| presentation_fields | Extracted/override values | presentation_id, field_key, extracted_value, override_value, final_value, confidence |
| presentation_links | Portal URLs | presentation_id, type (property24/lightstone/other), url, notes |
| presentation_sold_comps | Sold comparables | presentation_id, sold_date, sold_price_inc, suburb, beds, baths, size_m2 |
| presentation_active_listings | Active listings | presentation_id, list_price_inc, suburb, beds, baths, status |
| presentation_versions | Compiled packs | presentation_id, blueprint_version, data_snapshot_json, compiled_at |
| presentation_url_snapshots | Stored HTML snapshots | presentation_id, url, snapshot_html, source_type, http_status |
| presentation_articles | Market articles | presentation_id, url, snapshot_text, ai_summary_text, tags_json |
| presentation_listing_price_history | Price tracking | presentation_id, active_listing_id, price_inc, captured_at |
| presentation_document_library_items | Library attachments | presentation_id, document_library_item_id, attached_by_user_id |

### Portal Tables
| Table | Purpose |
|---|---|
| portal_captures | Raw captures from chrome extension |
| portal_listings | Deduplicated listings (unique on source_site + portal_listing_id) |
| portal_listing_observations | Price change tracking |

---

## 4. DATA SOURCES & EXTRACTION

### 4.1 CMA Info PDFs (3 types — standardised format, uploaded manually by agents)

All PDFs from CMA Info share: branded header (agency name, contact), consistent table formatting, page-numbered footer with date.

#### Suburb Report (Median Sales Analysis) — typically 4 pages
- Page 1: Year-by-year residential sales table (Year, No of Sales, Median Price, Annual Change %, Index) for suburb AND municipality + chart
- Page 2: Annual Residential Index charts (suburb vs municipality, Full Title vs Sectional Title)
- Page 3: Residential Price Ranges table (Year, No of Sales, Low Range, Median, High Range, Maximum) + chart
- Page 4: Price Distribution histogram (percentage by price band)

#### Vicinity Sales Report — typically 3 pages
- Page 1: Individual sales table within radius (Dist, Erf No, Address, Usage, Type, Extent m2, Sale Date, Sale Price, R/m2) + summary (Lower/Middle/Upper Range, Average, Avg R/m2)
- Page 2: Map showing numbered sale locations
- Page 3: Price Distribution histogram

#### CMA Valuation Report — typically 9 pages
- Page 1: Property info (Erf, GPS, Township, Extent, Municipal Valuation, Accommodation)
- Page 2: CMA area map
- Page 3: Indexed Value (Purchase Date, Purchase Price, Indexed Value, CAGR)
- Page 4: CMA table (11 comps: Dist, Erf, Address, Extent, Sale Date, Sale Price) + Lower/Middle/Upper Range
- Page 5: Comparative Municipal Valuation
- Page 6: Comparative Accommodation
- Page 7: 15 most recent street sales (with R/m2)
- Page 8: Price Distribution histogram (vicinity)
- Page 9: Currently listed properties nearby (Days on Market)

### 4.2 Property24 Data (via Chrome Extension)

**Current flow:** Agent browses P24 -> clicks extension -> captures HTML/screenshot/fields -> POSTs to /portal-captures/ingest -> server extracts via Property24SearchExtractorV1 (deterministic DOM/XPath) -> upserts portal_listings -> tracks price changes

**P24 Listing ID:** Format `P24-116950342`. This is the canonical dedup key across ALL P24 data sources (extension + email alerts). Same ID appears in chrome extension captures, P24 alert emails (subject line + links), and portal listing URLs.

**Target UX improvement:** Extension remembers "active presentation" -> agent browses P24 -> clicks extension -> auto-links to active presentation. No tab-switching.

### 4.3 Holding Cost Inputs (manual entry)
Fields: Bond payment, Rates, Levies, Insurance, Utilities, Opportunity cost. Monthly ZAR.

### 4.4 Property24 Alert Emails (FUTURE — post-launch, start collecting early)

P24 sends structured HTML alert emails for new listings and price reductions. Already flowing into sales@hfcoastal.co.za in large volume.

- Subject pattern: `House for sale in [Suburb] P24-[ListingID]`
- Contains: P24 listing ID, Price, Beds, Baths, Property type
- Alert types: New listings, Price reductions, Sold
- P24 listing ID enables full lifecycle tracking: listed -> reduced -> sold
- Enables: new stock flow rate, dynamic absorption trends, avg days on market, avg discount %

---

## 5. WHAT WORKS vs WHAT DOESN'T (as of 2026-02-23)

### Working
- Presentation CRUD, Pack Readiness checklist with progress bar
- PDF upload + text extraction (pdftotext CLI -> smalot/pdfparser fallback)
- PDF parser services for all 3 CMA Info types
- Extracted data displays on presentation page
- Chrome extension captures P24 pages, P24 search extractor works
- Portal listing dedup and price change detection
- Property links, Holding cost inputs, Document Library
- Feature flags (15+), Live polling for portal capture updates

### Not Working / Not Built
- **Run Analysis** — button exists, pipeline doesn't execute end-to-end
- **Compile Pack** — button exists, does not produce output
- **Brain Simulation** — UI scaffolded, no computation behind it
- **Seller-facing PDF download** — PresentationPdfService exists but pipeline not connected
- **Snapshot system** — tables exist, 0 snapshots saved

### Needs Fixing
- P24 listing count mismatch (minor, +/-1)
- Chrome extension UX (too many tab switches)
- Verify extraction_json populated correctly for all 3 PDF types
- Verify presentation_fields populated from extracted data

---

## 6. BUILD PLAN — 10-DAY SPRINT (Target: 2026-03-05)

### Phase 1: Pipeline Fix (Days 1-3)
Goal: Run Analysis produces real statistics from real data.
1. Audit extraction_json for each PDF type
2. Build/fix statistics computation -> store in presentation_snapshots.computed_json
3. Wire Run Analysis button -> compute -> store snapshot -> display results

### Phase 2: Compile Pack — Seller PDF (Days 4-7)
Goal: Agent clicks Compile Pack -> downloads professional seller-facing PDF.
Sections: Cover, Property Overview, Market Snapshot, Comparable Sales, CMA Analysis, Competitive Landscape, Holding Cost Analysis, Recommended Price Band, Document Library Attachments.

### Phase 3: Polish & Launch (Days 8-10)
Test with real data, fix edge cases, chrome extension UX, performance check, brief agents.

### Post-Launch
- P24 Alert Email Ingestion (HIGH PRIORITY — start collecting)
- Brain Simulation
- Market articles, AI narrative overlay
- Price sensitivity curves, Absorption rate modeling

---

## 7. PDF PARSER SPECS

- ZAR format: "R 1 300 000" -> strip spaces -> integer. Regex: `/R\s*([\d\s]+\d)/`
- Dates: YYYY/MM/DD
- Erf extents: "1 523 m2" -> integer m2
- Percentages: "8.89%" or "-10.00%" -> float
- Missing data: CMA Info uses "-" -> store as null
- Low sample years: still extract, flag low count

---

## 8. CHROME EXTENSION

Files: `chrome-extension/portal-capture/` (manifest.json, content.js, popup.js, popup.html)
Endpoint: `POST /portal-captures/ingest` (auth via Sanctum/CSRF)

---

## 9. ELLIE — SEPARATE MODULE

Port 3100, ai_conversations + ai_messages tables, EllieController. **Not connected to Presentations.**

---

## 10. SERVICE MAP

### Parsers
CmaParserV1, SalesReportParserV1, SuburbStockParserV1, Property24SearchParserV1, Property24ListingParserV1, PrivatePropertySearchParserV1, PrivatePropertyListingParserV1, UnknownParser

### Analytics
PriceBandService, TrajectorySimulationService, RecommendationService, LaunchPositioningService, HoldingCostService, ExplainabilityService, PPIService

### Compilation
PresentationBlueprintService, PresentationCompilerService, PresentationReadinessService, PresentationNarrativeService, PresentationPdfService, PresentationDataQualityService

---

## 11. BUSINESS CONTEXT

### The Core Problem
Sellers price on emotion. Buyers buy on data. Other agents tell sellers what they want to hear to win mandates. Properties sit overpriced for months/years. Holding costs bleed the seller dry.

### South Coast Challenges
Absentee sellers (holiday/investment properties), scattered across SA/internationally. Must convince via email/PDF, not face-to-face. South Coast is cheaper than Cape Town/Joburg — out-of-town sellers don't understand this.

### The Presentation's Core Argument
Property worth R1.5M (CMA) + 50 active competitors (P24) + 10 sales/year (suburb data) = 5 years of stock. New listings at 4/month = stock is GROWING. Every month overpriced = R30K+ in holding costs. The answer is always price.

### Narrative Arc
1. Here's your property (credibility)
2. Here's the market (trends)
3. Here's your competition (absorption rate)
4. Here's what data says (CMA valuation)
5. Here's what waiting costs (holding costs)
6. Here's our strategy (recommended price)

### Design Standard
Not a data dump — a persuasion document. Professional consultancy report feel. Must work printed A4, on screen, and on mobile. Data-forward, no fluff.

---

## 12. TEST CASE — 21 Dee Road, Uvongo

- Erf 789, 1,523 m2, 4 bed house
- Asking: R2,500,000 | Purchased: R2,800,000 (2025/04)
- CMA: R1,412,000 – R1,529,000 – R1,744,000
- Suburb median: R1,300,000 (60 sales in 2025)
- Vicinity avg: R1,687,000, R/m2: R1,232 (15 comps within 300m)
- Municipal: R1,715,000 (2023) | Indexed: R2,880,000 (CAGR 2.84%)
- Monthly holding: R30,751

---

*End of CLAUDE.md — Living document. Update when architecture changes.*
