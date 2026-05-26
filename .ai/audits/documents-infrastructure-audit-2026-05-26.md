# Documents Infrastructure Audit — 2026-05-26

## Executive Summary

**CRITICAL FINDING: Phase 9c-3 (`company_documents`) DUPLICATES a pre-existing "agency-level documents with branch override" system that was intentionally built 4 days earlier.**

Phase 9c-3 (commit `efe6d45`, 2026-05-25) created a new `company_documents` table for admin-managed legal content (privacy policy, T&Cs, etc.) with:
- Public `/legal/{token}` route
- Token-based access pattern  
- Markdown + HTML content storage
- Agency-scoped publish/unpublish workflow

However, CoreX **already had** an operationally deployed system (`agency_compliance_provisions` + `agency_document_type_configs`, commit `eb6a5af`, 2026-04-22) that provides:
- Agency-level documents with **branch-level overrides** (company-wide fallback)
- File upload + versioning
- Expiry tracking and renewal workflows
- Compliance certificate management (PI insurance, tax clearance, FFC, etc.)
- Agent portal display with scope badges ("Company" vs "Branch")

**Verdict: Phase 9c-3 should NOT have created a new table. It should have extended the existing `agency_compliance_provisions` system to add public publishing capability and support for legal-document types.**

---

## 1. Documents-Related Tables Inventory (37 total, 2 in scope)

**Pre-existing branch-override system:**
- `agency_compliance_provisions` — Agency-level compliance docs with branch overrides
- `agency_document_type_configs` — Config for doc types (required, expiry_days, etc.)

**Phase 9c-3 system:**
- `company_documents` — Legal docs with public URLs (privacy policy, T&Cs, etc.)

**Others:** contact_documents, user_documents, documents (unified), docuperfect_documents, fica_documents, document_amendments, etc. (30+ more, orthogonal to this audit)

---

## 2. The Pre-Existing System: agency_compliance_provisions

### Built: 2026-04-22, commit `eb6a5af`

**Status:** LIVE, operational, in use by agents daily via sidebar "Agency Documents" link

### Schema: agency_compliance_provisions

Columns:
- `id`, `agency_id`, `document_type_config_id`
- **`branch_id`** (nullable FK → branches) ← **BRANCH OVERRIDE SUPPORT**
- `document_path`, `document_original_name` (file uploads)
- `effective_from`, `effective_until` (date-based expiry)
- `status` (enum: active|expired|superseded)
- `created_by`, timestamps, soft deletes

### How It Works: Branch Fallback Resolution

From `AgencyComplianceProvision::resolveForUser()` (line 109):

1. If user has `branch_id`, try to find a branch-specific override (active, latest by effective_from)
2. If found, return it (branch override wins)
3. Otherwise, fall through to company-wide (`branch_id IS NULL`)
4. Return company version or NULL

Result:
- **Branch users see:** Branch-specific document if uploaded, else company fallback
- **Company users see:** Company-wide document
- **Agent portal shows:** Scope badge "Branch" (blue) or "Company" (grey)

### Agent Portal Display

File: `resources/views/compliance/my-portal/agency-documents.blade.php` (line 65)

Shows:
- Grid of **compliance documents** (file uploads from `agency_compliance_provisions` only)
- Status badges: Available, Expiring (amber), Expired (red), Required but missing (red)
- Download buttons
- **Scope badges:** "Branch" (blue) or "Company" (grey)
- **NO public URLs** displayed to agents

---

## 3. Phase 9c-3: company_documents (2026-05-25, commit efe6d45)

### Schema: company_documents

Columns:
- `id`, `agency_id`, `document_type` (string)
- `title`, `content` (longtext)
- `content_format` (markdown | html, default markdown)
- **`public_token`** (unique, 48 chars, auto-generated)
- **`is_published`, `published_at`** (publish workflow)
- `last_updated_by_user_id`, timestamps, soft deletes

### Routes

**Admin:** `POST/GET/PUT /corex/admin/company-documents/*` (CRUD)  
**Public:** `GET /legal/{token}` (no auth, throttle 60/min, 404 unless published)

### Document Types (Hardcoded Enum)

`privacy_policy`, `terms_of_service`, `complaints_procedure`, `aml_statement`, `code_of_conduct`, `popia_consent_text`

---

## 4. The Duplication

| Aspect | Pre-existing | Phase 9c-3 | Assessment |
|--------|------|---|---|
| **Scope** | Agency-level | Agency-level | REPLICATED |
| **Storage** | File uploads | Markdown/HTML text | Different, valid |
| **Branch overrides** | YES (`branch_id` FK) | NO | **MISSING in Phase 9c-3** |
| **Public URLs** | NO | YES (`/legal/{token}`) | **NEW, valid feature** |
| **Expiry tracking** | YES (`effective_until`) | NO | **MISSING in Phase 9c-3** |
| **Admin UI** | `/compliance/agency-settings/*` | `/corex/admin/company-documents/*` | Separate pages |
| **Type system** | Per-agency config | Hardcoded enum | Different approach |

**Root cause:** Phase 9c-3 was built to close POPIA privacy policy requirement. Developer created minimal, purpose-built table rather than extending the existing system.

---

## 5. Risk of Building L and P on Phase 9c-3 Alone

1. **Dual systems diverge** — incompatible when Phase 9c-3 eventually gets branch support
2. **Admin confusion** — two separate "Agency Documents" pages to maintain
3. **Agent confusion** — fragmented UX (some docs in one portal section, others elsewhere)
4. **Calendar mess** — expiry reminders for compliance docs but not legal docs (or vice versa)
5. **Hard to unify later** — data already separated by table

