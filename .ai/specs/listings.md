# Spec: Listings

**Status:** Live (basic) — spec to be written during consolidation sprint

---

## What Exists

- Listing creation with property details, pricing, bedroom/bathroom configs
- Multi-agent assignment per listing
- P24 email parser (suburb extraction fixed)
- Listing import from P24 email (`matchAllUsersFromAgentCell()`, `$listing->agents()->sync()`)
- Listing status management
- P24 market data integration via `P24MarketDataService` (used in Presentations)

---

## Consolidation Items (Phase 1)

- [ ] All listing status values from settings table (not hardcoded)
- [ ] All property type values from settings table
- [ ] Listing links bidirectionally to Property pillar
- [ ] Navigation: all listing actions reachable from sidebar

---

## Pending Spec Items

The following require full spec before build:

- P24 image scraping into listing record
- Listing photo display in Presentations module
- Clickable P24 refs on price change log
- Listing-to-Flow integration (listing creation triggers mandate flow, etc.)

---

*Full spec to be completed during Phase 1 consolidation sprint.*
