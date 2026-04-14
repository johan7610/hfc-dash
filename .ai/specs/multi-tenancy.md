# Multi-Tenancy (Agency Isolation) — Spec

> Status: Active (implemented 2026-04-14 after cross-agency leak found on staging).
> Owner: Andre / Johan
> Severity: Security-critical. Treat regressions as P0.

---

## Why this exists

CoreX OS is a **multi-agency** platform. Every operational row (properties,
contacts, deals, presentations, documents, agents, branches) belongs to
exactly one agency, and no user of Agency A may ever read or write data
owned by Agency B.

On 2026-04-14 staging was shown to be leaking properties and agents
across agencies because:

1. Several pillar tables (`contacts`, `deals`, `presentations`,
   `documents`) had no `agency_id` column at all.
2. Controllers relied on ad-hoc `where('agency_id', …)` checks, which
   were missing in `PropertyController@index` and anywhere newly added.
3. `AgencySwitcherController` stored `active_agency_id` in session with
   no authorisation check, so any user with the permission key could
   impersonate any agency.

The fix is structural, not a patch: isolation is enforced at the model
layer via a global scope, not at the controller layer.

---

## Pillar tables that MUST carry `agency_id`

| Table | Backfill source |
|-------|-----------------|
| users | (already present) |
| branches | (already present) |
| properties | (already present) |
| contacts | `users.agency_id` via `created_by_user_id` |
| deals | `branches.agency_id` via `branch_id` |
| presentations | `branches.agency_id` via `branch_id` |
| documents | `users.agency_id` via `uploaded_by` |

Any new table that stores tenant-owned data MUST include `agency_id`
from migration day one. No exceptions.

---

## How isolation is enforced

### 1. `App\Models\Concerns\BelongsToAgency` trait

Every agency-owned Eloquent model uses this trait. It:

- registers `AgencyScope` as a global scope,
- auto-fills `agency_id` on `creating` from `Auth::user()->effectiveAgencyId()` when blank,
- exposes an `agency()` BelongsTo,
- exposes `queryWithoutAgencyScope()` as the **only** sanctioned escape hatch
  (use for console commands, queues and system imports; never in request code).

### 2. `App\Models\Scopes\AgencyScope`

Applies `WHERE agency_id = :effectiveAgency OR agency_id IS NULL` to every
query on models that use the trait. Notes:

- Skipped when there is no authenticated user (console, migrations, login queries).
- Skipped for owner-role accounts that have NOT activated the agency switcher
  (so platform owners see everything until they deliberately scope in).
- Respects the session override `active_agency_id` via `User::effectiveAgencyId()`.
- Recursion-guarded per model class so the `User` scope is safe during auth
  resolution.

### 3. `AgencySwitcherController`

Validates the target agency before writing `active_agency_id` into session:

- owner-role users may switch into any agency,
- non-owner users may only switch into agencies they personally belong to
  (matched via `users.agency_id` or `users.branch_id → branches.agency_id`),
- otherwise responds with 403.

---

## Rules for every new feature

1. **New model holds tenant data?** Add `agency_id` to the migration, use
   `BelongsToAgency` on the model, index the column. Do it in the first
   commit, not later.
2. **Never call `->where('agency_id', …)` manually in new code.** The global
   scope does it. Manual filters are redundant and drift from the real
   source of truth.
3. **Never use `Model::withoutGlobalScope(AgencyScope::class)` in request
   code.** If you think you need it, you are probably designing a leak.
   Console/queue only.
4. **Route-model binding** (e.g. `Property $property`) is already safe —
   the binder runs the query through the scope, so mismatched tenants 404.
   Rely on that; do not re-check.
5. **Owner-role "see all" is intentional.** If a feature needs to aggregate
   across agencies (platform-wide KPIs, superadmin tooling), gate the UI
   on `$user->isOwnerRole()` and use `queryWithoutAgencyScope()` explicitly
   on the query you need unscoped.
6. **Agency switcher** must remain the single entry point for owners who
   want to act inside one agency. Don't invent a second mechanism.

---

## Verification checklist (before declaring any feature done)

Run through this list on every module that reads or writes a pillar table:

- [ ] Log in as a user of Agency A. Can you see any Agency B records
      anywhere in the UI (lists, search, autocomplete, API endpoints,
      exports)?
- [ ] Can you hit an Agency B record by URL (`/properties/{id}` where
      `{id}` belongs to B)? Expected: 404.
- [ ] Does creating a record from the UI assign the correct `agency_id`
      automatically?
- [ ] Does the agency switcher 403 when an Agency A user posts a switch
      request for Agency B?
- [ ] Does `php artisan tinker` with a non-owner user show only that
      user's agency rows on every pillar model?

If any item fails, the feature is not done.

---

## Known limitations / follow-up

- A handful of secondary tables (`prospecting_listings`, `fica_submissions`,
  `commission_ledgers`, `training_courses`, `agent_applications`,
  `web_packs`, `docuperfect_field_groups`, etc.) already had `agency_id`
  and manual filtering before this fix. They are candidates to also adopt
  `BelongsToAgency` for defense in depth. Track in `ROADMAP.md`.
- Cross-agency reporting for owners (platform KPIs) currently requires
  `queryWithoutAgencyScope()`. Any new reporting query that needs this
  must be code-reviewed specifically for leak safety.