---

## 6. The "Agency Documents" Sidebar Item

### Navigation

File: `resources/views/layouts/corex-sidebar.blade.php`, line 522

Links to: `route('my-portal.agency-documents')`

### Data Source

Route: `GET /my-portal/agency-documents` (routes/web.php, line 1215)

Controller: `AgencyDocumentsViewerController::index()`

**Data read from:** `agency_compliance_provisions` + `agency_document_type_configs` ONLY

Does **NOT** read from `company_documents`

### What Agents See

- Grid of compliance documents (PI insurance, tax clearance, FFC, etc.)
- Status + scope badges
- Download buttons
- **NO legal documents** (privacy policy, T&Cs) — those are Phase 9c-3, admin-only
- **NO public URLs** shown to agents

---

## 7. Git Timeline

**2026-04-22, 14:00 (commit eb6a5af):**
- Phase 3: Branch-level document overrides
- `agency_document_type_configs` table created
- `agency_compliance_provisions` table created with `branch_id` FK
- Agent portal "Agency Documents" sidebar link + controller + view added
- Status: LIVE

**2026-05-25, 21:12 (commit efe6d45):**
- Phase 9c-3: Company Documents + public /legal/{token} route
- `company_documents` table created (NEW, separate table)
- Admin CRUD + public controller + routes added
- Markdown editor + publish workflow
- Status: LIVE

**Time between builds:** 33 days

---

## 8. Answer: "Did We Build This?"

### Your Memory: "company-level documents with branch override"

**VERDICT: YES. You were right.**

**System:** `agency_compliance_provisions` + `agency_document_type_configs` (commit eb6a5af, 2026-04-22)

**What it does:**
1. Stores agency-level documents (file uploads)
2. **SUPPORTS branch-level overrides** via `branch_id` FK + fallback resolution
3. Shows scope badges ("Branch" vs "Company") in agent portal
4. Tracks expiry + renewal workflows
5. **LIVE in production** — agents use it daily

**Why Phase 9c-3 looks like a duplicate:**

Phase 9c-3 created a *separate* table for legal/compliance documents (privacy policy, T&Cs) with a *different* model (markdown instead of file uploads) and a *new* feature (public token-based URLs). While it also agency-scopes documents, it **does NOT yet support branch overrides**.

**Conclusion:** Duplication of *scope* (both agency-level documents), gap in *feature* (branch overrides missing from Phase 9c-3).

---

## 9. Recommended Forward Path

### OPTION A: Unify (Recommended long-term)

**Action:** Merge `company_documents` into `agency_compliance_provisions`

- Add `content` (longtext) + `content_format` (file|markdown|html) columns
- Add `public_token` (nullable, unique where set) + `is_public_published` (boolean)
- Migrate Phase 9c-3 data to new rows with new document types
- Create unified admin page under `/compliance/documents/`
- Unify `resolveForUser()` to handle branch overrides for *all* doc types

**Pros:** One source of truth, branch overrides work for all doc types, expiry + calendar work for all

**Cons:** Requires Phase 9c-3 rollback + refactor

**Timeline:** M-size, 2–3 sessions

### OPTION B: Keep Separate, Add Branch Support to company_documents (Faster)

**Action:** Unify admin UI + add branch-override pattern to `company_documents`

- Add `branch_id` FK to `company_documents`
- Update `CompanyDocumentController` to use `resolveForUser()` fallback pattern
- Create unified "Documents" admin page that shows both compliance files + legal docs
- Still two tables, but one admin UI

**Pros:** Keeps Phase 9c-3 mostly intact, branch overrides work, single admin page

**Cons:** Two tables remain (long-term debt), duplicate resolution logic

**Timeline:** S-size, 1 session

### OPTION C: Accept Duplication (No refactor)

**Action:** Keep both systems separate

- Document the split in CODEBASE_MAP
- Schedule unification as future tech debt
- Let L and P layers build on `company_documents` as-is

**Pros:** Zero short-term effort

**Cons:** Highest long-term maintenance burden, harder to unify later

**Timeline:** 0

---

## 10. File:Line Reference

**`agency_compliance_provisions` system:**
- Model: `app/Models/Compliance/AgencyComplianceProvision.php`, line 109 (`resolveForUser`)
- Agent controller: `app/Http/Controllers/Compliance/AgencyDocumentsViewerController.php`, line 31
- Agent view: `resources/views/compliance/my-portal/agency-documents.blade.php`, line 65 (scope badge)
- Sidebar: `resources/views/layouts/corex-sidebar.blade.php`, line 522
- Routes: `routes/web.php`, line 1215 (agent), line 1435 (admin)

**`company_documents` system:**
- Model: `app/Models/CompanyDocument.php`
- Admin controller: `app/Http/Controllers/Admin/CompanyDocumentController.php`
- Public controller: `app/Http/Controllers/Public/CompanyDocumentController.php`
- Public view: `resources/views/public/legal-document.blade.php`
- Routes: `routes/web.php`, line 58 (public), line 1450 (admin)

---

## Conclusion

**Phase 9c-3 duplicates the agency-level document management scope without the branch-override capability. Before L and P layers build complexity on top of it, decide: Unify (Option A), add branch support + unified UI (Option B), or accept debt (Option C).**

**Core question for Johan's review:** Was Phase 9c-3 built separately due to genuinely different requirements (public URLs, markdown editor), or due to lack of visibility into the existing `agency_compliance_provisions` system? If the latter, a refactor before L and P is warranted.

---

Report generated: 2026-05-26
Audit method: Code analysis + git history + schema inspection
Status: READY FOR REVIEW
