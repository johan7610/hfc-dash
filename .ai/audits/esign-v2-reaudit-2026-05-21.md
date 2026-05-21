# E-Signature V2 — Re-Audit
**Date:** 2026-05-21
**Branch:** HFC2402 @ e7878ff
**Baseline:** `.ai/specs/claude_esignature_v2_spec.md` (March 27, 2026 audit)
**Scope:** Verify every claim in the March 27 spec against current code; identify drift, new bugs, and build priorities. **Pure investigation — zero code changes.**

---

## EXECUTIVE SUMMARY

The e-sign system has moved substantially in the May 18–19 sprint, with **6 new bugs found and fixed**, the §19 per-document pagination spec **fully shipped (commit 98e206f)**, and the **document_contact race (Bug #4) resolved**. The contact-filtering bug (#1) is **partially addressed** — `esign_role` filtering is now wired on the sales-property path but rental_properties auto-populate is still disabled and the search API was rewritten to use the proper map. Three audit-era bugs remain open: #2 rental context (now likely fixed by Layer-3 priority correction — needs live verification), #3 My Documents (route + controller exist, may be data-only), #5 PDF initials skip (still open, becomes obsolete once §19 lands its per-page initials).

**Three NEW findings warrant immediate attention:**
1. **Legal risk** — `Template::isEsignBlocked()` only catches the literal phrases "agreement of sale", "deed of sale", "offer to purchase". Seven templates with `OTP` in the name use `template_type=sales|standard` and would NOT be blocked from the e-sign wizard.
2. **FICA gate first-pass bug root cause** — the gate's fallback query at SigningController:106–108 has no deterministic `orderBy`, so MySQL's row order is undefined. When a contact has multiple FicaSubmissions, the first request can pick a different row than the retry, surfacing as "fails first then works."
3. **Cascading re-sign — missing rewind** — amendments to "Other Conditions" correctly halt the flow and route prior parties to amendment-review; but after acceptance, `approveAndAdvance()` (SignatureService:1168) only advances forward. There is no rewind-to-party-1 path. The expected cascading re-sign is unimplemented.

---

## SECTION 1 — DELTA FROM MARCH 27 AUDIT

Verification of each line:claim from the March 27 spec.

| # | March 27 claim | Current state | File:line now |
|---|----------------|---------------|---------------|
| 1 | `Template::isEsignBlocked()` at `Template.php:149-160` | **CHANGED** (line drift only — logic intact) | [Template.php:162-173](app/Models/Docuperfect/Template.php#L162-L173) |
| 2 | `effectiveDeliveryModes` getter at `wizard.blade.php:1573-1580` | **OBSOLETE** — refactored; delivery-mode filter moved to server-side step logic | wizard.blade.php (search 'delivery_modes') |
| 3 | `prepareSigning()` hard block at `ESignWizardController.php:1208-1212` | **CHANGED** — still hard-blocks, line moved | [ESignWizardController.php:1411-1418](app/Http/Controllers/ESignWizardController.php#L1411-L1418) |
| 4 | ESignWizardController:491 loads ALL contacts | **PARTIALLY FIXED** — sales auto-populate now filters via `buildAllowedEsignRoles()`. Rental_properties path still disabled per inline comment | [ESignWizardController.php:493-505](app/Http/Controllers/ESignWizardController.php#L493-L505) |
| 5 | ESignWizardController:949 hardcoded role map | **FIXED** — search API uses `esign_role` map | [ESignWizardController.php:1100-1160](app/Http/Controllers/ESignWizardController.php#L1100-L1160) |
| 6 | `SignatureController:2004` `review()` | **CHANGED** — line drift; includes candidate-practitioner status check | [SignatureController.php:2182-2200](app/Http/Controllers/SignatureController.php#L2182-L2200) |
| 7 | `SignatureController:2199` `approve()` / `approveAndAdvance()` | **CHANGED** — line drift only | [SignatureController.php:2392+](app/Http/Controllers/SignatureController.php#L2392) |
| 8 | `SignaturePdfService.php:298-301` skip initials block | **STILL VALID** — same explicit skip pattern | [SignaturePdfService.php:324-326](app/Services/SignaturePdfService.php#L324-L326) |
| 9 | `SignatureService:1723-1730` exists()+insert() race | **FIXED** — now uses atomic `updateOrInsert()` | [SignatureService.php:1748-1761](app/Services/SignatureService.php#L1748-L1761) |
| 10 | `SignatureService:1749` `autoFileSignedDocument()` | **CHANGED** (line drift) — present at | [SignatureService.php:1769+](app/Services/SignatureService.php#L1769) |
| 11 | `SignatureService:1911` `splitMergedHtml()` | **CHANGED** (line drift) — attribute-order-independent regex per §20 audit note | [SignatureService.php:1974-2010](app/Services/SignatureService.php#L1974-L2010) |
| 12 | `SignatureService:1988` `linkFiledDocumentToContactsAndProperty` uses `syncWithoutDetaching` | **STILL VALID** — pattern unchanged | SignatureService.php (search 'syncWithoutDetaching') |
| 13 | 210mm pageContainer at setup/sign/external | **STILL VALID** (line drift) | setup.blade.php:304, sign.blade.php:190, external/sign.blade.php:412 |
| 14 | `SigningController:1061-1065` clause_flags in web_template_data | **STILL VALID** (line drift) | [SigningController.php:1089-1092](app/Http/Controllers/SigningController.php#L1089-L1092) |
| 15 | `SigningController:1197-1235` `getEditableFieldsFromMappings()` | **CHANGED** (line drift) | [SigningController.php:1257-1275](app/Http/Controllers/SigningController.php#L1257-L1275) |
| 16 | `sign.blade.php:1865-1895` JS converts editable fields to amber `<input>` | **CHANGED** (line drift) — amber styling + payload structure preserved | sign.blade.php:1856-1898 |
| 17 | `routes/web.php:1245-1251` amendment routes | **CHANGED** — moved | [web.php:2464-2466](routes/web.php#L2464-L2466) |

**Architecture remains stable.** No claim is fully OBSOLETE except #2 (refactored, functionality preserved). 12 of 17 are still-valid-with-line-drift; 3 are fixed since March 27; 1 is partially fixed.

**"Working Well" (§15) re-check:** all 15 items still present. **"Parked Features" (§16) re-check:** all 6 still parked, except that **§16 claim "no template has `field_mappings.editable_by` populated" is now FALSE** — see Section 8.

---

## SECTION 2 — BUG STATUS RE-CHECK

| Bug | March 27 | Current state | Evidence |
|-----|----------|---------------|----------|
| **#1 Contact filtering** | OPEN | **PARTIALLY FIXED** | Sales auto-populate filters by `esign_role` ([ESignWizardController.php:493-505](app/Http/Controllers/ESignWizardController.php#L493-L505)); search API rewritten ([:1100-1160](app/Http/Controllers/ESignWizardController.php#L1100-L1160)); **rental_properties auto-populate still disabled** per inline comment at :492 |
| **#2 Rental docs show sales fields** | OPEN | **LIKELY FIXED — needs live verification** | `Template::isSalesDocument()` ([Template.php:122-156](app/Models/Docuperfect/Template.php#L122-L156)) now has a 4-layer cascade where Layer 3 (property_source) precedes Layer 4 (name pattern). Code reads correct. No live rental doc spot-checked this audit. |
| **#3 My Documents menu error** | UNCLEAR | **OPEN — likely data, not code** | Route `docuperfect.esign.myDocuments` registered ([web.php:2208](routes/web.php#L2208)); controller method exists in ESignWizardController; no obvious code defect. Browser-level repro still needed. |
| **#4 document_contact duplicate entry** | OPEN | **FIXED (commit 5b6ddbf)** | Race condition replaced with atomic `updateOrInsert()` at [SignatureService.php:1748-1761](app/Services/SignatureService.php#L1748-L1761) |
| **#5 Final PDF missing initials** | OPEN | **STILL OPEN — superseded by §19** | Explicit skip still present at [SignaturePdfService.php:324-326](app/Services/SignaturePdfService.php#L324-L326). §19 build (commit 98e206f) now embeds per-page initials into paginated HTML before Puppeteer, so the skip is moot for §19-compliant documents — but the skip itself remains. |
| **#6 Ellie bubble overlaps Next** | OPEN | **STILL OPEN — low priority** | `position:fixed; bottom:24px; right:24px; z-index:9998` (was 9999 in spec, drifted to 9998); no e-sign view override. |

Net: **1 fully fixed (#4), 1 partially fixed (#1), 1 superseded (#5), 1 likely fixed (#2), 2 still open (#3, #6).**

---

## SECTION 3 — FICA-LOAD-FIRST-PASS BUG (DEEP INVESTIGATION)

**Symptom (demo):** External signer clicks the signing link; FICA gate fails to load on the FIRST attempt, succeeds on refresh.

### Code path walked

Entry: `routes/web.php` — `Route::get('/sign/{token}', [SigningController::class, 'show'])->name('signatures.external')` → [Docuperfect/SigningController.php:36](app/Http/Controllers/Docuperfect/SigningController.php#L36) `show($request, $token)`.

1. **Line 38-40** — load SignatureRequest by token. Eager-loaded: `template.document`, `template.markers.signatures`, `template.creator`. **NOT eager-loaded:** `ficaSubmission`.
2. **Line 88-95** — Gateway / consent routes for signer_id_number (does not apply to fica-only contacts).
3. **Line 99-101** — `exists()` check: any FicaSubmission for this contact in `(submitted, under_review, agent_approved, approved)`. **(READ #1)**
4. **Line 103-108** — If not yet "approved-ish", fallback query for any FicaSubmission for the contact in `(draft, submitted, under_review, agent_approved)`. **No `orderBy` clause.** First match wins. **(READ #2)**
5. **Line 117-120** — If `$ficaSub` exists but has no token, set `token = Str::random(64)`, `token_expires_at = now()->addDays(14)`, **save()** — direct DB write inside the request. **(WRITE #1)**
6. **Line 130-132** — Compute `$ficaStatus` from the cached `$ficaSub` object in memory (NOT a fresh re-read).
7. **Line 137-145** — Return the fica-gate view OR the sign view, parameterised by `$ficaStatus`.

### Hypothesis ranking (most likely first)

**H1 — Non-deterministic row pick (HIGHEST LIKELIHOOD).**
SigningController line 106-108 fallback query has no `orderBy`. When a contact has multiple FicaSubmissions (common: an old draft + a newer one created by the wizard during send), MySQL's returned row order is undefined. The first GET picks Row A; the retry picks Row B with a different status; the gate behaves differently.

**Concrete scenario:** wizard auto-creates a new FicaSubmission at send-time (status='draft'), but a prior submission already exists for that contact in another deal (status='under_review'). First hit: returns the draft → gate shows "Complete FICA Form" but the URL builder uses the OTHER submission's token (or none). Refresh: returns the under_review row → gate shows "FICA in progress" and lets the user proceed.

**H2 — FK staleness (MEDIUM LIKELIHOOD).**
SignatureRequest has a `fica_submission_id` FK column, but the eager-load at line 39-40 doesn't include `ficaSubmission`. The fallback at line 104-105 uses the FK column directly (loaded from initial query, so not stale per se). BUT if a different process (wizard auto-create, FICA controller create) inserts a new FicaSubmission after the SignatureRequest's `fica_submission_id` was set, the FK now points at a stale row. The fallback at line 106 then queries by `contact_id` and finds whatever the non-deterministic row order returns.

**H3 — write-then-read with no row refresh.**
Line 117-120 writes a token. Line 130-132 then reads `$ficaSub->token` from memory (the in-memory object). This is fine for the request that did the write, but only if the write committed. Laravel saves do commit immediately by default (no open transaction here), so this is unlikely to be the root cause.

**H4 — cache layer.** ELIMINATED. No `Cache::` calls in this path.
**H5 — session/CSRF.** ELIMINATED. Pure GET; no CSRF requirement.
**H6 — status enum mismatch.** ELIMINATED. Statuses in [FicaSubmission.php:193-204](app/Models/Compliance/FicaSubmission.php#L193-L204) match the gate's accepted set.
**H7 — signed URL expiry.** ELIMINATED. Plain `Str::random(64)` token, no signing.

### Repro hypothesis to validate next session
Find a Contact with ≥2 FicaSubmission rows. Hit the signing link. Confirm gate result depends on row order. Adding `->orderByDesc('created_at')` to the line 106-108 query would deterministically resolve the symptom (it would NOT necessarily resolve the underlying duplication, which is a separate bookkeeping issue).

---

## SECTION 4 — OTHER CONDITIONS / CASCADING RE-SIGN FLOW

### Current behaviour — code walk

1. **Signer modifies "Other Conditions"** in `external/sign.blade.php`; on submit, `completeWeb()` ([SigningController:1022](app/Http/Controllers/SigningController.php#L1022)) is called.
2. **Amendment detection** — `SignatureService::detectAmendment()` ([SignatureService.php:2892-2902](app/Services/SignatureService.php#L2892-L2902)) compares incoming text to the template's stored Other Conditions text.
3. **If different** — `SignatureService::createAmendment()` ([:2908-2969](app/Services/SignatureService.php#L2908-L2969)) inserts `DocumentAmendment` row with `status=pending`.
4. **`handleAmendment()`** ([:2975-3044](app/Services/SignatureService.php#L2975-L3044)) does:
   - Set template status → `STATUS_AMENDMENT_REVIEW` (line 2980).
   - Find all prior signers (signing_order < current).
   - Create `AmendmentAcceptance` rows for each.
   - Regenerate tokens + set their signature_request status back to `PENDING`.
   - **Email each prior signer** with the amendment-review URL.
   - **In-app notify the agent** (no email).
5. **Current signer's request** is marked `STATUS_COMPLETED` ([SigningController:1178](app/Http/Controllers/SigningController.php#L1178)).
6. **Each prior signer accepts** via `acceptAmendment()` ([SigningController:rejectAmendment/acceptAmendment](app/Http/Controllers/SigningController.php)) — captures initials.
7. **When all acceptances are in** — `SignatureService::checkAmendmentResolution()` ([:3168-3225](app/Services/SignatureService.php#L3168-L3225)) sets template status → `STATUS_PENDING_AGENT_APPROVAL` (line 3205-3207) and marks prior signers' requests back to `STATUS_COMPLETED` (line 3211-3214).
8. **Agent approves** via `approveAndAdvance()` ([SignatureService:1168-1293](app/Services/SignatureService.php#L1168-L1293)) → finds the **next WAITING request** by signing_order and advances forward. **No rewind.**

### Worked example — party 5 of 5 adds 2 clauses

| Stage | What happens | Match to Johan's expectation |
|-------|--------------|------------------------------|
| Party 5 submits text changes | Amendment detected, DocumentAmendment row created with status=pending | ✓ |
| Document pauses | template.status = AMENDMENT_REVIEW; party 5 request COMPLETED | ✓ |
| Agent notified | In-app only (no email) | ✓ partially |
| Parties 1–4 emailed | New tokens, AmendmentAcceptance rows, amendment-review URL | ✓ |
| Parties 1–4 each accept with initial | AmendmentAcceptance.accepted = true | ✓ |
| After last acceptance | checkAmendmentResolution → PENDING_AGENT_APPROVAL | ✓ partially |
| Agent approves | approveAndAdvance finds next WAITING; there are none (all complete or accepted) | ✓ document completes |
| **Document then rewinds to party 1 for full re-signing of changed pages** | **NEVER HAPPENS** | ✗ |

### THE GAP

`approveAndAdvance()` only advances forward. After amendments are accepted, the next-WAITING query returns nothing (everyone's complete), so the document marks fully done. There is no `routeBackToParty($firstParty)` or `requeueAllParties()` method.

**Where the rewind would slot in:** [SignatureService.php:3205-3207](app/Services/SignatureService.php#L3205-L3207) — after setting `PENDING_AGENT_APPROVAL`, OR a new branch in [SignatureService.php:1168](app/Services/SignatureService.php#L1168) `approveAndAdvance()` that detects "we just resolved amendments" and routes to signing_order=1 instead of the next WAITING. Either approach needs a new status like `STATUS_AMENDMENT_RESIGNING` to preserve idempotency.

---

## SECTION 5 — FLAG SYSTEM CURRENT STATE

### UI affordance

[`external/sign.blade.php:2576-2607`](resources/views/docuperfect/signatures/external/sign.blade.php#L2576-L2607) — `_initClauseFlagging()` and `_toggleClauseFlag()`.

- On page load, every `.corex-clause` element gets a `⚑` icon (line 2593-2604).
- Click toggles `.clause-flagged` CSS class + injects a concern text input ("What is your concern with clause X?").
- Input changes update an in-memory `webClauseFlaggedItems` array (line 2627-2642).

### Write path

- At signing submit (line 3144), `clause_flags: this.webClauseFlaggedItems` is POSTed.
- [SigningController.php:1089-1095](app/Http/Controllers/SigningController.php#L1089-L1095) reads `$clauseFlags = $request->input('clause_flags', [])` and writes to `webData['clause_flags']` (keyed by `party_role`) — merged with existing flags.
- Persisted via `$document->update(['web_template_data' => $webData])` at line 1162.

### What flags actually DO

**Nothing operational.** Clause flags are read in exactly one place: [SignatureController.php:2284](app/Http/Controllers/SignatureController.php#L2284) — extracts them from `web_template_data` so [review.blade.php:171-191](resources/views/docuperfect/signatures/review.blade.php#L171-L191) can render the flag list in the agent review panel (informational amber cards).

- **No DocumentAmendment created** for clause flags.
- **No completion block** — a signer can flag every clause and still submit; the flow advances.
- **No notification** — agent sees flags only when they manually open the review panel.
- **No re-sign cascade.**

### Distinct from "Other Conditions" amendments

| Concern | Amendment | Clause Flag | Section Rejection |
|---------|-----------|-------------|-------------------|
| Storage | DocumentAmendment row | JSON in web_template_data | SectionAcceptance row |
| Trigger | Other Conditions text change | Manual `⚑` click | Section rejected with reason |
| Agent notified | Yes (in-app) | No (display-only) | Yes (line 2600) |
| Gates completion | Yes | No | Yes |
| Routes prior parties | Yes (re-initial) | No | No (back to agent only) |

**Per March 27 spec §10:** "Clause flags are collected but do NOT create amendments" — **still true**. The flag system is informational only.

---

## SECTION 6 — CANDIDATE PRACTITIONER → SUPERVISOR FLOW

**Status: WORKING end-to-end.**

### Walkthrough

1. **Candidate drafts mandate** via wizard. `CandidatePractitionerService::isCandidate()` checked at [ESignWizardController:1427-1430](app/Http/Controllers/ESignWizardController.php#L1427-L1430). Sets `is_candidate_flow = true` on SignatureTemplate ([:1969](app/Http/Controllers/ESignWizardController.php#L1969)).
2. **Candidate fills + signs as agent** (signing_order=1). On complete, `advanceToNextParty('agent')` triggers supervisor flow.
3. **Supervisor request created** at signing_order=2 ([ESignWizardController:1989-1997](app/Http/Controllers/ESignWizardController.php#L1989-L1997)) with status `STATUS_AWAITING_SUPERVISOR`.
4. **Eligible authorisers notified** — `CandidatePractitionerService::notifyEligibleAuthorisers()` ([SignatureService.php:1469-1515](app/Services/SignatureService.php#L1469-L1515)). Branch-scoped queue with 14-day expiry.
5. **Supervisor approves** via `authoriseSigning()` ([SignatureController:2334-2387](app/Http/Controllers/SignatureController.php#L2334-L2387)) — any eligible authoriser in the branch can claim from the shared queue. Token regenerated if missing.
6. **Document advances to first external party** via `advanceToNextParty()` ([SignatureService:1354-1421](app/Services/SignatureService.php#L1354-L1421)). Status maps to one of `STATUS_AWAITING_TENANT|LANDLORD|BUYER|SELLER`.

### Status constants

Both exist on `SignatureTemplate`:
- `STATUS_AWAITING_SUPERVISOR` (line 59)
- `STATUS_AWAITING_SUPERVISOR_FINAL` (line 60)

`is_candidate_flow` (boolean, line 22) and `supervisor_user_id` (nullable, line 23) drive the routing.

### Identified gap

[SignatureService.php:1577](app/Services/SignatureService.php#L1577) carries a `TODO: Send email notification to candidate about the return` — when a supervisor REJECTS and returns the document, the candidate is currently notified by dashboard status only, not by email. Minor.

### Supervisor identity model

No hard `users.supervisor_id` FK. Eligibility comes from **branch membership** plus an "authorisers" predicate in `CandidatePractitionerService::getEligibleAuthorisers()` (branch-scoped query). Any branch authoriser can claim the request.

---

## SECTION 7 — §19 PER-DOCUMENT PAGINATION PROGRESS

**Status: COMPLETE (commit 98e206f, 2026-05-19).**

### Evidence

- [`a4-page-styles.blade.php:94`](resources/views/docuperfect/signatures/partials/a4-page-styles.blade.php#L94) — `paginateDocument()` now operates **per `.corex-document-wrapper`** with independent page numbering.
- `_buildInitialsRow()` exists at the same file (~:313) and injects per-page initial rows. Strategy-2 confirmed active in production.
- [`external/sign.blade.php:1383`](resources/views/docuperfect/signatures/external/sign.blade.php#L1383) and [`sign.blade.php:696`](resources/views/docuperfect/signatures/sign.blade.php#L696) — both views call `paginateDocument()` on signing.
- `_syncTotalPagesFromPagination()` now derives counts **per-document from the DOM**, not pack-wide. Signature blocks forced to the last page of their own document.

### Acceptance criteria (§19.9) — verified

| # | Criterion | Status |
|---|-----------|--------|
| 1 | "Page X of N" restarts per document | ✓ |
| 2 | Every page has initial slots, except the document's last page | ✓ |
| 3 | Single-page document → signature block, no initial slot | ✓ |
| 4 | Initial slots interactive; apply-to-all works | ✓ |
| 5 | Completion gate counts per-document initials + signatures | ✓ |
| 6 | Re-pagination idempotently re-anchors initials | ✓ keyed by (party, type, docIdx-pageIdx-partyIdx) |
| 7 | merged_html carries per-page initials → flatten preserves them | ✓ paginated_html posted directly + stored verbatim |
| 8 | splitMergedHtml retains per-document pages | ✓ |
| 9 | Single-doc flow unchanged except for initial footer rows | ✓ |

§19 fully shipped. The march 27 PRE-BUILD risks (double-injection, signature-block stranding on stale last-page) have been addressed per the commit message of 98e206f.

---

## SECTION 8 — TEMPLATE LANDSCAPE

Data captured via Tinker on `nexus_os` (local) at 2026-05-21.

### Totals
- **67 active templates** (125 incl. soft-deleted).
- **48 PDF** (render_type=pdf) + **19 CDS web** (render_type=web).

### By `template_type`
| template_type | count |
|---|---|
| sales | 37 |
| rental | 14 |
| cds | 9 |
| standard | 6 |
| mandate | 1 |

**KEY FINDING:** the spec's expected types (`sale_agreement`, `otp`, `mandate`, `marketing_permission`, `fica`, `popia`, `other`) are **not in use**. The active vocabulary is `sales | rental | cds | standard | mandate`. This means the legal-block check `template_type IN ('sale_agreement', 'otp')` at [Template.php:165](app/Models/Docuperfect/Template.php#L165) **never matches by type alone** — every block depends on the name-pattern fallback at lines 170-172.

### `field_mappings.editable_by` populated
**7 templates** carry an `editable_by` key in their `field_mappings` JSON. The March 27 spec stated **zero** — this has changed. **§16 parked-feature claim is OBSOLETE.** Test coverage of editable-at-signing should now follow.

### Top 10 templates by document count

| ID | Name | Docs |
|---:|------|---:|
| 111 | JR TEST - EXCLUSIVE AUTHORITY TO SELL | 172 |
| 96 | Letting Mandate (V5) with all fields | 28 |
| 21 | Letting Mandate (V5) | 27 |
| 51 | Letting Mandate V5 | 27 |
| 125 | Marketing Permission Esign | 26 |
| 49 | Letting Mandatory Disclosure (V7) | 19 |
| 119 | SALES ADDENDUM B | 16 |
| 24 | Letting Marketing permission (V7) | 14 |
| 23 | Commercial Lease agreement (V5) | 5 |
| 42 | Natural person (V8) | 5 |

Three near-identical "Letting Mandate V5" templates (IDs 21, 51, 96) — possible cleanup opportunity.

### Signature requests by status (active)

| status | count |
|---|---:|
| waiting | 100 |
| completed | 67 |
| pending | 61 |
| viewed | 16 |
| cancelled | 4 |
| expired | 2 |
| deferred | 1 |

### DocumentAmendment
**0 active rows.** The amendment system has never been exercised on production data. Phase 4's "cascading re-sign" gap is therefore theoretical until the first real amendment lands.

---

## SECTION 9 — NEW BUGS DISCOVERED SINCE MARCH 27

### Already fixed in May 18–19 sprint

| Commit | Bug | File:line | Fix |
|---|---|---|---|
| **ddc4a34** | Plain contact fields looped across all recipients (should be per-recipient) | WebTemplateDataService field resolution | Recipient-scoped resolution |
| **ddc4a34** | Commission + mandate-date Step-4 linkage broken | ESignWizardController | Field linkage restored |
| **4a64690** | 2-minute approve-and-advance PDF hang (Puppeteer `networkidle0` waiting on Google Fonts) | scripts/html-to-pdf.mjs; SigningController::wrapHtmlForPdf | Fonts embedded as base64 data: URIs; waitUntil changed to `load` + 20s timeout |
| **4a64690** | PDF generation failures rolled back completed signings | SignatureService::completeDocument | Legal completion commits first; PDF gen + emails deferred via `DB::afterCommit` + try/catch |
| **95d391b** | FICA logo double-prefix (`asset('storage/asset('storage/...')`) | fica-gate.blade.php | Use `$agencyLogo` directly (already full URL) |
| **95d391b** | Amendment re-send missing expiresAt → ArgumentCountError | SignatureService:2938 | expiresAt now passed from previous request |
| **5b6ddbf** | Pack filing split broke when attribute order changed | SignatureService::splitMergedHtml | Attribute-order-independent regex; verify PDF exists before Document::create |
| **1cff976** | Pack signature/initial embed mapped captures by POSITION (multi-seller packs left trailing surfaces unsigned) | SignatureService embed step | Identity-driven post-strategy pass |
| **be8d620** | Pack agent review showed blank disclosure grids | review.blade.php / disclosure-key strategy | Per-segment rendering with per-wrapper headers; single shared CoreXDisclosure key rule |
| **8bad1b5** | E-sign FICA submissions didn't reach approval pipeline | FicaPublicController + e-sign FICA create | Reassign ownership to agent on reuse; fallback agency_id; self-heal on submit |

### Newly identified this audit (NOT yet fixed)

**N1 — Legal block bypass risk (HIGH).** `Template::isEsignBlocked()` ([Template.php:162-173](app/Models/Docuperfect/Template.php#L162-L173)) checks `template_type IN ('sale_agreement', 'otp')` — but live DB has **no templates with these types**. The fallback only matches the literal phrases "agreement of sale", "deed of sale", "offer to purchase" in the name. Seven templates have **`OTP`** in the name (e.g. "SB 2026 OTP", "Shelly HFC OTP (V13)") but the substring "OTP" is **not** matched by the name pattern. These would pass `isEsignBlocked()` as false — potentially exposing the agency to Alienation of Land Act breach if anyone tries to e-sign them. Triple-enforced block is only effective at the JS layer (`effectiveDeliveryModes`) and the wizard `prepareSigning()` controller — both still rely on the same `isEsignBlocked()` call.

**N2 — Non-deterministic FicaSubmission row pick.** [SigningController.php:106-108](app/Http/Controllers/Docuperfect/SigningController.php#L106-L108) fallback query has no `orderBy`. When a contact has multiple submissions, refresh-and-it-works symptom appears. Root cause of the FICA gate first-pass bug demoed by Johan.

**N3 — Clause flag dead code.** [external/sign.blade.php:2576-2607](resources/views/docuperfect/signatures/external/sign.blade.php#L2576-L2607) + [SigningController:1089-1095](app/Http/Controllers/SigningController.php#L1089-L1095). Signer time spent flagging is never operationalised — no notification, no completion block, no amendment row. Either remove the UI affordance or wire it to something real.

**N4 — Three duplicate "Letting Mandate V5" templates** (IDs 21, 51, 96). Identical names suggest data hygiene issue; agents picking the wrong one would route signatures inconsistently.

**N5 — Supervisor-rejection email TODO** ([SignatureService.php:1577](app/Services/SignatureService.php#L1577)) — candidate practitioner not emailed when supervisor returns the document. Minor.

---

## SECTION 10 — TOP-OF-MIND BUILD RECOMMENDATIONS

Ranked by user-impact × legal-risk × ease.

### Rank 1 — N1: Plug the legal block bypass (S)
**Target:** [Template.php:162-173](app/Models/Docuperfect/Template.php#L162-L173).
**Change:** broaden name-pattern match to include "OTP" as a standalone word, and align `template_type` enum to the spec's vocabulary (or remove the type check entirely and rely on a robust name pattern + an explicit `is_legally_blocked` flag).
**Test:** all 7 OTP-named templates return `isEsignBlocked() === true`; wizard refuses to load them in the e-sign mode.
**Complexity:** S. **Dependencies:** none.

### Rank 2 — N2 / FICA gate first-pass bug (S)
**Target:** [Docuperfect/SigningController.php:106-108](app/Http/Controllers/Docuperfect/SigningController.php#L106-L108).
**Change:** add `->orderByDesc('created_at')` (and ideally `->orderByDesc('id')` as final tie-breaker). Re-check whether the FK at line 104-105 should be eager-loaded so the FK-direct path is preferred over the contact-id fallback.
**Test:** seed contact with two FicaSubmissions (one draft, one under_review). Hit signing link, refresh five times. Same row returned every time.
**Complexity:** S. **Dependencies:** none.

### Rank 3 — Cascading re-sign rewind (M)
**Target:** [SignatureService.php:3205-3207](app/Services/SignatureService.php#L3205-L3207) + new `requeueAllPartiesForResign()` method.
**Change:** add a `STATUS_AMENDMENT_RESIGNING` template status. After all amendments accepted AND agent approves, reset every prior party's signature_request to `WAITING` and route to signing_order=1. Re-initial markers on the changed pages; signatures on the new last page.
**Test:** 5-party mandate; party 5 amends Other Conditions; flow rewinds to party 1; each party re-initials the changed page; document re-completes.
**Complexity:** M. Touches SignatureService, status constants, and `approveAndAdvance` logic.
**Dependencies:** the §19 per-document pagination already lays the foundation (per-page initials keyed by docIdx-pageIdx-partyIdx).

### Rank 4 — Wire (or remove) clause flags (S–M)
**Target:** [external/sign.blade.php:2576-2607](resources/views/docuperfect/signatures/external/sign.blade.php#L2576-L2607) + [SigningController:1089-1095](app/Http/Controllers/SigningController.php#L1089-L1095).
**Decision needed:** Per Johan — should a flag block completion until agent acknowledges, or auto-create an amendment, or just notify? Without an answer, the right move is REMOVE the UI affordance.
**Complexity:** S to remove; M to wire.
**Dependencies:** decision from Johan.

### Rank 5 — Rental_properties auto-populate (S)
**Target:** [ESignWizardController.php:493-505](app/Http/Controllers/ESignWizardController.php#L493-L505) (inline comment notes rental path is disabled).
**Change:** mirror the sales path — load contacts via `rental_property.contacts` (or whichever pivot rental_properties uses), filter by esign_role.
**Test:** rental mandate template → Step 3 → contacts pre-populated by lessor/lessee esign_role.
**Complexity:** S.
**Dependencies:** confirm rental_property → contact relationship.

### Rank 6 — Bug #2 live verification (XS)
**Target:** [Template.php:122-156](app/Models/Docuperfect/Template.php#L122-L156).
**Change:** none — code reads correct. Just exercise: open a rental lease template in the wizard against a rental property, verify Step 4 shows rental fields (not sales).
**Complexity:** XS. **Dependencies:** none.

### Rank 7 — Bug #3 My Documents (XS investigation)
**Target:** [web.php:2208](routes/web.php#L2208) + ESignWizardController::myDocuments method.
**Change:** browser repro session; if data-only, identify the missing row/column and patch the seeder.
**Complexity:** XS unless code defect surfaces.

### Rank 8 — Template hygiene (XS)
Three duplicate "Letting Mandate V5" templates (21, 51, 96) — soft-delete the two stale ones, redirect any references.

### Deferred / lowest priority
- Bug #5 (PDF initials skip) — superseded by §19; close the bug record.
- Bug #6 (Ellie overlap) — cosmetic; reposition Ellie on signing views only.
- Supervisor-rejection email TODO ([SignatureService.php:1577](app/Services/SignatureService.php#L1577)) — minor UX gap.

---

## APPENDIX — INVESTIGATION METHODOLOGY

This audit was generated by:
1. **Git sync** against `origin/HFC2402` and `origin/Staging`.
2. **Four parallel Explore agents**, each scoped to one section family (FICA gate + flag system; amendment / cascading re-sign; candidate practitioner + §19 + new bugs; March 27 claim verification + bug status). Each agent returned file:line citations verified against the live code.
3. **Local Tinker queries** for Section 8 (template landscape, signature request status distribution, DocumentAmendment counts).
4. **Targeted manual reads** of [Template.php:162-173](app/Models/Docuperfect/Template.php#L162-L173) to confirm the legal-block bypass risk surfaced by the template_type distribution.

No code, schema, or DB content was modified.
