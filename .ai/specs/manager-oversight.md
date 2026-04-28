# Spec: Manager Oversight (Dashboard)

**Status:** Drafted 2026-04-27 — awaiting approval
**Author:** Andre (drafted via Claude)
**Module:** Dashboard / Tasks extension
**Route:** `/dashboard/oversight`

---

## What This Feature Does and Why

Branch managers, admins, and any other role granted permission can monitor the **outstanding items of other agents** in a single pane — surfacing things agents have ignored, missed, or are about to miss. Without this, manager-level accountability is invisible: agents ignore notifications, mandates expire, deals stall, and nobody catches it until the damage is done.

The feature also lets a manager **nudge** the responsible agent (email) to drive action without taking the work over silently.

---

## Pillar Connections

| Pillar | Read | Write back |
|--------|------|------------|
| **Agent (User)** | Identify agents in scope (branch / agency), read their assigned items | Log nudge events on user activity feed |
| **Property** | Read stale/unattended listings | (none — read-only oversight) |
| **Deal** | Read deals near expiry / stalled | Log oversight nudges against the deal |
| **Contact** | Read stale leads tied to agents | (read-only) |

Notifications, tasks, mandates, FFCs are read but not pillar models themselves — they belong to agents/deals/properties.

---

## Signals Tracked (Outstanding Categories)

Each row on the oversight page represents one outstanding item. Categories:

1. **Ignored Notifications** — notification fired at agent, no read/dismiss/action within threshold
2. **Deals Near Expiry** — deal `expires_at` within threshold, no recent activity (note, status change, doc) by assigned agent
3. **Expiring Mandates** — mandate expiry within threshold, no extension or action
4. **Stale Listings** — property assigned to agent, no activity (note, viewing, status change) within threshold
5. **Overdue Tasks** — task `due_at` past, status not complete
6. **Expiring FFCs** — agent's FFC expires within threshold, no renewal flag
7. **Stale Leads** — contact (lead status) assigned to agent, no contact activity within threshold

Each category has its own configurable threshold (see User Settings below).

---

## Permissions

Add to `CoreXPermissionSeeder.php`:

| Key | Description |
|-----|-------------|
| `dashboard.oversight.view` | Can see the Manager Oversight page and oversight data for users in scope |
| `dashboard.oversight.manage` | Can nudge / reassign items from oversight page (implies view) |

**Scope option** (per-role, set in Role Manager UI alongside the permission toggle):
- `oversight_scope`: `branch` (default — only users in same branch) or `agency` (all users in agency)

Stored as a column on `roles` table: `oversight_scope ENUM('branch','agency') NULL`.

Multi-tenancy: the `AgencyScope` global scope already isolates by agency. Branch scoping is an additional `where('branch_id', auth()->user()->branch_id)` applied in the OversightService when role's `oversight_scope = branch`.

---

## Branch Concept

If `branches` table does not yet exist, this spec **requires** creating it (minimal):
- `branches` table: `id`, `agency_id`, `name`, `address`, `manager_user_id`, timestamps, soft deletes
- `users` table: add `branch_id` nullable FK
- Branch CRUD lives in Agency Settings (separate small spec / add to multi-tenancy spec)

If a user has no `branch_id`, branch-scoped oversight returns no results for that manager — they must be assigned a branch.

---

## User Settings — Oversight Notification Preferences

Anyone with `dashboard.oversight.view` gets a new section in **User Settings → Oversight Settings** where they configure when *they* are notified about each category, per agent in their scope.

Stored in new table `user_oversight_preferences`:

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | |
| user_id | bigint FK | the manager |
| agency_id | bigint FK | tenancy |
| category | enum | one of the 7 signal categories |
| enabled | bool | receive notifications for this category |
| threshold_hours | int nullable | when to consider it "outstanding" (e.g. 24 for ignored notifications, 168 for deals near expiry) |
| notify_channel | enum | `email`, `in_app`, `both` |
| created_at / updated_at | | |

Unique on (`user_id`, `category`).

Defaults seeded on first visit:

| Category | Default threshold | Channel |
|----------|------------------|---------|
| Ignored notifications | 24h | in_app |
| Deals near expiry | 7 days | both |
| Expiring mandates | 14 days | both |
| Stale listings | 14 days | in_app |
| Overdue tasks | 0 (immediate once overdue) | in_app |
| Expiring FFCs | 30 days | both |
| Stale leads | 7 days | in_app |

A scheduled job (`OversightDigestJob`, hourly) evaluates each manager's preferences and dispatches notifications when new outstanding items match.

---

## Data Model / Migrations

1. `2026_04_27_000001_create_branches_table` (if not present)
2. `2026_04_27_000002_add_branch_id_to_users_table`
3. `2026_04_27_000003_add_oversight_scope_to_roles_table`
4. `2026_04_27_000004_create_user_oversight_preferences_table`
5. `2026_04_27_000005_create_oversight_nudges_table`
   - `id`, `agency_id`, `from_user_id` (manager), `to_user_id` (agent), `subject_type`, `subject_id` (polymorphic — Deal/Property/Notification/Task/Contact), `category`, `message` text nullable, `sent_at`, timestamps

All new tables: `agency_id` + `BelongsToAgency` trait per non-negotiable #7.

---

## UI Placement and Navigation

- **Sidebar entry** under Dashboard group:
  - Label: "Oversight"
  - Icon: `eye`
  - Visible only when user has `dashboard.oversight.view`
- **Page route:** `GET /dashboard/oversight` → `OversightController@index`
- **Settings entry:** User Settings page gets new tab "Oversight" (visible only with `dashboard.oversight.view`)
- **Role Manager:** existing role edit page gets new section "Oversight" with:
  - permission checkboxes (`view`, `manage`)
  - `oversight_scope` radio (Branch only / Entire agency)

