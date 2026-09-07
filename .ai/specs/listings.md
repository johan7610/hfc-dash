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

## Co-listing visibility (secondary agent) — Live

A property may carry a **secondary (co-listing) agent** alongside the primary.

- **Storage:** `properties.pp_second_agent_id` (nullable FK → `users`). Originally
  added for P24/PrivateProperty dual-agent syndication, now also the spine of
  co-listing visibility. Set via the "Second Agent" card on the property page.
- **Relationship:** `Property::secondAgent()`.
- **Pillars:** Property ↔ Agent (a listing links to two practitioners).

**Behaviour:**
- The secondary agent sees the co-listed property in their **own "My Listings"**
  — the index scope matches `agent_id = me OR pp_second_agent_id = me`.
- **Both agents are shown on the property card** (and the table's agent column):
  the primary agent on top, the secondary agent **underneath** — just the name and
  the dark-blue avatar, no "Primary"/"2nd" labels.
- **Counts once:** a co-listed property is a single `properties` row, so it
  counts **once** in the Total / On Market KPIs even when both agents are in the
  selected filter set — verified by `test_co_listed_property_counts_once_in_the_kpi_totals`.
- The secondary agent has **full view + edit** access to the listing (same as the
  primary — co-agents are equals on the listing).
- **Admin/BM agent picker:** filtering by an agent surfaces listings where that
  agent is **primary OR secondary**. The scope is `where/orWhere` on the single
  `properties` row (no JOIN), so a co-listed property renders **exactly once**
  even when both the primary and secondary are in the selected set.

**Acceptance:** secondary agent's "My Listings" shows the co-listing badged
"Secondary"; admin filter by either agent surfaces it without duplication.
Covered by `tests/Feature/Properties/SecondaryAgentVisibilityTest.php`.

**Files:** `app/Http/Controllers/CoreX/PropertyController.php` (index scope +
`viewer_is_secondary` flag), `resources/views/corex/properties/index.blade.php`
(badges).

---

## Location field consistency (suburb/city/town/province) — Live (2026-09-07, QA1)

Investigation of property #21014 (Johan, live-testing) found that `properties.town`
could silently disagree with `suburb`/`city`/`province` after a location edit — the
Property24 location picker corrected suburb/city/province and all three P24 ids, but
`town` was never part of that write and kept whatever value it held from promotion
time. The property Intelligence tab's market snapshot preferred `town` over `city`,
so a fully-corrected property could still display its old, wrong area.

**Storage:** `properties.city` is the field kept in sync on every save that resolves
a P24 suburb (`AppliesP24Location::applyP24Location()`), used by the full property
edit form, the quick-setup wizard, and the mobile API alike. `properties.town` means
the same thing — "the real town an agent/buyer recognises" for a P24-linked property
(see `DeedsCaptureController::promote()`'s own town-resolution comment) — and is now
written from the **same** resolved P24 city row as `city`, every time, so the two can
never drift again.

**Precedence when they still disagree** (legacy rows saved before this fix, or a
property whose suburb was never linked to a P24 record): **`city` wins, `town` is the
fallback only when city is blank** — `$property->city ?: $property->town`. `city` is
the field this trait actively maintains; `town` is best-effort. This matches
`Property::slug()`'s own existing `$this->city ?? $this->town` precedence, so the
Intelligence tab is now consistent with that, not introducing a third convention.

**Other screens that read `town`/`city` together, found during this investigation but
NOT touched (outside this defect's direct path — reported per CLAUDE.md non-negotiable
#2, "report don't fix outside scope"):**
- `app/Models/Property.php:1974` — `slug()`, already city-first (`$this->city ?? $this->town`)
- `app/Services/WebTemplateDataService.php:113,882,887,889,1477` — public web template data (suburb/town/district fallback chains), town-first
- `app/Services/Compliance/MarketingReadinessService.php:536` — town-first
- `app/Services/Map/MapPinService.php:411` — town-first
- `app/Services/Contact/ContactAddressPropertyGuard.php:247,256` — city-first
- `app/Services/PrivateProperty/PrivatePropertyListingMapper.php:72` — town-first
- `app/Http/Controllers/Presentation/PresentationGeneratorController.php:83` — city-first
- `resources/views/corex/properties/partials/syndication-panel.blade.php:555` — town-first

These will stop mattering for any property edited going forward (town and city are
now always equal after a P24 suburb pick), but for existing/legacy rows they read
whichever precedence they already had before this fix — unchanged.

**Repair for existing data:** `php artisan properties:repair-town` — dry-run by
default, `--apply` to persist. Only touches properties with a resolved
`p24_city_id` whose `town` disagrees with that city's name (a `town` that was never
populated is left alone — that's a different, much larger, unrequested change; see
command docblock). Idempotent.

**Acceptance:** covered by
`tests/Feature/Properties/PropertyTownFollowsLocationEditTest.php` —
correcting a property's location via the edit screen updates `town` along with
`suburb`/`city`/`province`/the three P24 ids; the Intelligence tab shows the
corrected area even on a property still carrying a stale `town` from before this fix.

**Files:** `app/Http/Concerns/AppliesP24Location.php`,
`resources/views/corex/properties/intelligence/_market-snapshot.blade.php`,
`app/Console/Commands/RepairPropertyTownFromP24City.php`.

---

## Pending Spec Items

The following require full spec before build:

- P24 image scraping into listing record
- Listing photo display in Presentations module
- Clickable P24 refs on price change log
- Listing-to-Flow integration (listing creation triggers mandate flow, etc.)

---

*Full spec to be completed during Phase 1 consolidation sprint.*
