# Spec: Presentations

**Status:** Live (partial) — photo display and P24 data integration pending

---

## What Presentations Does

Generates professional seller/landlord listing presentations. The agent uses this before listing appointment to show the potential seller/landlord:
- What their property is worth (CMA)
- What the current market looks like (stock absorption, listing flow)
- What CoreX / Home Finders Coastal will do for them
- Why they should sign a mandate

Presentations render via Puppeteer — real Chrome, pixel-perfect output.

---

## What's Live

- Puppeteer-rendered presentation document
- Basic market data integration via `P24MarketDataService`
- Agent branding and agency branding

---

## Consolidation Items (Phase 1)

- [ ] Listing photos displayed inside presentation (from P24 or own photos)
- [ ] Clickable P24 refs on price change section

---

## Pending Spec Items (Phase 2)

### P24 Image Scraping
Pull listing photos from P24 into the CoreX listing record so they can be used in presentations and elsewhere. Full spec required.

### TVA API: CMA System
Use TVA property data to build a comparable market analysis inside the presentation:
- Recent sales in the area
- Active competing listings
- Absorption rate (how fast stock is selling)
- Recommended listing price range

### TVA API: Data Flywheel
Cache model for TVA data:
- Property lookup (address → TVA record)
- Owner contact retrieval (TVA owner data → pre-fill Contact record)
- Cached responses to avoid repeated API calls
- Cache invalidation strategy

Full spec required before build.

---

*Full spec for photo display and TVA CMA to be completed in Phase 2.*
