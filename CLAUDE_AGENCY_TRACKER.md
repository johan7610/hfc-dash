# HF Coastal Agency Tracker — Claude Instructions

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
- Agency Tracker sidebar = `resources/views/layouts/nexus-sidebar.blade.php`
- Presentation sidebar = `resources/views/layouts/sidebar.blade.php` — DO NOT modify unless explicitly told.

---

# PROJECT KNOWLEDGE — Agency Tracker (Performance Platform)

> **Last updated:** 2026-02-23
> **Owner:** Johan Reichel, Home Finders Coastal (Shelly Beach, KZN)
> **Stack:** Laravel (PHP) + Blade templates, running on Windows (localhost:8000)
> **Production:** performance.hfcoastal.co.za
> **Codebase:** Same Laravel app as Nexus Presentation System — shared codebase, separate modules
> **This section is the single source of truth for project architecture. When spec conflicts with codebase, codebase wins.**

---

## 1. PROJECT OVERVIEW

The **Agency Tracker** is a multi-branch real estate performance and commission management platform for Home Finders Coastal. It manages the full financial lifecycle of property deals — from capture through commission splits, agent payouts, and company retained earnings.

### Business Context
- ~20 active users (agents, branch managers, admin)
- ~60+ deals in production database
- Live and in use at performance.hfcoastal.co.za
- All currency is ZAR (South African Rand), formatted as "R 1,300,000"
- Commission is always calculated **ex VAT** — VAT belongs to SARS, not the agency
- Deals (property values) are captured **inc VAT**
- Standard company commission rate: 7.5%
- Geography: KZN South Coast branches

### Core Philosophy
- **No UI-level money calculations.** All financial logic must live in the service layer.
- **No duplicate formulas.** If a calculation exists in settlement, it must NOT be re-implemented in worksheet, dashboard, or any other view.
- **Stored computed values.** Dashboards read pre-computed values, never recalculate on render.
- **Deterministic math.** Same inputs → same outputs. No time-dependent logic, no randomization.
- **No silent failures.** Surface errors, never catch-and-ignore.

### ⚠ CRITICAL: THE PATCHWORK PROBLEM

This system was built incrementally via ChatGPT over several months. The primary architectural debt is:

**The same financial formulas exist in multiple places.** Settlement calculates commission splits one way. Worksheet may calculate them differently. Dashboards may use a third method. When a formula is wrong, it must be found and fixed in EVERY location — and if one is missed, figures mismatch silently.

**Rules for Claude to prevent making this worse:**
1. **NEVER add a new financial calculation to a Blade template or controller.** All money math goes in service classes.
2. **Before changing ANY formula**, search the entire codebase for that calculation pattern. List every file it appears in.
3. **If the same calculation exists in multiple places**, flag it as a consolidation candidate — don't just fix one instance.
4. **When fixing a bug**, verify the fix produces the same result on ALL screens that display that value (settlement, worksheet, agent dashboard, branch dashboard, company dashboard).

---

## 2. SYSTEM ARCHITECTURE

### Tech Stack
- **Framework:** Laravel (PHP 8.x) with Blade templates
- **Database:** MySQL/MariaDB
- **Shared codebase** with Nexus Presentation System
- **Production:** VPS at performance.hfcoastal.co.za

### Route Architecture
All routes are grouped by role prefix with middleware enforcement:
```
/admin/*     → role:admin middleware
/bm/*        → role:bm middleware
/agent/*     → role:agent middleware
/tv/*        → public/token-protected display
```

### ⚠ TWO PERMISSION SYSTEMS COEXIST

**System 1 (Original GPT-built):** Role-based URL routing — hard `/agent/`, `/bm/`, `/admin/` prefix separation with `role:` middleware. Simple but rigid.

**System 2 (Andre-built via Nexus integration):** Role & Permission manager — granular permissions with checkboxes attached to roles (likely Spatie `laravel-permission` or similar). Located in Nexus admin area.

**Risk:** These two systems can conflict. A user might have permission granted in the role manager but get blocked by route middleware, or vice versa.

**Rule for Claude:** Do NOT add new permission checks without first understanding which system governs that route. Do not add new middleware without checking the role manager. Flag any conflicts found.

---

## 3. USER ROLES

