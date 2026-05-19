# CoreX OS — E-Signature V2 Specification
# State of Reality — March 27, 2026 (Audit-Verified)

> This spec reflects what ACTUALLY EXISTS in the codebase, verified by
> a full code audit on March 27, 2026. Every claim has been traced to
> exact file:line references. This is the single source of truth.
> Every VS Code prompt must read this file before making any changes.

---

## 1. Legal Boundaries

### What CAN be e-signed
- FICA declarations and consent forms
- Mandates (letting mandates, sole mandates, marketing mandates, exclusive authority)
- Mandatory disclosure forms
- POPIA consent forms
- Lease agreements (under 10 years)
- Rental applications
- Inspection reports
- Any internal agency document

### What CANNOT be e-signed (triple-enforced block)
- Agreement for alienation of immovable property (sale agreement / OTP)
- Lease agreements over 10 years
- Template types `sale_agreement` and `otp` are blocked at THREE levels:
  1. Model: `Template::isEsignBlocked()` (Template.php:149-160) — checks template_type AND name patterns
  2. Client JS: `effectiveDeliveryModes` getter (wizard.blade.php:1573-1580) — removes 'esign' from modes
  3. Server: `prepareSigning()` hard block (ESignWizardController.php:1208-1212)

---

## 2. Architecture Overview

### Core Files
| File | Purpose |
|------|---------|
| `ESignWizardController.php` | 6-step wizard: template → property → recipients → details → fill/review → sign/send |
| `SignatureController.php` | Setup (marker placement), agent signing, review, approve. Also handles candidate practitioner supervisor flow |
| `SigningController.php` | External signer flow (gateway → signing → completion) |
| `SignatureService.php` | Core logic: createSigningRequest, handlePartyCompletion, advanceToNextParty, sendSigningRequest, autoFileSignedDocument, approveAndAdvance |
| `SignaturePdfService.php` | PDF generation via Puppeteer (generateFromHtml for web templates) |
| `WebTemplateDataService.php` | Field resolution for web templates |
| `TemplateController.php` | Template CRUD, CDS generation, field management |

### Key Views
| View | Purpose |
|------|---------|
| `esign/wizard.blade.php` | 6-step e-sign wizard |
| `signatures/setup.blade.php` | Marker placement on document |
| `signatures/sign.blade.php` | Agent signing view |
| `signatures/external/sign.blade.php` | External signer signing view |
| `signatures/external/fica-gate.blade.php` | FICA gate before signing |
| `signatures/review.blade.php` | Agent review after party signs |
| `signatures/external/print.blade.php` | Print view for recipients |
| `signatures/external/amendment-review.blade.php` | Amendment accept/reject |
| `templates/edit.blade.php` | Legacy PDF template editor |
| `templates/cds-builder.blade.php` | CDS web template builder |
| `esign/my-documents.blade.php` | Agent document management |

### Key Database Tables
| Table | Purpose |
|-------|---------|
| `docuperfect_templates` | Template definitions with all settings |
| `docuperfect_documents` | Document instances |
| `signature_templates` | Signing config per document flow |
| `signature_requests` | Per-party signing status and tracking |
| `signature_markers` | Signature/initial positions on document |
| `signature_zones` | Bounding boxes for dynamic signature areas |
| `document_amendments` | Clause flags and amendment records |
| `esign_consent_log` | Immutable consent records |
| `agency_signing_parties` | Agency-level party name definitions |
| `contact_types` | Contact type with esign_role mapping |

---

## 3. Template Configuration

### Template Settings — What Each One Does (Audit-Verified)

| Setting | DB Column | What It Does | Status |
|---------|-----------|--------------|--------|
| Template Name | `name` | Display name | WORKING |
| Category | `category` | Sales/Rentals filter on template list UI. NOT used in wizard for any logic | WORKING (UI only) |
| Document Type | `document_type_id` | FK to document_types. Used for template list filtering AND auto-filing signed docs to contacts/properties | WORKING |
| Template Type | `template_type` | Legal e-sign block for sale_agreement/otp types | WORKING — critical |
| Global | `is_global` | Branch visibility | WORKING |
| Eligible for E-Sign | `is_esign` | Coarse gate: show/hide from wizard template list | WORKING |
| Signing Mode | `party_mode` | Shared (all sign same doc) vs Per Party (separate copy each) | WORKING |
| Allowed Delivery Modes | `allowed_delivery_modes` | Fine-grained: which modes (esign/wet_ink/download) appear in wizard Step 6. Read by getAllowedDeliveryModesArray(), allowsDeliveryMode(), getEffectiveDeliveryModes() | WORKING |
| Security Tier | `security_tier` | Stored but NEVER read by any business logic. Pure pass-through. | DEAD — safe to remove |
| Signing Parties | `signing_parties` | JSON array of roles (owner_party/acquiring_party/agent). Used in 7+ files for sales/rental context detection, signature block rendering, wizard role fallback. Feeds isSalesDocument(). | WORKING — critical |