---

## User Flow

### Manager viewing oversight
1. Manager logs in, clicks Dashboard → Oversight in sidebar
2. Page loads with filter bar: Category (all / specific), Agent (all in scope / specific), Branch (if scope = agency)
3. Default view: all outstanding items grouped by agent, sorted by severity (oldest first)
4. Each row shows: agent name, category icon, item summary (e.g. "Deal #1234 — 2 days from expiry, no activity for 5 days"), age, action buttons
5. Action buttons (when `dashboard.oversight.manage`):
   - **Nudge** — opens modal, prefills email subject/body, manager edits and sends → `OversightNudgeMail` to agent, logs to `oversight_nudges`
   - **Reassign** — opens modal to pick a new agent (only same-pillar reassignment; e.g. deal → another agent), writes to subject record + nudge log
   - **View** — deep link to the actual record (deal page, listing page, etc.)

### Manager configuring preferences
1. User Settings → Oversight tab
2. List of 7 categories, each with: enabled toggle, threshold input, notification channel select
3. Save → upserts to `user_oversight_preferences`

### Role admin granting access
1. Settings → Role Manager → edit role
2. New "Oversight" section: tick `view` and/or `manage`
3. Choose `Oversight scope`: Branch only / Entire agency
4. Save → updates role permissions and `oversight_scope` column

### Nudge flow
1. Manager clicks Nudge on a row
2. Modal pre-fills: "Hi {agent}, a {category} item needs your attention: {summary}. Please action by {suggested}."
3. Manager edits, clicks Send
4. Email dispatched to agent (`OversightNudgeMail`)
5. In-app notification also created for agent
6. Nudge logged in `oversight_nudges` — agent sees badge "Nudged by {manager} on {date}" on the record

---

## Acceptance Criteria

- [ ] `dashboard.oversight.view` and `dashboard.oversight.manage` exist in `CoreXPermissionSeeder.php`
- [ ] Sidebar shows "Oversight" entry under Dashboard, gated on `view` permission
- [ ] `/dashboard/oversight` renders with all 7 categories populated for users in scope
- [ ] Branch-scoped manager sees only their branch; agency-scoped sees full agency
- [ ] Cross-agency leakage is impossible (AgencyScope verified by test)
- [ ] User Settings → Oversight tab persists 7-category preferences correctly
- [ ] Role Manager edit page surfaces oversight permissions + scope radio
- [ ] Nudge button sends email + creates in-app notification + logs to `oversight_nudges`
- [ ] Reassign button updates the subject record's assigned agent and logs the change
- [ ] Hourly `OversightDigestJob` dispatches notifications per preferences
- [ ] All new pages have nav entries (non-negotiable #2)
- [ ] All deletes are soft (non-negotiable #1) — nudges and preferences use SoftDeletes
- [ ] `php -l`, view/route/cache clears, and `scripts/dev-check.ps1` all pass with 0 new failures
- [ ] Manual functional verification: log in as manager → view page → nudge an agent → confirm agent receives email + in-app notification

---

## Files to Create / Modify

### Create
- `app/Http/Controllers/CoreX/Dashboard/OversightController.php`
- `app/Services/Oversight/OversightService.php` — aggregates 7 signal categories
- `app/Services/Oversight/Signals/IgnoredNotificationsSignal.php`
- `app/Services/Oversight/Signals/DealsNearExpirySignal.php`
- `app/Services/Oversight/Signals/ExpiringMandatesSignal.php`
- `app/Services/Oversight/Signals/StaleListingsSignal.php`
- `app/Services/Oversight/Signals/OverdueTasksSignal.php`
- `app/Services/Oversight/Signals/ExpiringFfcsSignal.php`
- `app/Services/Oversight/Signals/StaleLeadsSignal.php`
- `app/Models/Branch.php` (if not present)
- `app/Models/UserOversightPreference.php`
- `app/Models/OversightNudge.php`
- `app/Mail/OversightNudgeMail.php`
- `app/Jobs/OversightDigestJob.php`
- `resources/views/corex/dashboard/oversight/index.blade.php`
- `resources/views/corex/dashboard/oversight/partials/row.blade.php`
- `resources/views/corex/dashboard/oversight/partials/nudge-modal.blade.php`
- `resources/views/corex/settings/user/oversight.blade.php`
- 5 migrations listed above
- `tests/Feature/Dashboard/OversightTest.php` — scope, permission, nudge, multi-tenancy

### Modify
- `database/seeders/CoreXPermissionSeeder.php` — add 2 permission keys
- `resources/views/layouts/corex-sidebar.blade.php` — add Oversight entry
- `resources/views/corex/settings/roles/edit.blade.php` — add Oversight section + scope radio
- `app/Http/Controllers/CoreX/Settings/RoleController.php` — persist `oversight_scope`
- `app/Http/Controllers/CoreX/Settings/UserSettingsController.php` — handle Oversight tab
- `routes/web.php` — register `/dashboard/oversight` + settings route
- `app/Console/Kernel.php` — schedule `OversightDigestJob` hourly
- `.ai/ROADMAP.md` — mark Manager Oversight as in-progress
- `.ai/CODEBASE_MAP.md` — add Oversight service paths

---

## Open Questions / Risks

- **Branches table** — does `branches` already exist in some form? If yes, this spec adapts; if not, branch CRUD UI is a small follow-up spec (this spec assumes the table only).
- **Notification model** — assumes Laravel notifications table has `read_at`. If we use a custom notifications model, the IgnoredNotificationsSignal adapts.
- **Reassign permissions** — reassigning a deal currently may have its own permission. Oversight reassign should respect those (manager must have both `dashboard.oversight.manage` AND the underlying reassign permission for that pillar).

---

*Approval required from Johan before any code is written.*