| Role | Access | Can Do | Cannot Do |
|---|---|---|---|
| Admin | Full system, all branches | CRUD deals, manage users/branches, configure finance definitions, trigger audits, recalculate periods | — |
| Branch Manager (BM) | Own branch only | View branch KPIs, agent performance, deal details | Edit finance definitions, audit, see other branches |
| Agent | Own data only | View own deals, own dashboard, add remarks | Change deal status, edit splits after save, see system-wide numbers |

---

## 4. CORE MODULES

### 4.1 Deal Register

One row per deal. Central transactional record.

**Key stored fields:**
- `property_value` — inc VAT (the sale price)
- `total_commission` — inc VAT as captured
- `vat_inclusive_flag` — whether commission includes VAT
- `status` — Pending → Granted → Registered (pipeline stages)
- `agency_paid_flag` — separate boolean, manually toggled when bank payment confirmed
- `external_listing_flag` / `external_selling_flag` — whether that side is an external agency
- `our_share_listing_percent` / `our_share_selling_percent` — if external, what % is ours
- Listing agent(s) and selling agent(s) — multi-agent support via `deal_agents` pivot

**Status logic:**
| Status | Meaning | Financial Impact |
|---|---|---|
| Pending | Pipeline only | Not counted in earned figures |
| Granted | Commission secured, not received | Included in projections |
| Registered | Commission received | Included in actuals |

### 4.2 Settlement (attached to each deal)

Calculates commission splits and produces payment documentation. Serves two purposes:

1. **Agent payslip** — printable document showing agent's earnings from this deal
2. **Company payment slip** — what gets paid via bank

**Commission split flow (example):**
```
Property sale: R 1,000,000
Total commission: R 115,000 (inc VAT)
Commission ex VAT: R 100,000 (this is what gets split — VAT is for SARS)

Listing/Selling split: 50/50
  → Listing side: R 50,000
  → Selling side: R 50,000

Listing agent split with company: 60/40
  → Agent earns: R 30,000 (60% of R 50,000)
  → Company retains: R 20,000 (40% of R 50,000)

Same calculation applies to selling side.
Agent PAYE deducted if configured on user setup.
```

**External side handling:** If listing or selling side is external agency, that side's commission is excluded from internal calculations. Optional partial "our share" percent allowed.

### 4.3 Worksheet (Planning vs Actual)

Originally the foundation of the system. Two-sided comparison:

**Planned side (budget-based):**
- Agent inputs personal monthly budget requirements
- System reverse-calculates required sales volume using:
  - Area average sales price
  - Standard company commission of 7.5%
  - Agent's split percentage
- Rolls up to branch budget — each agent's share of what the branch needs to earn

**Actual side (deal-based):**
- Reads from Deal Register for that specific agent
- Uses actual deal values and actual commission percentages taken
- Shows real earnings vs planned requirements

**Key distinction:** Planned uses standardised assumptions (7.5%, area avg). Actual uses real deal data. These are intentionally different calculation bases.

### 4.4 Daily Activity / Points System

Agents log daily activities from a configurable list. Each activity type has a weighted point value. Agents have monthly point targets.

**Structure (verify in codebase):**
- Activity types with point weights (configurable by admin)
- Per-agent daily activity entries
- Monthly point targets per agent
- Dashboard showing points earned vs target

### 4.5 Dashboards

All dashboards read from **stored computed values only**. Never recalculate money on render.

| Dashboard | Route Prefix | Shows |
|---|---|---|
| Agent Dashboard | `/agent/dashboard` | Own deals, own commission, own performance, pace tracking |
| Agent Performance | `/agent/performance` | Detailed own metrics, target vs actual |
| BM Dashboard | `/bm/dashboard` | Branch totals, agent leaderboard |
| BM Agent Performance | `/bm/agent-performance` | Branch agents comparison |
| BM Branch Performance | `/bm/branch-performance` | Branch-level KPIs |
| Admin Dashboard | `/admin/dashboard` | Company totals, all branches summary |
| Admin Agent Performance | `/admin/agent-performance` | All agents comparison |
| Admin Branch Performance | `/admin/branch-performance` | All branches comparison |
| Admin Company Performance | `/admin/company-performance` | Company-level KPIs |
| Daily Summary | `/{role}/daily-summary` | Current date + 7 days back |

### 4.6 TV Dashboards

Public-facing display screens for office TVs:
- `GET /tv` — company overview
- `GET /tv/branch/{branch}` — branch-specific
- Read only stored computed values. Never compute money live.

### 4.7 Target System

Each agent has monthly targets (value-based and commission-based). Used for pace tracking, progress bars, and performance comparisons. Does not compute money itself — only compares against stored computed values.