### is_esign vs allowed_delivery_modes — NOT duplicates
- `is_esign` = "Can this template appear in the wizard at all?" (boolean gate)
- `allowed_delivery_modes` = "Which delivery methods are available?" (fine-grained)
- A template can have `is_esign=true` but `allowed_delivery_modes='wet_ink,download'` (appears in wizard but e-sign mode unavailable)
- Both are needed — independent, complementary gates

### Two "Signing Parties" UI Elements — NOT duplicates

**"Signature Block Parties"** (cds-builder.blade.php:167-237)
- Agency-wide list stored in `agency_signing_parties` table
- Names like: Agent, Seller, Buyer, Lessor, Lessee, Witness
- Used by: signature/initial tag checkboxes ("which parties sign here?")
- Scope: all templates for the agency
- CRUD via DocumentImporterController (store, update, destroy, reorder)

**"Document Signing Roles"** (cds-builder.blade.php:320-346)
- Per-template setting stored in `docuperfect_templates.signing_parties` JSON
- Values: owner_party, acquiring_party, agent
- Used by: sales/rental context detection, signature block rendering, wizard fallback role
- Scope: this template only

### Per-Field Settings

**Legacy PDF templates (template editor):**
- `fields_json[].assignedTo` — dropdown: who fills this field (creator/agent/seller/tenant etc)
- Agent fills = editable. Others = read-only with "[Party] will complete" label
- No "editable at signing by" checkboxes exist in legacy editor

**CDS web templates (CDS builder):**
- `field_mappings[].filled_by` — who fills the field initially
- `field_mappings[].editable_by` — array of roles who can EDIT at signing time
- Checkboxes with tooltip: "If none selected, field is locked after agent fills it"
- Role mapping at signing: seller→owner_party, buyer→acquiring_party, agent→agent, witness→witness
- JS converts matching fields to `<input>` elements with amber styling (sign.blade.php:1865-1895)

---

## 4. E-Sign Wizard (6 Steps)

### Step 1: Template Selection
- Shows templates where `is_esign = true`
- Filtered by branch access
- Status: WORKING

### Step 2: Property Selection
- Agent picks a property
- Tracks `_property_source` for context (properties vs rental_properties)
- Status: WORKING

### Step 3: Recipients
- Loads ALL contacts linked to the property via `contact_property` pivot (ESignWizardController:491)
- **BUG #1**: No filtering by template context — all contact types shown regardless of document type
- **BUG #1b**: Auto-populate only works when propertySource === 'properties' — skips rental_properties entirely
- **BUG #1c**: Search API at line 949 uses hardcoded role map instead of esign_role
- **FIX PLANNED**: Use `esign_role` on `contact_types` table to filter contacts based on template's `signing_parties`
- Manual add always available (no filter)
- Status: BROKEN — needs contact filtering + rental property support

### Step 4: Details
- Sales fields or rental fields based on template context
- **BUG #2**: 3-layer context detection has priority flaw. If template has generic roles (owner_party/acquiring_party) AND template name contains 'authority'/'sell'/'mandate', Layer 3 fallback incorrectly returns sales context even for rental properties. Layer 2 (property source) should take precedence but doesn't.
- Status: BROKEN — rental docs show sales fields

### Step 5: Fill & Review
- Agent fills template fields, reviews document preview
- Status: WORKING