---

## 5. FINANCE ENGINE

### 5.1 Design

All financial logic is centralised in a versioned formula system.

**Table: `finance_definitions`**
| Column | Purpose |
|---|---|
| `definition_key` | e.g., `gross_commission`, `agent_income`, `company_retained` |
| `expression` | Formula string |
| `version` | Incremented on change |
| `is_enabled` | Active flag |
| `effective_from` / `effective_to` | Date range validity |

**Table: `finance_computed_values`**
| Column | Purpose |
|---|---|
| `entity_type` | `agent_period`, `branch_period`, `company_period` |
| `entity_id` | FK to agent/branch/company |
| `period` | `YYYY-MM` format |
| `definition_key` | Which formula produced this |
| `definition_version` | Which version of the formula was used |
| `value` | The computed result |
| `computed_at` | Timestamp |

### 5.2 Execution Flow

```
Deal saved
  → Finance Engine computes deal-level outputs
  → Period rollups triggered
  → Agent period recalculated
  → Branch period recalculated
  → Company period recalculated
  → Computed values stored
  → Dashboards read stored values
```

### 5.3 Version Handling

When a formula changes:
- Old computed rows retain their `definition_version`
- Historical periods remain consistent
- Audit can detect version mismatches
- **No retroactive silent mutation** — old deals keep old formula results

### ⚠ REALITY CHECK

The Finance Engine and definitions table exist and have data. However:
- **It's unclear whether ALL financial calculations actually flow through it**, or whether some screens still use hardcoded formulas from the patchwork era
- **This must be audited** before trusting the engine as the single source of truth
- **Priority task:** Trace every financial value displayed on every dashboard back to its source — is it reading from `finance_computed_values` or calculating inline?

---

## 6. FINANCE AUDIT SYSTEM

### 6.1 Purpose

Verify that stored computed values match what the formulas would produce if re-run. Detect drift, bugs, and data corruption.

**Table: `finance_audit_runs`**
| Column | Purpose |
|---|---|
| `entity_type` | What was audited |
| `entity_id` | Which entity |
| `period` | Which period |
| `triggered_by` | Who triggered it |
| `status` | Running/complete |

**Table: `finance_audit_items`**
| Column | Purpose |
|---|---|
| `definition_key` | Which formula |
| `stored_value` | What's in `finance_computed_values` |
| `recomputed_value` | What the formula produces now |
| `delta` | Difference |
| `status` | PASS (delta = 0) or FAIL (delta ≠ 0) |

### 6.2 Rules

- Audit does NOT mutate financial state
- No auto-overwrites — mismatches are flagged, not silently fixed
- No auto-recalculation — human must review and approve
- Admin-only trigger

### ⚠ CURRENT LIMITATION

The audit system is built and shows results, but Johan cannot easily verify results without running SQL queries directly. **A key improvement would be a clear admin UI that shows audit results in plain language** — which deals failed, what the expected vs actual values are, and a button to approve recalculation.

---

## 7. DATABASE SCHEMA

### Core Tables
| Table | Purpose |
|---|---|
| `users` | All users. Key fields: `role`, `branch_id`, agent split %, PAYE % |
| `branches` | Branch records |
| `deals` | One row per deal — property value, commission, status, external flags |
| `deal_agents` | Pivot — multi-agent per side. Fields: `deal_id`, `user_id`, `side` (listing/selling), `split_percent` |
| `agent_targets` | Monthly targets per agent |

### Finance Tables
| Table | Purpose |
|---|---|
| `finance_definitions` | Versioned formula definitions |
| `finance_computed_values` | Stored computed results per entity per period |
| `finance_audit_runs` | Audit execution records |
| `finance_audit_items` | Per-definition audit comparison results |

### ⚠ CRITICAL: VERIFY — Agent Split Storage

The agent/company split percentage (e.g., 60/40) is stored on the `users` table as the current default. **It MUST also be copied to `deal_agents.split_percent` at deal capture time** so that historical deals retain the split that was active when the deal was done.

**If settlement reads from `users` table at render time instead of `deal_agents`:** Changing an agent's split retroactively corrupts ALL their historical deal calculations. This is a potential data integrity time bomb. **Verify and fix if needed.**

---

## 8. ROUTE MAP

### Authentication
| Route | Purpose |
|---|---|
| `GET/POST /login` | Login |
| `POST /logout` | Logout |
| `GET/POST /forgot-password` | Password reset |
| `GET /dashboard` | Role-based redirect to admin/bm/agent dashboard |

### Deal Register
| Route | Role |
|---|---|
| `GET /admin/deals` | Admin — all deals |
| `GET /admin/deals/create` | Admin — new deal |
| `POST /admin/deals` | Admin — store deal |
| `GET /admin/deals/{deal}/edit` | Admin — edit deal |
| `PUT /admin/deals/{deal}` | Admin — update deal |
| `DELETE /admin/deals/{deal}` | Admin — delete deal |
| `GET /bm/deals` | BM — branch deals |
| `GET /bm/deals/{deal}` | BM — view deal |
| `GET /agent/deals` | Agent — own deals |
| `GET /agent/deals/{deal}` | Agent — view deal |
| `POST /agent/deals/{deal}/remarks` | Agent — add remarks |

### Performance Dashboards
| Route | Role |
|---|---|
| `GET /admin/dashboard` | Admin dashboard |
| `GET /admin/agent-performance` | All agents comparison |
| `GET /admin/branch-performance` | All branches comparison |
| `GET /admin/company-performance` | Company KPIs |
| `GET /bm/dashboard` | BM dashboard |
| `GET /bm/agent-performance` | Branch agents |
| `GET /bm/branch-performance` | Branch KPIs |
| `GET /agent/dashboard` | Agent dashboard |
| `GET /agent/performance` | Agent detailed metrics |

### Finance Engine & Audit (Admin only)
| Route | Purpose |
|---|---|
| `GET /admin/finance/definitions` | View/manage formulas |
| `POST /admin/finance/definitions` | Create definition |
| `PUT /admin/finance/definitions/{def}` | Update definition |
| `POST /admin/finance/recalculate` | Recalculate all |
| `POST /admin/finance/recalculate/{period}` | Recalculate specific period |
| `POST /admin/finance/audit/{entity_type}/{entity_id}/{period}` | Trigger audit |
| `GET /admin/finance/audit/{audit_run}` | View audit results |
| `GET /admin/finance/audit-history` | Audit history |

### Targets
| Route | Role |
|---|---|
| `GET /admin/targets` | Admin — manage targets |
| `POST /admin/targets` | Admin — set target |
| `PUT /admin/targets/{target}` | Admin — update target |
| `GET /bm/targets` | BM — view branch targets |
| `GET /agent/targets` | Agent — view own targets |

### TV Dashboards
| Route | Purpose |
|---|---|
| `GET /tv` | Company TV display |
| `GET /tv/branch/{branch}` | Branch TV display |

### System / Maintenance (Admin only)
| Route | Purpose |
|---|---|
| `POST /admin/system/rebuild-period` | Rebuild period data |
| `POST /admin/system/recalculate-all` | Full recalculation |
| `POST /admin/system/clear-cache` | Clear system cache |

### Admin Setup Screens
| Route | Purpose |
|---|---|
| `GET /admin/users` | User management (incl. split %, PAYE %) |
| `GET /admin/branches` | Branch management |
| Company details setup | (verify route) |
| Daily activity type setup | (verify route) |
| Various dropdown/config screens | (verify routes — multiple admin setup pages exist) |

---

## 9. COMMISSION MATH REFERENCE

This is the canonical calculation. Every place in the codebase that computes commission must produce these same numbers.

```
INPUTS:
  property_value        = R 1,000,000 (inc VAT)
  total_commission      = R 115,000 (inc VAT)
  vat_rate              = 15%
  listing_split         = 50%
  selling_split         = 50%
  agent_company_split   = 60/40 (agent gets 60%)
  agent_paye            = (if configured on user)

STEP 1: Strip VAT from commission
  commission_ex_vat = R 115,000 / 1.15 = R 100,000
  (Property value stays as-is for reporting — only commission gets VAT stripped)

STEP 2: Split between listing and selling sides
  listing_commission  = R 100,000 × 50% = R 50,000
  selling_commission  = R 100,000 × 50% = R 50,000

STEP 3: If external side, exclude or apply our_share_percent
  (If listing is external with our_share = 30%: listing_our_share = R 50,000 × 30% = R 15,000)
  (If not external: full amount)

STEP 4: Apply agent/company split (per side)
  listing_agent_income     = R 50,000 × 60% = R 30,000
  listing_company_retained = R 50,000 × 40% = R 20,000

STEP 5: Apply PAYE (if configured)
  agent_paye_deduction = listing_agent_income × paye_percent
  agent_net = listing_agent_income - agent_paye_deduction

REPEAT for selling side.
```