### Step 6: Sign & Send
- FICA required toggle (default ON) — stored on signature_request
- Delivery mode selection (filtered by template's allowed_delivery_modes)
- Agent signs
- Send to recipients
- Status: WORKING

---

## 5. Signing Flow

### Agent Signing
- Agent signs first in the wizard
- Signature captured via canvas (draw) or typed name
- All signature/initial markers assigned to agent are rendered
- Status: WORKING

### External Signer Flow
1. Recipient receives email with signing link
2. Opens link → SigningController::show()
3. **FICA Gate**: If `fica_required=true` on the signature_request:
   - Check if contact has FicaSubmission with status in (`submitted`, `under_review`, `agent_approved`, `approved`)
   - If NO → show fica-gate.blade.php with "Complete FICA Form" button
   - If YES → proceed to signing (gate lifts on SUBMISSION, not approval)
4. Document renders with signatures applied so far
5. Recipient signs their assigned blocks
6. Completion → returns to agent for review

### Agent Approval Gate
- After each external party signs, document returns to agent
- Status: `pending_agent_approval`
- Agent reviews what party filled/signed (SignatureController:2004 review())
- Agent approves (SignatureController:2199 approve()) → advances to next party via approveAndAdvance()
- Agent can return to party with notes
- Also handles candidate practitioner flow (awaiting_supervisor, awaiting_supervisor_final)
- Status: WORKING

### Delivery Modes
| Mode | How It Works | Status |
|------|-------------|--------|
| E-Sign | Web-based signing flow (as above) | WORKING |
| Wet Ink | Document posted to secure portal, printed, signed physically, scanned | WORKING |
| Download Only | PDF generated, agent downloads for manual handling | WORKING |

---

## 6. Signature/Initial Rendering

### Four Systems (Historical)
The codebase has four separate mechanisms that produce signature elements:

1. **HTML @include sig-lines** — CDS compiler inserts `@include('signature-line')` per party in the blade template
2. **Signature Zones** — bounding boxes drawn in setup, expanded to markers via `expandZone()`
3. **Signature Markers** — precise positioned overlays (x%, y%, page) rendered in signing views
4. **Inline sig-line includes** — signature sections rendered in the HTML document itself

### How They Interact
- CDS compiler (step 1) generates the static signature sections in the document HTML
- Zones (step 2) are drawn by the agent in setup and converted to markers
- Markers (step 3) are rendered as floating overlays on top of the paginated HTML
- All three must align to the same coordinate system (pageContainer locked to 210mm width)

### Coordinate System
- pageContainer locked to `width:210mm; max-width:100%` in all three views:
  - setup.blade.php:300
  - sign.blade.php:190
  - external/sign.blade.php:411
- Markers placed before the 210mm width fix may be slightly mispositioned
- New markers placed after the fix are consistent across all views

---

## 7. PDF Generation

### Process
1. Document HTML (merged_html with signatures embedded) → temp HTML file
2. Puppeteer/Chromium renders HTML → PDF via generate-pdf.sh
3. Zoom: 0.82, correct margins, 3-page output matching web view

### Two PDF Types
- **Internal PDF**: Full document with audit certificate
- **Client PDF**: Document without internal audit data
- Both generated by SignaturePdfService::generateFromHtml() (line 99-157)

### BUG #5: Initials Missing from Final PDF
- SignaturePdfService.php:298-301 explicitly skips initials: `if (in_array($type, ['signature', 'initial'])) { continue; }`
- Comment says "handled by signature markers" but marker rendering depends on Alpine.js state that doesn't persist through Puppeteer
- Fix: render initial markers into the HTML before PDF generation, same as signatures

---

## 8. Document Filing

### Auto-Filing on Completion
- `autoFileSignedDocument()` in SignatureService.php:1749
- Splits merged HTML at `.corex-document-wrapper` boundaries
- Generates individual PDF per template in a pack
- Creates separate `Document` records with correct `document_type_id`
- Links to contacts and property via `document_contacts` and `document_properties` pivots

### BUG #4: Duplicate Entry on document_contact
- SignatureService.php:1723-1730 uses `DB::table()->exists()` check followed by `->insert()`
- This is a race condition — concurrent requests can pass the exists() check simultaneously
- Fix: replace with `updateOrCreate()` or `syncWithoutDetaching()` (the latter is already used correctly in linkFiledDocumentToContactsAndProperty at line 1988)

---

## 9. FICA Integration

### FICA Gate in E-Sign
- Wizard Step 6: "FICA verification required" checkbox per recipient (default ON)
- Stored on `signature_requests.fica_required`
- At send time: auto-creates FicaSubmission for contacts without approved FICA
- Gate checks status IN (`submitted`, `under_review`, `agent_approved`, `approved`)
- Gate lifts on SUBMISSION (not approval) — recipient has done their part
- Contact FICA status only updates on final CO approval
- Return-to-signing flow after FICA completion (hidden return_url field)

### FICA Compliance Module (Complete)
- Public token-based form covering all 4 SA FICA schedules (natural, company, trust, partnership)
- Two-stage approval: Agent review → CO review
- TFS screening with embedded FIC iframe + per-field copy buttons
- RMCP Version 4 viewable in-system (all 28 sections + schedules)
- PDF certificate generation
- Compliance dashboard with CO queue, tabs, pipeline indicator
- esign_role on contact_types for future wizard filtering

---

## 10. Amendment/Flag System

### Status: BACKEND COMPLETE, PARTIALLY WIRED

| Component | File | Status |
|-----------|------|--------|
| DocumentAmendment model | DocumentAmendment.php | Complete — types: addition/strikeout/modification, statuses: pending/accepted/rejected, soft deletes |
| Amendment review view | amendment-review.blade.php | Built — accept with initial, reject with reason |
| Accept/reject endpoints | SigningController | acceptAmendment(), rejectAmendment() methods |
| Routes | web.php:1245-1251 | GET amendment-review, POST accept, POST reject |
| Clause flags | SigningController:1061-1065 | Stored in web_template_data['clause_flags'] |
| Section reject | rejectSection() route | Allows rejecting individual document sections |

### What's Wired
- "Other Conditions" text changes auto-create amendments
- Amendment → notification → review → accept/reject with initials works for Other Conditions

### What's NOT Wired
- Clause flags are collected but do NOT create amendments
- No manual amendment trigger for agents
- Per the Act: every added condition requires initials from ALL parties — this needs verification

---

## 11. Editable at Signing

### For CDS Web Templates: BUILT, NO TEMPLATE DATA
- Infrastructure works: `field_mappings[].editable_by` → `getEditableFieldsFromMappings()` (SigningController:1197-1235) → JS converts fields to `<input>` (sign.blade.php:1865-1895)
- Role mapping correct: seller→owner_party, buyer→acquiring_party
- **HOWEVER**: No template currently has `field_mappings.editable_by` populated
- System falls back to `WebTemplateFieldPartyMap` static map
- Status: Infrastructure works but is UNTESTED IN PRODUCTION because no template uses it

### For Legacy PDF Templates: LIMITED
- `fields_json[].assignedTo` only controls who FILLS the field
- No "editable at signing" checkboxes exist in legacy editor
- Non-agent parties see fields as read-only

### "Other Conditions" Field
- Should be editable by recipients to add conditions
- Will work once editable_by is populated on the template's field_mappings
- Currently falls back to static map behaviour

---

## 12. Email Notifications

### Rules
- **Agents receive ZERO emails** — all notifications are in-app (database notifications via NotificationController + bell icon)
- **External signers receive exactly TWO emails**:
  1. Signing request (with "Sign Document" button)
  2. Completed PDF (after all parties signed)

---

## 13. Contact Filtering

### Current Behaviour (BROKEN)
ESignWizardController:491 loads ALL contacts from property via `$prop->contacts`. No filtering by template context, signing_parties, category, or contact type. Additionally, auto-populate only works for sales properties (propertySource === 'properties') — skips rental_properties entirely. Search API at line 949 uses hardcoded role map instead of esign_role.

### Solution: esign_role on contact_types
- New column `contact_types.esign_role` (varchar: seller/buyer/lessor/lessee/null)
- Set via dropdown in Settings → Contact Types
- Migration auto-seeds existing types based on name matching
- **NOT YET WIRED INTO WIZARD** — ContactType::scopeForEsignRole() exists but is never called

### Filter Logic (To Be Built)
```
Template signing_parties contains 'owner_party' →
  Show contacts where contact_type.esign_role IN ('seller', 'lessor')

Template signing_parties contains 'acquiring_party' →
  Show contacts where contact_type.esign_role IN ('buyer', 'lessee')

Template signing_parties is null →
  Show all contacts (legacy fallback)

Must also work for rental_properties, not just properties.

Manual add → always available, no filter
```

---

## 14. Known Bugs (Audit-Verified March 27, 2026)

| # | Bug | Root Cause | Status | Fix |
|---|-----|-----------|--------|-----|
| 1 | All contact types show on all templates | Wizard:491 loads all contacts, no filter. Also skips rental_properties. | OPEN | Wire esign_role filter + add rental property support |
| 2 | Rental documents show sales fields | Context detection Layer 3 fallback overrides Layer 2 (property source). Template name patterns force sales context. | OPEN | Fix layer priority: property source > name pattern |
| 3 | My Documents menu error | Route and controller exist but may be transient/data issue | UNCLEAR | Needs browser testing |
| 4 | Duplicate entry on document_contact | Race condition: exists() + insert() at SignatureService:1723-1730 | OPEN | Replace with updateOrCreate or syncWithoutDetaching |
| 5 | Final PDF missing initials | Explicit skip at SignaturePdfService:298-301 | OPEN | Render initials into HTML before PDF generation |
| 6 | Ellie bubble overlaps Next button | CSS: position:fixed bottom:24px right:24px z-index:9999 | OPEN | Reposition Ellie |

### Resolved
| # | Bug | Resolution |
|---|-----|-----------|
| 7 | Memory allocation on docuperfect_documents | FIXED — DocumentController uses ->paginate(20) |
| 8 | Wrong email (elizesouthbroom) | DATA FIX — Agent's email wrong in users table on staging, not a code bug |

---

## 15. What's Working Well (Audit-Confirmed)

- Full 6-step wizard flow
- Agent signing with ceremony fields
- Client-side A4 page pagination
- External signer view (gateway → signing)
- Agent approval gate (pending_agent_approval) including candidate practitioner flow
- Signature embedding into merged HTML
- CDS-to-blade compiler with multi-party signature extraction
- Pack splitting into individual documents on filing
- Three delivery modes (e-sign, wet ink, download)
- FICA gate on signing (lifts on submission)
- FICA compliance module (complete: form, two-stage approval, TFS, RMCP, PDF)
- Notification bell system
- Template management with full settings
- Document type management
- Legal e-sign block (triple-enforced)
- 210mm pageContainer consistency across all views

---

## 16. Parked Features

| Feature | Status | Notes |
|---------|--------|-------|
| Upload signature option | Specced | Third capture mode alongside draw/type |
| Staff FICA verification | Specced | Training records, acknowledgement flow, 12-month cycle |
| Amendment/flag full flow | Backend complete | Only Other Conditions wired. Clause flags inert. |
| Editable at signing | Infrastructure built | No template has field_mappings.editable_by populated |
| Signature upload (image) | Specced | In V2 spec as two-layer approach |

---

## 17. Build Priority (When Resuming)

1. **Bug #4: Duplicate entry** — replace insert()+exists() with updateOrCreate at SignatureService:1723-1730
2. **Bug #5: Initials in PDF** — render initial markers into HTML before Puppeteer
3. **Bug #1: Contact filtering** — wire esign_role into wizard Step 3 + rental property support
4. **Bug #2: Rental fields** — fix context detection layer priority
5. **Bug #6: Ellie position** — CSS reposition
6. **Verify amendment flow** — test Other Conditions end-to-end
7. **Populate editable_by** — set field_mappings on at least one CDS template to test
8. **Staff FICA training** — build when capacity allows

---

## 18. Hard Rules

- Every VS Code prompt starts with: read CLAUDE.md, .ai/STANDARDS.md, and this spec
- Every prompt ends with: php -l, view:clear, dev-check.ps1, Tinker verification
- No quick fixes — investigate first, report findings, get approval, then fix
- When Johan reports a bug, the code is wrong. Never suggest user error.
- No hard deletes anywhere. Soft deletes only.
- Commit HFC2402 to main and push after every session.
- DO NOT remove working code without full dependency trace.

---

## 19. Auto Per-Page Initials  — DRAFT (PROPOSED, pending Johan/Andre approval)

> Status: **DRAFT — NOT APPROVED. No code may be written against this section
> until it is reviewed and committed to `main`** (CLAUDE.md spec-first rule).
> Drafted by VS Code Claude from the session "WEB PACK + PER-PAGE INITIALS
> INVESTIGATION" (BUG 3). Size: **L**. Legally significant — initials appear
> on every page of signed mandates/disclosures; Document-Fidelity rule applies.

### 19.1 Requirement (business)

Every rendered A4 page of an e-sign document MUST carry one initial slot per
required signer, placed automatically by the system. Page count is
unpredictable (a 1-page template can render to 2+ when a signer adds text), so
this is a **render-time system rule**, not a template-design decision. Manual
`####` CDS markers remain supported but are no longer the only mechanism.
`autoPlaceInitialMarkers()` (ESignWizardController.php:~2100) does NOT serve
this — it estimates pages from one template's `cds_json`, is marker-overlay
based, and has no pack concept. It is out of scope here (leave as-is).

### 19.2 Placement & mechanism

- **When:** at client pagination time, immediately after `paginateDocument()`
  builds the `.corex-a4-page` elements (a4-page-styles.blade.php:~87; called
  from external/sign.blade.php:~1382 & :~1959, sign.blade.php:~690).
- **Where:** a footer initials row appended to each `.corex-a4-page`, one
  `[data-marker-type="initial"][data-marker-party="<role>"]` slot per required
  signer. `_buildInitialsRow()` already exists in a4-page-styles.blade.php
  (~:340) and Strategy-2 already calls it (~:318) — Strategy-1 and the
  external/agent re-pagination paths must call it consistently too.
- **Pack-aware:** each `.corex-a4-page` is owned by exactly one
  `.corex-document-wrapper` (one per pack template). The injected initials
  MUST sit inside that owning wrapper so `SignatureService::splitMergedHtml()`
  (:~1911, splits at `.corex-document-wrapper` boundaries) keeps each page's
  initials in the correct filed document. A page that visually straddles two
  templates is attributed to the wrapper of its **first** child node
  (deterministic rule — document it in the code).
- **Party keys:** use the same canonical recipient role keys produced by
  `normalizePackMarkerParties()` (ESignWizardController) so the initial scan
  matches the signer exactly as signatures do.

### 19.3 Idempotent re-anchor

Re-pagination (content edit, zoom, font reflow) re-runs `paginateDocument()`.
Injection MUST be keyed by **page index within the owning wrapper** so it:
- never duplicates an initials row on re-run,
- never loses an already-applied initial value (re-attach captured state by
  `(wrapperIndex, pageIndex, party)`),
- removes orphaned rows when page count shrinks.

### 19.4 Interactivity (both signing views)

Injected initials become interactive for the current signer via the EXISTING
`[data-marker-type="initial"]` scan in BOTH external/sign.blade.php and
sign.blade.php (agent), including the existing **apply-to-all** affordance.
No new scanning mechanism — reuse the initial-marker handler.

### 19.5 Completion gating

The "all items complete" gate MUST add `pages × requiredSigners` initial items
to its required-count. The document MUST NOT complete with any blank page
initial. Gate count MUST be derived from the SAME paginated DOM the signer
sees (not a server estimate) so it cannot diverge from what is rendered.

### 19.6 Persistence / PDF (BUG #5 history)

Per-signer initials MUST be embedded into `merged_html` in the existing
signature/initial embed step so the Puppeteer-flattened PDF carries them.
History (§6 / BUG #5, SignaturePdfService.php:298-301): initials have been
explicitly skipped in the PDF before — the build MUST verify per-page initials
survive flatten and appear in both the internal and client PDFs.

### 19.7 Files in scope (build, when approved)

| Concern | File (approx) |
|---|---|
| Inject + re-anchor at pagination | resources/views/docuperfect/signatures/partials/a4-page-styles.blade.php |
| Interactivity + gating (external) | resources/views/docuperfect/signatures/external/sign.blade.php |
| Interactivity + gating (agent) | resources/views/docuperfect/signatures/sign.blade.php |
| Embed into merged_html for PDF | app/Http/Controllers/Docuperfect/SignatureController.php / SignatureService.php |
| PDF flatten retains initials | app/Services/Docuperfect/SignaturePdfService.php (~:298) |
| Split keeps per-page initials | app/Services/Docuperfect/SignatureService.php splitMergedHtml (~:1911) |

### 19.8 Acceptance criteria

1. Every rendered A4 page (single doc AND each pack segment) shows one initial
   slot per required signer in the footer.
2. Slots are interactive for the current signer; apply-to-all works.
3. Completion is blocked until every page initial for every signer is filled.
4. Re-pagination (add text) re-anchors with no duplicate rows and no lost
   applied initials.
5. Completed `merged_html` carries every per-page initial; the flattened PDF
   (internal + client) shows them on every page.
6. `splitMergedHtml()` output: each filed document retains exactly its own
   pages' initials (none lost, none cross-filed).
7. Single-document flow unchanged in behaviour except for the new footer row.

### 19.9 Risks (must be addressed in build)

- Re-pagination must idempotently re-anchor without duplicating or dropping
  signed initials (state keyed by wrapper+page+party).
- Puppeteer flatten has dropped initials before (§6 BUG #5) — explicit verify.
- Pack-split boundary must align with page ownership; a straddling page must
  file deterministically (first-child-wrapper rule).
- Gate page-count and PDF page-count must both derive from the same paginated
  DOM or they will disagree (gate says complete, PDF missing a page's initial).

### 19.10 Approval

- [ ] Reviewed by Johan
- [ ] Reviewed by Andre
- [ ] Committed to `main` — build may then proceed on a dev branch
- DO NOT assume a setting is "dead" without checking every file that reads it.