**Where this calculation SHOULD live:** Finance Engine service layer only.
**Where it might ALSO live (the patchwork problem):** Settlement controller, worksheet service, dashboard queries, Blade templates. **Find and consolidate.**

---

## 10. KNOWN BUGS & ARCHITECTURAL DEBT

### High Priority — Financial Integrity
| Issue | Risk | Action |
|---|---|---|
| Duplicate formulas across settlement/worksheet/dashboards | Mismatched figures across screens | Audit all formula locations, consolidate to service layer |
| Agent split may read from `users` table instead of `deal_agents` | Historical deals corrupted on split change | Verify storage, fix if reading from users at render time |
| Finance Engine may not be the actual source for all screens | Dashboards may bypass stored computed values | Trace every displayed value to its source |
| Audit results require SQL knowledge to verify | Owner cannot validate system integrity | Build admin-friendly audit results UI |

### Medium Priority — System Quality
| Issue | Risk | Action |
|---|---|---|
| Two permission systems coexist | Access control conflicts | Audit and unify |
| Patchwork code from incremental GPT sessions | Hidden bugs, inconsistent patterns | Gradual refactoring, service consolidation |
| Multiple admin setup screens (locations unknown) | Hard to maintain, may have orphaned config | Map all setup routes and verify they work |
| Daily activity points system untested | May have calculation errors | Test with real data |

---

## 11. SETUP & CONFIGURATION SCREENS

The system has multiple admin-configurable areas. These need to be mapped from the codebase:

- **User setup** — agent split %, PAYE %, role, branch assignment
- **Branch setup** — branch details, location
- **Company details** — company-level configuration
- **Daily activity setup** — activity types, point weights
- **Various dropdown configurations** — (verify what exists)
- **Role & Permission manager** (Andre-built, in Nexus admin) — granular permissions per role

**Rule for Claude:** When encountering a setup/config screen, verify it reads from and writes to the correct table. Do not add hardcoded values that should be configurable.

---

## 12. RELATIONSHIP TO NEXUS / PRESENTATIONS

The Agency Tracker and Presentation System share the same Laravel codebase:

| Aspect | Agency Tracker | Presentations |
|---|---|---|
| Sidebar | `nexus-sidebar.blade.php` | `sidebar.blade.php` |
| Route prefix | `/admin/`, `/bm/`, `/agent/`, `/tv/` | `/presentations/` |
| Production URL | performance.hfcoastal.co.za | (same domain, different routes) |
| Integration | Andre merged into Nexus shell | Built directly in Nexus |

**Rule for Claude:** Changes to shared infrastructure (auth, middleware, layouts, base models) affect BOTH systems. Be aware of cross-module impact.

---

## 13. DEVELOPMENT WORKFLOW

```
Local dev (VS Code Claude on Windows, localhost:8000)
  → Test locally
  → Run dev-check script
  → Deploy to production VPS
  → No live hacking on production
```

---

## 14. PRIORITY TASK LIST — AGENCY TRACKER AUDIT & STABILISATION

Before building new features, the tracker needs to be audited and stabilised.

### Phase 1: Code Audit (understand what exists)
1. Map ALL service classes and their financial calculations
2. Find every place commission/split math is done — list every file
3. Verify `deal_agents` stores split at capture time (not read from `users` at render)
4. Verify dashboards read from `finance_computed_values` (not inline calculations)
5. Map all admin setup/config routes and verify they work
6. Document the daily activity/points system structure

### Phase 2: Consolidation (fix the patchwork)
1. Consolidate all commission calculations into Finance Engine service layer
2. Remove duplicate formulas from controllers, Blade templates, and non-service classes
3. Ensure settlement, worksheet, and ALL dashboards read from the same source
4. Unify permission systems (route middleware + role manager)

### Phase 3: Verification
1. Run finance audit across all periods
2. Build admin-friendly audit results UI
3. Cross-check all dashboard figures against settlement figures
4. Test with real production data (60+ deals)

### Phase 4: Enhancement (only after stabilised)
- Auto-audit on period close
- Period locking
- Deal-level audit tracing
- Simulation mode (what-if scenarios)
- Better daily activity reporting

---

*End of Agency Tracker CLAUDE.md — Living document. Update when architecture changes.*
