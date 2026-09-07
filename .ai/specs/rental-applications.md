# Spec: Rental Applications (AT-392)

**Status:** Phase 1 approved by Johan 2026-09-04. This spec covers Phase 1 only.
**Ticket:** AT-392 — Rental Applications lane.

---

## Why

The Rental Application (V8) document was being sent through the full e-sign
wizard, which structurally requires the agent to be a signing party
(`ESignWizardController` auto-injects and locks an `agent` recipient row —
see AT-332 investigation). For a tenant intake form this is overkill: the
agent never needs to sign a rental application, and the wizard's multi-step
flow (property → recipients → details → fill & review → signing setup →
prepare-signing) is far more machinery than "pick a contact, send a form."

Phase 1 replaces that path with a dedicated page for this one document type.

---

## Phase 1 scope — what this build includes

1. **Dedicated page**, not the e-sign wizard. Contact **required**, property
   **optional**. Every field **optional** — nothing blocks send. Prefill
   from the contact wherever a field maps to a real `contacts` column.
2. **Fields** (from Rental Application V8):
   - Property address
   - Personal: full name, ID number, marital status, spouse name, spouse ID,
     citizenship, current residential address, email, cell, work
   - Emergency contact: name, cell, work
   - Current landlord: name, tel, current rental amount, from, to
   - Employment: employer, position, employer address, employer tel,
     monthly salary
   - Lease requirement: occupation date, rental terms, special conditions,
     adults, children
3. **Agent does not sign.** The applicant signs twice in one sitting: the
   truth-of-information declaration, and the Tenant Profile Network (TPN)
   credit-check consent.
4. **One send, two return routes**, applicant's choice:
   - (a) Download the PDF, complete by hand, scan, return — with an upload
     link so the scanned/completed copy and supporting documents come back
     into CoreX rather than an inbox.
   - (b) Tokenised link — complete online, sign both blocks, upload
     supporting documents, data lands directly against the contact.
   - (b) is modelled on the existing `/sign/{token}` mechanism
     (`SignatureRequest`/`SigningController` pattern) — reused, not
     reinvented: same token generation shape (`Str::random(64)`, uniqueness
     loop), same 14-day expiry convention, same "blocked/expired" no-identity
     -leak handling shape.
5. **Supporting documents.** Reuses the existing e-sign recipient
   supporting-document upload path's contract (allowlist
   `pdf,jpg,jpeg,png,doc,docx`, 15MB/file, max 10 files/request — see
   `SigningController::uploadSupportingDocuments()`). Required-document list
   is agency-configurable, defaulting to the V8 checklist per employment
   type (permanently employed / business owner — personal account /
   business owner — business account). **Nothing is enforced** — missing
   documents show as outstanding on the Returned Applications screen; they
   never block submission.
6. **Returned Applications** — its own menu item for the rental team.
   Status values: `sent`, `in_progress`, `returned`, `under_assessment`,
   `approved`, `declined`, `withdrawn`.
7. **Branding.** No literals. The live template (`template-97.blade.php`)
   hardcodes `letting@hfcoastal.co.za` (`:58`) and `039 315 0857` (`:77`).
   The new build reads agency email/phone/website via the same `$d()`
   accessor pattern already used in
   `docuperfect/web-templates/components/company-header.blade.php`
   (`agencies.email`, `agencies.phone` / `phone_secondary`,
   `agencies.website_url`).
8. **Navigation** (standing rule — every page ships with its nav entry the
   same day): Rentals → Rental Applications. Rentals → Returned
   Applications. Settings → Rental Applications.
9. **Soft deletes only** (`SoftDeletes`), **agency-scoped**
   (`BelongsToAgency` + global `AgencyScope`) — no exceptions.

## Explicitly OUT of Phase 1 (later phases, per Johan)

- Assessment split-screen with agency-configurable affordability calculator
  and approval routing.
- Applicant highlighting and OCR keyword marking on returned documents.

---

## Pillars

- **Contact** — required. The application is filed against a contact; the
  contact's own fields (name, ID number, email, phone, address) prefill the
  form and the PDF's Document ↔ Contact link (`document_contacts` /
  `documents.agency_id`) is how it appears on the contact record.
- **Property** — optional. When set, links via `documents.property_id` /
  `document_properties`, same convention as every other filed document.
- **Deal** — not required for Phase 1 (a rental application precedes any
  deal; nothing here creates or requires a `deals` row).
- **Agent** — the creating/sending user; recorded, never a signer.

---

## Data model

### `rental_applications`

One row per sent application. `agency_id` (BelongsToAgency), `branch_id`,
`contact_id` (NOT NULL — the one required link), `property_id` (nullable),
`created_by_user_id`, `status` (enum, see §6 above, default `sent`),
`token`, `token_expires_at`, `delivery_mode` (`download` | `online`,
nullable until the applicant picks one), plus one nullable column per V8
field listed in §2. `submitted_at`, soft deletes.

**Every V8 field column is nullable.** Per BUILD_STANDARD §2 (the
input-space rule), an empty optional field must never reach the DB and
error — nullable columns are the DB-layer half of that guarantee.

### `rental_application_signatures`

Two rows per completed application (`kind`: `declaration` | `tpn_consent`),
each with the captured signature image path, signed-at timestamp, and the
IP/user-agent of the signer — mirrors the audit shape already used for
e-sign (`SignatureAuditLog`), scoped down to what a two-signature intake
form needs.

### `rental_application_document_requirements`

Agency-configurable checklist. `agency_id`, `employment_type` (enum:
`permanently_employed`, `business_owner_personal_account`,
`business_owner_business_account`), `document_type_id` (FK to the existing,
shared `document_types` table — no parallel type system), `sort_order`.
**No agency row present ⇒ the V8 defaults apply in memory** (Rule 17-safe
pattern: never persists a hardcoded default, just returns it when nothing
is configured), so an agency that never opens the settings screen still
gets the correct, working default checklist.

### Documents

Supporting documents and the returned/scanned copy file through the
**existing, shared `documents` table** (`source_type = 'rental_application'`,
`source_id = rental_applications.id`, `agency_id` stamped from the
application) — never a parallel documents table. Contact/property linkage
uses the same pivots (`document_contacts`, `document_properties`) every
other filed document uses.

---

## Routes (Phase 1)

| Purpose | Route |
|---|---|
| List / create | `GET/POST corex/rental-applications` |
| Show / edit (pre-send) | `GET corex/rental-applications/{rentalApplication}` |
| Send | `POST corex/rental-applications/{rentalApplication}/send` |
| Returned Applications inbox | `GET corex/rental-applications/returned` |
| Settings — document checklist | `GET/POST corex/settings/rental-applications` |
| Public token entry | `GET rental-application/{token}` |
| Public submit | `POST rental-application/{token}/submit` |
| Public supporting-doc upload | `POST rental-application/{token}/documents` |
| Public supporting-doc view | `GET rental-application/{token}/documents/{document}` |
| Public supporting-doc remove (archive) | `POST rental-application/{token}/documents/{document}/remove` |
| Public supporting-doc replace | `POST rental-application/{token}/documents/{document}/replace` |
| PDF download (agent side, any time) | `GET corex/rental-applications/{rentalApplication}/pdf` |

---

## Permissions

- `rental_applications.view` / `rental_applications.create` /
  `rental_applications.send` — the intake page and list.
- `rental_applications.view_returned` — the Returned Applications inbox
  (rental team).
- `rental_applications.manage_settings` — the document-checklist settings
  screen.

---

## Acceptance criteria (Phase 1)

- An agent can open Rental Applications, pick a contact (required),
  optionally a property, leave every other field blank, and send — no
  validation error.
- The applicant can either download a correctly-branded PDF (agency's own
  email/phone/website, not HFC literals) or open a tokenised link.
- On the tokenised link, the applicant can fill any subset of fields,
  capture both signatures (declaration + TPN consent), upload supporting
  documents, and submit in one sitting.
- The submission is visible on the contact record and in Returned
  Applications with the correct status.
- Missing checklist documents are shown as outstanding, never block
  submission.
- Deleting an application soft-deletes it; nothing is hard-deleted.
- A second agency's data is never visible to this agency, in the list, the
  inbox, or the settings screen.

---

## Deploy requirements (mandatory, every environment — do not skip)

**1. Run the permission sync after every deploy that lands this feature.**
`config/corex-permissions.php` defines 4 `rental_applications.*` keys, but
they only take effect once granted to roles in `role_permissions`. This is
NOT automatic on `migrate` — the sync command must run explicitly:

```
php artisan corex:sync-permissions --merge-defaults
```

Root-caused on QA1 2026-09-07 (cc2): after this feature landed, `role_permissions`
had **zero** grant rows for any of the 4 keys, on any role, for any agency —
`hasPermission('rental_applications.view')` evaluated false for every user,
including admin, and the nav entry stayed invisible. Confirmed fixed on QA1
by running the sync (210 rows inserted, admin role only, every agency).
**Note when running this:** the command processes every permission key
missing from the DB, not just this feature's — on QA1 it also caught up one
unrelated pre-existing key (`view_photo_upload_report`, from an older
diagnostics feature that had never been synced either). That is expected
behaviour of `--merge-defaults`, not a side effect of this feature — verify
the diff on each run rather than assuming it only touches
`rental_applications.*`.

**2. RESOLVED 2026-09-07 — the nav entry was inside the owner-only "Hidden"
panel; it now has its own agency-visible main-menu section.** Originally
found nested inside the sidebar's `$isOwner`-gated "Hidden" section (via
the pre-existing "Rentals" drill-down there) — a normal per-agency admin,
including Johan's own regular login, could not see it no matter what
permissions they held. See "Rentals main-menu section" below for the full
build, verification, and the known naming collision this created.

---

## Rentals main-menu section (Johan's decision, 2026-09-07)

Johan: Rental Applications is an agency admin/agent feature, not a
system-owner tool. Built as a proper top-level "Rentals" main-menu section
in `resources/views/layouts/corex-sidebar.blade.php` — the container for a
**growing** section, not a one-off link. Rental Applications is only the
first child.

**Structure.** Matches the existing "Reports"/"Leave Management" pattern
exactly — same `corex-nav-item corex-nav-group-toggle` / `corex-nav-panel`
/ push-pop Alpine markup as every other top-level drill-down group in the
file. Placed between the "Reports" and "Trust Interest" groups (a natural,
low-disruption boundary — both neighbours are themselves standalone,
independently-gated groups, same shape as this one).

**Internal Alpine group key is `rental-applications`, NOT `rentals`.**
Deliberately distinct from the existing Hidden panel's `rentals` key (see
below) — two groups sharing one key would corrupt each other's
open/active panel-stack state via `$groupOpen()`/`$navGroupParents`. This
is invisible to the user; only the visible label is "Rentals."

**Section-level gate:** `@if($user && $user->hasAnyPermission(['rental_applications.view', 'rental_applications.view_returned']))`
— no `@feature()` wrap. Two reasons: (1) there is no dedicated
feature-registry entry for `rental_applications` in
`config/corex-features.php`, and adding one would be scope creep on a
nav-only change; (2) this exactly mirrors the "Reports" group immediately
above it in the file, which is also permission-only for the same reason
(its own comment: *"Gated by its own existing permission... not a new
key"*). An agency with neither child permission sees no "Rentals" menu at
all — not an empty shell.

**Adding the next rentals feature:** one more
`@permission(...)`/`<a>`/`@endpermission` block inside the panel `<div>`,
directly after Returned Applications, same shape as the two existing
children. Documented inline in the blade file's own comment at the same
spot.

### Known, deliberate, pending reconciliation — two things named "Rentals"

**Not resolved, not ours to resolve.** A second, unrelated "Rentals"
drill-down still exists, nested inside the sidebar's owner-only "Hidden"
section (`$isOwner` gate) — lease capture, dashboard, e-signatures, active/
expired leases. `config/corex-features.php`'s `'rentals'` feature key
(`sidebar_section: 'hidden.rentals'`) belongs to that panel, not to this
one. **Both are untouched by this build** — this section only adds the new
agency-visible "Rentals" menu; the Hidden one is exactly as it was.

Pre-build investigation (reported to Johan before any code was written,
2026-09-07) established the Hidden panel is real, working, and — on the
evidence — deliberately hidden rather than abandoned:
- All 5 of its links resolve (`rentals.index`, `rental.dashboard`,
  `rental.signatures`, `rental.active-leases`, `rental.expired-leases`),
  backed by real controllers (`RentalsController`, `Rental\RentalDivisionController`)
  with substantive query logic, not stubs.
- All 5 views exist and are substantial (97–1,116 lines).
- Real production-shaped data exists on QA1 (58 `Rental` rows, 2
  `LeaseRecord` rows).
- Dispatching a real authenticated request to all 5 routes returns 200.
- 28 commits have touched this code, most recently in a 239-page app-wide
  style sweep (July 2026) — still actively carried along by maintenance,
  not forgotten.
- The section's own comment is explicit: *"HIDDEN — pages hidden from
  agency users, visible to system owners only."*

**What a system owner actually sees, right now, with both sections live**
(rendered and verified, not inferred): a "Rentals" toggle in the normal,
agency-visible part of the menu (this build) that expands to "Rental
Applications" / "Returned Applications" — **and**, separately, further
down inside "Hidden," a second "Rentals" toggle that expands to a panel
also titled "Rentals," which itself contains a link whose visible text is
also just "Rentals" (the `rentals.index` link, labelled identically to its
own parent group and panel). Three identical "Rentals" labels total, in
two unrelated places, for the one role that can see both.

**Assessment:** this does not fail or crash — both work independently and
correctly (confirmed via the distinct Alpine keys) — but it reads as
confusing or duplicated to a human looking at the sidebar, not as an
intentional design. An owner encountering both without this context would
reasonably wonder why "Rentals" appears twice. Flagged for Johan; nothing
renamed or merged without his explicit call.

### Verification (rendered output, real kernel dispatch — not blade source)

- **User 22** (johan@hfcoastal.co.za, admin, agency 1): "Rentals" section
  present and expandable (`push('rental-applications')` fires once), both
  children present with correct hrefs (`corex/rental-applications`,
  `corex/rental-applications/returned`).
- **User 24** (agent, no `rental_applications.*` permissions): the entire
  section — button, panel, both children — is absent. Zero occurrences of
  "rental-applications" anywhere in the rendered page.
- **Nothing else broke.** Compared three rendered states (original Hidden
  placement → intermediate Tools placement → this build) for the same
  user: every major nav section marker (Agents, Branch Manager, Tools,
  Admin, System Developer, Dashboard, Properties, Deeds, Compliance,
  Payroll, Reports, Trust Interest) has an identical count across all
  three. A full span-by-span diff between the immediately-prior state and
  this one shows only the expected structural delta (`Rentals` +
  `Back` labels added for the new panel chrome) — no other label moved,
  vanished, or duplicated.
- `php -l` and a full `Blade::compileString()` compile check: both clean.
- `dev-check.ps1` **cannot run on this box** — `pwsh` is not installed on
  this Linux QA host. Substituted `tests/Feature/Features/FeatureNavGuardCoverageTest.php`
  (the structural test that reads the sidebar file directly and checks
  every feature-registry entry against its actual nav guard) — **PASS**.

---

## Applicant-facing fixes (Johan, 2026-09-07 — "no submit application button")

Six real defects on the public token-based side, all found by rendering the
real page for a real token (not inferred) and, for the first one, a
headless-browser screenshot — not just reading the HTML.

1. **The submit button was invisible, not absent.** Root cause: the button
   used the raw Tailwind arbitrary-value class `bg-[#0b2a4a]`. Tailwind's
   JIT compiler only emits a utility class if it can see it in a scanned
   file AT BUILD TIME — the compiled CSS bundle on this box predates this
   blade file by ~2 weeks, so the class compiled to nothing. Confirmed with
   a real headless-browser render: the button had correct dimensions, was
   Alpine-hydrated, zero console errors — but rendered as white text on an
   unstyled background against the white card behind it. Fixed with an
   inline `style="background: var(--brand-default, #0b2a4a);"`, the same
   var()-with-fallback pattern UI_DESIGN_SYSTEM.md already requires — this
   can never depend on a Tailwind rebuild, so it can't regress the same way.
2. **Validation-failure redirects silently discarded the applicant's typed
   answers.** `<x-rental-application-field>` and three raw textareas/one
   select read straight from `$application` (the DB row), never `old()`.
   Laravel's own validate() failure already flashes old input to the
   session correctly — the views just never read it back. Fixed in the
   component (fixes every field that uses it in one place) and the four
   raw fields individually. No error messages were shown anywhere either —
   added a summary banner plus a per-field `@error()` message.
3. **No agent notification existed at all** on a successful submission.
   Added `RentalApplicationNotifier` + `RentalApplicationReturnedMail`
   (new files, isolated from the agent-side lane's own
   `RentalApplicationMailer`), fired from `submit()` after the DB
   transaction commits (a mail failure must never roll back the
   applicant's already-saved submission). Verified via Mailpit's real HTTP
   API (`127.0.0.1:8025`) — the notification actually arrived, addressed
   to the sending agent, nothing sent to a real address (MAIL_HOST is
   Mailpit's own local listener, port 1025 — structurally cannot escape).
4. **`submit()`'s writes were not transactional.** The record save and both
   signature captures could partially land (e.g. a disk failure between
   the two signature writes) leaving `status='returned'` with only one
   signature saved — an inconsistent state with no way back. Wrapped in
   `DB::transaction()`.
5. **Supporting-document upload was silently unavailable after
   submission.** `uploadDocuments()` never blocked on status (matching the
   spec's "before OR after signing" design, mirroring the e-sign
   precedent) — but `already-submitted.blade.php` never rendered the
   upload form at all, cutting off a path the backend already supported.
   Added the same upload section already-submitted.blade.php was missing.
6. **A rejected upload (wrong file type, too large) showed neither success
   nor error** — confirmed by deliberately uploading a non-PDF file and
   getting a silent, message-free redirect. `$errors` was populated but
   nothing on either page ever displayed `supporting_files` /
   `supporting_files.*` errors. Added per those keys to both upload
   sections.

**Verified end-to-end via real HTTP requests** (curl, cookie jar, real
CSRF, against the actual live QA1 URL) against real application id=1:
malformed email → redirected back with "Test Applicant XYZ" preserved and
the exact field error shown; valid submission → DB row shows every field
persisted, both signature files genuinely exist on disk
(`Storage::exists()` confirmed); re-opening the token shows "Application
already received" with no submit form present, and a direct POST replay
with a valid CSRF for the same session was refused by `submit()`'s own
status guard (DB row and signature count unchanged, confirmed after);
document upload confirmed with a real PDF — file exists on disk, `documents`
row correctly linked to the contact; the agent-notification email was
captured by Mailpit with the correct subject and recipient.

**Also removed**: a dead, unreachable status-check branch in
`show.blade.php` — `RentalApplicationSigningController::show()` already
routes any `returned`-or-later status to the separate
`already-submitted.blade.php` view before this template is ever reached,
so the branch could never fire.

---

## Applicant-side document CRUD & access control (Johan, 2026-09-07 — "proper CRUD" standard)

Full CRUD for the applicant's own supporting documents, applied to the
public token-based side per the box-wide standard: "we always need proper
CRUD... own / branch / agency levels... design and build correctly from
the word go." List-screen requirements (search/sort/filter/pagination)
do not apply here — there is no list screen on the public side, only an
individual applicant's own small document set.

### Access control mechanism

A `documents.id` is a globally auto-incrementing key on a table shared
across every agency, every module, and every application — knowing or
guessing an id proves nothing. Every document action on the public route
(`view`, `remove`, `replace`) re-derives the application from the URL
**token** first, then independently verifies the target document's
`source_type === 'rental_application' AND source_id === $application->id`
before permitting any read or write
(`RentalApplicationSigningController::scopedDocument()`). A mismatch
returns **404, never 403** — a 403 would confirm "this id exists but is
off-limits," leaking information a 404 does not.

**Verified with real HTTP requests, not asserted:**
- Fetching Application A's document using Application B's token → 404.
- Fetching a raw/guessed document id (999999) using a valid but unrelated
  token → 404.
- Removing Application A's document using Application B's token → blocked;
  confirmed via DB that the document's `deleted_at` stayed `NULL` — the
  blocked attempt did not silently soft-delete anything anyway.
- A completely invalid/guessed token → 404 (`firstOrFail()` in
  `findByToken()`), same as any other unknown resource.
- The identical action with the document's own correct token → 200, real
  file returned. Confirms the control is a real boundary, not a
  blanket failure that would also break the legitimate path.

### Token expiry

`token_expires_at` is checked independently on every route that touches
an application — `show`, `submit`, `uploadDocuments`, `viewDocument`,
`removeDocument`, `replaceDocument` — not only on the entry page. Verified
with a genuinely expired test application (`token_expires_at` in the
past): the entry page renders the "This link has expired" view with no
form; `viewDocument` on a document that existed before expiry returns 404;
`uploadDocuments`, `submit`, `removeDocument`, and `replaceDocument` all
redirect back with "This link has expired" and perform no write (confirmed
via DB — the application's `status`/`submitted_at` and the document's
`deleted_at` were unchanged after the blocked attempts).

### Full CRUD, including after submission

The applicant can **view, replace, and remove (archive)** their own
documents, not just create — "create-and-submit-only is not finished."
`replaceDocument()` performs the swap atomically inside a `DB::transaction`:
the new document is filed and the old one archived together, or neither
happens.

Document actions remain available **after the application is submitted**
(`status = 'returned'`), matching the pre-existing spec design that
supporting documents are uploadable "both before signing and after
signing" (§ Documents). Only the *main application form* is blocked once
submitted (`submit()`'s own status guard, unchanged) — re-reading Johan's
"an expired/revoked/already-submitted token cannot be used to read or
write anything" as applying to the application record itself, not to the
already-approved post-submission document flow it would otherwise
contradict. **Flagged for confirmation, not assumed**: if document actions
should also lock once `status = 'returned'`, that is a one-line status
check to add to `scopedDocument()` or each action — not done here pending
Johan's call.

Verified end-to-end against a real, already-submitted application: opened
the "Application already received" page with its own valid token,
replaced its one existing document — old document's `deleted_at` set
(soft delete only, file remains on disk), new document created in the
same transaction (identical timestamp), `documents()` relationship count
correctly drops the old doc; then removed the new document — `deleted_at`
set, file still exists on disk, `documents()` relationship count now `0`,
and re-fetching the removed document's own view URL correctly 404s.

### No hard deletes

`removeDocument()` and the old-document side of `replaceDocument()` both
call `Document::delete()` — `Document` already uses `SoftDeletes`, so this
is always a soft delete; no `forceDelete()` anywhere in this code path.
Confirmed by DB inspection after every remove/replace test above: the row
persists with `deleted_at` set, the file remains on disk, and the record
would be recoverable exactly as with any other soft-deleted document.

### Agent-side visibility

No change needed on the agent side (confirmed with cc3, agent-side lane
owner): the agent's document listing already reads through the standard
`documents()` Eloquent relationship, which respects `SoftDeletes`
automatically — an applicant's replace/remove is reflected there with no
additional work.

### Routes added

`GET /rental-application/{token}/documents/{document}` (`throttle:30,1`),
`POST /rental-application/{token}/documents/{document}/remove`
(`throttle:10,1`), `POST /rental-application/{token}/documents/{document}/replace`
(`throttle:10,1`) — same throttle convention already used by
`/sign/{token}`.

---

## Agent-side hardening (2026-09-07)

Johan tested the applicant side himself and hit a blocker within minutes;
the agent side was assumed equally under-tested and every screen was
re-rendered through the kernel as a real authenticated user (not just
grepped) to check. It was — four real gaps found and fixed, none of which
had any test coverage before this pass:

1. **`searchProperties()` returned every listing type.** A rental
   application's property picker showed for-sale, commercial, and vacant
   land listings alongside rentals. Fixed with a `listing_type = 'rental'`
   filter — `RentalApplicationController::searchProperties()`.
2. **`show.blade.php` rendered only ~40% of the V8 field list.** Missing
   entirely: `property_address_override`, `current_residential_address`,
   the whole Emergency Contact block, the whole Current Landlord block,
   `employer_address`/`employer_tel`, and the whole Requirement of Lease
   block (`occupation_date`, `rental_terms`, `special_conditions`,
   `adults`, `children`). An agent opening a submitted application could
   not see or edit most of what the applicant actually submitted, even
   though the PDF template (`corex/rental-applications/pdf.blade.php`)
   already rendered every field correctly — the two views had silently
   diverged. Fixed by adding the missing sections to the edit form,
   mirroring the PDF's own field list exactly. Three of the added fields
   (`current_residential_address`, `employer_address`,
   `special_conditions`, all `max:2000` per the shared validation rules)
   use a plain inline `<textarea>` rather than the shared
   `x-rental-application-field` component — that component is also used by
   the public applicant view and was being actively edited by the
   applicant-side lane at the same time, so it was left untouched rather
   than risk a collision.
3. **No document-download route existed on the agent side.** Supporting
   documents were listed by filename only, with no way to open one. Added
   `GET corex/rental-applications/{rentalApplication}/documents/{document}`
   → `RentalApplicationController::downloadDocument()`. Both route
   parameters are agency-scoped by their own model's `BelongsToAgency`
   global scope (a cross-agency id 404s at route-model-binding, before the
   method body ever runs); the method additionally checks
   `source_type`/`source_id` match as defense-in-depth against a
   same-agency agent guessing a document id belonging to a different
   application.
4. **No archive/delete route existed.** `RentalApplication` already had
   `SoftDeletes`; the destroy action was simply never wired up. Added
   `DELETE corex/rental-applications/{rentalApplication}` →
   `RentalApplicationController::destroy()`, gated on
   `rental_applications.create` (the spec defines no separate delete
   permission), with a confirm dialog on the Archive button per
   STANDARDS.md.

Also verified and confirmed already correct (no changes needed): contact
prefill on create, send + Mailpit-only delivery, agency isolation at both
the route-model-binding and raw-query level, the settings-screen
save/reload round-trip, and PDF generation (`RentalApplicationPdfService`,
shared with the applicant-side `pdf()` route) — a real PDF was generated
and opened, containing the applicant's actual submitted field values,
correct agency branding (no hardcoded HFC literals), and rendering
uploaded signature images correctly.

New test coverage: `tests/Feature/RentalApplications/RentalApplicationAgentControllerTest.php`
— none existed for this controller before this pass.

---

## CRUD / search / sort / scoping standard (Johan, 2026-09-07 — permanent, applies from the word go)

Johan, verbatim, mid-build: "we always need proper crud? search / sort /
own / branch / agency levels. that should be the design standard. not me
asking for it once we get to that stage." This is a correctness/security
requirement, not a setting — there is no toggle, it just works. Applied
immediately to the agent side of this module rather than deferred; the
same standard is being written into BUILD_STANDARD.md/STANDARDS.md/CLAUDE.md
project-wide (a separate lane's work, not this one's).

**Full CRUD.** Create (`store`), read (`index`/`returned`/`show`), update
(`update`), archive (`destroy` — soft delete only, `RentalApplication`
already had `SoftDeletes`), and restore (`restore`, new). A route-model-
bound `{rentalApplication}` 404s on a soft-deleted row by default, so
`restore()` explicitly binds `RentalApplication::withTrashed()->findOrFail()`
— the only action in the controller that needs to.

**Search** (both `index` and `returned`, `?q=`): matches the application
id itself (an agent quoting "#42"), `property_address_override`, the
linked contact's `first_name`/`last_name`/`email`, and the linked
property's `address`/`title`. Both list screens show a real empty state
("No rental applications match this search" / "No returned applications
match this filter") when a search/filter combination matches nothing,
distinct from the true-empty-table state.

**Sort** (`?sort=&direction=`): `contact` (joins `contacts.last_name`),
`property` (joins `properties.address`), `status`, `date` (`created_at`
on `index`, `submitted_at` on `returned`). Default: `date desc` (newest
first) on both screens, unchanged from before this standard, now explicit
and user-controllable via clickable column headers. **`status` is a MySQL
`enum`** (`RentalApplication::STATUSES`) — sorting by it orders by
*declared* index (`sent` → `in_progress` → `returned` → ...), i.e. rough
workflow order, never alphabetically. This is intentional/more useful
than alphabetical for a status column, not a defect — documented here so
a future reader doesn't "fix" it into alphabetical order.

**Date range** (`?date_from=&date_to=`): filters the same date column
sorting defaults to (`created_at` / `submitted_at`).

**Pagination**: 25 per page on every list (`index`, `returned`, and the
new archived sub-list), `->withQueryString()` so search/sort/filter
survive pagination.

**Own / branch / agency scoping — enforced at the query layer, on every
list, detail view, PDF, and document download, never by hiding a link:**

- `RentalApplication::scopeVisibleTo($query, $user)` (new model method) —
  the list-query guard, applied in `index()`, `returned()`, and the
  archived sub-query. Mirrors `Docuperfect\Document::scopeVisibleTo()`
  EXACTLY: `PermissionService::getDataScope($user, 'rental_applications')`
  resolves to `'own'` (→ `created_by_user_id` — the creating agent, this
  module's equivalent of Document's `owner_id`), `'branch'` (→
  `branch_id`), or `'all'` (agency-wide — the tenant boundary itself is
  still `BelongsToAgency`'s own global scope underneath this).
- `AuthorizesRentalApplicationAccess::guardRentalApplication()` (new
  trait, `app/Http/Controllers/Concerns/`) — the single-record sibling,
  called at the top of `show()`, `update()`, `send()`, `pdf()`,
  `destroy()`, `restore()`, and `downloadDocument()`. Mirrors
  `AuthorizesDocumentAccess::guardDocument()` exactly, so list and
  single-record access can never disagree.
- This is the SAME mechanism the Documents module already uses — wiring
  an existing, established pattern into a new module, not new
  architecture.
- Proven with real requests in
  `tests/Feature/RentalApplications/RentalApplicationCrudStandardTest.php`,
  not asserted: an `agent`-role user (own scope) sees only applications
  they created and gets a real 403 opening another agent's; a
  `branch_manager` (branch scope) sees only their branch's; an `admin`
  (agency/all scope) sees every branch in the agency; a different
  agency's admin gets a real 404 on both `show` and `pdf` by direct URL
  (route-model-binding never resolves a cross-agency id at all, before
  any scope check runs).
### Role defaults (Johan, 2026-09-07 — resolves the gap this section used to describe)

**Previously a real gap, now fixed on QA1:** `corex:sync-permissions
--merge-defaults` had only ever granted the 4 `rental_applications.*` keys
to the `admin` role (cc2's 2026-09-07 fix only covered admin) — a genuine
front-line agent account could not open this feature at all. Johan: "he
moved this feature into the normal agency-visible menu precisely because
agents are the people who will use it. A rental application that only an
admin can open is not a working feature."

`config/corex-permissions.php`'s `role_defaults` now grants, matching the
house pattern (sanity-checked against `documents.view`/`documents.create`
and `rentals.view`/`rentals.create`, which use the identical shared-key-
across-roles approach — breadth is enforced by scope, not by giving each
role a differently-named key):

| Key | admin | branch_manager | agent |
|---|---|---|---|
| `rental_applications.view` | ✓ (via all-minus-exclude) | ✓ | ✓ |
| `rental_applications.create` | ✓ | ✓ | ✓ |
| `rental_applications.view_returned` | ✓ | ✓ | ✓ |
| `rental_applications.manage_settings` | ✓ | — | — |

`manage_settings` is deliberately admin-only, matching the house pattern
for `manage_*`/`*.configure`-shaped keys elsewhere (`manage_finance_definitions`,
`compliance.whistleblow.configure`, `outreach_templates.manage`) — narrower
than the `view`/`create` tier. `admin` needed no explicit `role_defaults`
edit at all: it already gets every permission via the all-minus-exclude
pattern, which is why cc2's original sync granted it automatically.

**Deployed to QA1 on 2026-09-07** via `corex:sync-permissions --merge-defaults`
(role_permissions backed up first to `/root/db-backups/` — safe, additive,
existing customisations untouched). Before: 55,040 total rows, 172
`rental_applications.*` rows (admin only, 43 agencies). After: 55,280 total
rows, 412 `rental_applications.*` rows — exactly 240 new rows
(`branch_manager` +3 keys × 40 agencies, `agent` +3 keys × 40 agencies),
verified by direct query that **every single new row's key starts with
`rental_applications.`** — nothing outside this module was touched by this
run. `rental_applications.view`'s `scope` column resolved correctly per
`scope_defaults` (admin→`all`, branch_manager→`branch`, agent→`own`); the
other three keys correctly carry no scope (they gate route access only,
`getDataScope()` only ever reads the `.view` key regardless of which
action-permission unlocked the route).

**Re-verified with real QA1 accounts, not synthetic test users, after the
grant** (Retha Kelly `agent`/branch 1, Shawn Du Bois `agent`/branch 1,
Jenny Joubert `agent`/branch 2, Falan Du Bois `branch_manager`/branch 1,
Sandra Mante `admin`/agency 90) — actual HTTP status codes:

| Actor | Target | Route | Status |
|---|---|---|---|
| Agent (own) | Own application | index/show/pdf/document-download | 200 |
| Agent (own) | Same-branch colleague's application | show/pdf/document-download | **403** |
| Agent (own) | Different-branch agent's application | show/document-download | **403** |
| Branch manager (branch) | Own-branch application (either agent) | index (sees both)/show | 200 |
| Branch manager (branch) | Different-branch application | show/pdf | **403** |
| Different-agency admin | Any application in this agency | show/pdf/document-download | **404** |

The 403 vs 404 split is intentional, not inconsistent: same-agency-wrong-scope
is a real 403 (the record exists, this user just isn't permitted); a
different agency's admin gets 404 because `BelongsToAgency`'s global scope
never resolves the row for route-model-binding at all, before any
finer-grained scope check runs — the same behaviour already proven for the
`admin`-only case earlier in this section, now reconfirmed with the newly-
granted roles.

### Required post-deploy step — do not skip

**`php artisan corex:sync-permissions --merge-defaults` is a REQUIRED step
on every environment this feature is deployed to** (QA1, Staging, live) —
it has already been missed once on QA1 today (cc2's fix only covered
`admin`; this entry's own fix was needed to cover `branch_manager`/`agent`
too). A `git pull` deploy never runs this automatically. Add it to this
feature's deploy checklist alongside `deploy:sync-reference-data`
(CLAUDE.md Non-negotiable #12, BUILD_STANDARD §8) — both exist for the
same reason: seeder/config-owned data that a plain `migrate` does not
carry across environments.

### Why cc4 found DB rows the config's git history didn't yet explain

cc4's audit (2026-09-07, correct and independently verified) found that
`role_permissions` on QA1 already held the `agent`/`branch_manager` grants,
while `/corex-qa1`'s own checked-out `config/corex-permissions.php` still
had no `role_defaults` entry for them. **The grants were never written
into the database by hand** — the sequence was: this fix's config change
was committed and pushed to `feature/rental-applications`, then
`corex:sync-permissions --merge-defaults` was run from an isolated
worktree that HAD that commit (both worktree and `/corex-qa1` share the
same `corex_qa1` database, but each has its OWN git checkout and
filesystem) — at that point `/corex-qa1`'s own checkout had not yet merged
`feature/rental-applications` (its last merge predated this fix), so its
copy of the config still looked stale even though the actual grants and
the actual committed config already agreed with each other. Confirmed via
`git merge-base --is-ancestor` that the fix commit was genuinely not yet
an ancestor of QA1's `HEAD` at the time. This is a deploy-*sequencing* gap
(config commit pushed, not yet merged into the environment's own branch),
not a bypassed-config gap — but it looks identical to one from the
outside, which is exactly why the stronger clean-state proof below exists
rather than trusting the DB state alone.

### Proof from a genuinely clean state — not a re-run against pre-existing rows

`tests/Feature/RentalApplications/RentalApplicationPermissionDefaultsTest.php`
proves the actual claim Johan needed proven: starting from a `role_permissions`
table with **zero** rows (RefreshDatabase — a real transaction-backed test
database, never run against real data), calling
`Artisan::call('corex:sync-permissions', ['--merge-defaults' => true])`
with no other setup produces exactly: `admin` holds all 4 keys (via
all-minus-exclude — `manage_settings` included), `branch_manager`/`agent`
hold the 3 non-settings keys, `rental_applications.view`'s `scope` column
resolves per `scope_defaults` (`all`/`branch`/`own`), the other three keys
carry no scope, and a second run inserts nothing new (idempotent). The
config alone — no manual database step — reproduces the correct grant set.
This is what makes the feature safe on a fresh agency, a QA1 reset,
Staging, or live: whoever runs the standard post-deploy sync gets these
grants back every time, from nothing.

**Found and reported, platform-wide, out of this lane's scope — now FIXED
(Johan, 2026-09-07: "the platform wide bug - needs attention and get
fixed. hate silent fails.")** — proving the grant set above exposed a
real, pre-existing limitation in `SyncPermissions::mergeRoleDefaults()`
itself, unrelated to Rental Applications specifically. It resolved
template roles via `Role::all(['name','is_owner','agency_id'])` wrapped in
a `try`/`catch` that only fell back to a synthetic template-role list when
`Role::all()` *threw* (e.g. the table doesn't exist) — but on a genuinely
fresh `roles` table that exists with zero rows (the real state until Role
Manager or a seeder creates rows; no seeder currently populates it),
`Role::all()` returns an empty Collection without throwing, the fallback
never fired, and `--merge-defaults` silently granted **nothing, for every
module**, not just this one, while still reporting success. The test
above worked around this by seeding the same minimal template `Role` rows
a real onboarded environment already has
(super_admin/admin/branch_manager/agent/viewer/office_admin,
`agency_id=null`) — a genuinely fresh *grants* table, not a genuinely
fresh *roles* table, which is the realistic scenario.

Johan explicitly authorised and directed the fix once this was reported;
it has since been built, proven, and shipped. Full writeup — the defect,
the `resolveRolesOrFail()` fix, the loud-reporting/non-zero-exit
mechanism, the sibling-command check, and the test file — lives in
`.ai/specs/roles-permissions.md` §10 (that spec, not this one, owns
`SyncPermissions.php`). The grant set proven above in this file was
re-confirmed passing unchanged under the fixed command
(`RentalApplicationPermissionDefaultsTest.php`, 2/2 green).

---

## Document-visibility bug (Johan, live on QA1, 2026-09-07)

Johan: "on testing rental applications the uploaded doc do not pull back
with the rental application." Traced the full chain on real QA1 data
before changing anything, per instruction:

1. **Application identified:** id=13 (agency 1, contact 16193, status
   `in_progress`, real 64-char token) — the most recent genuine activity on
   QA1 at the time, with a real ~1.3MB PDF uploaded. Ruled out ids 14–16 as
   another lane's audit fixtures (literally named "HACKED BY adminW" /
   "Audit App owned by AgentX") and ids 1–2 as having zero active documents
   (consumed by earlier test activity, not a bug).
2. **File on disk:** clean — exact size match, correct `www-data:www-data`
   ownership, correct permissions. The historical root-owned-file footgun
   does not apply here.
3. **Database row:** clean — `documents` row exists, `deleted_at` is NULL.
4. **Linkage:** clean — `source_type`/`source_id`/`agency_id`/`branch_id`
   all match the parent application exactly; the join executes and returns
   the row.
5. **Agent view query:** `show()`'s `documents()` relationship carries no
   extra filter beyond `source_type` — the own/branch/agency scoping work
   never touched it.
6. **Rendered and looked** (not grepped): dispatched a real authenticated
   request as user 22 to `show(13)`, from both an isolated worktree AND
   directly from `/corex-qa1`'s own live codebase — the document rendered
   correctly both times. **Not reproducible on this specific application,
   right now.**

**Root cause, found by reading the code, not by guessing:**
`RentalApplicationSigningController::uploadDocuments()` only ever advances
status `sent → in_progress` (it never reaches `returned`, which requires
the full sign-both-declarations `submit()`). But
`RentalApplicationController::returned()`'s status filter was
`['returned','under_assessment','approved','declined','withdrawn']` —
**`in_progress` was excluded**. An applicant who uploaded a real,
correctly-filed document without finishing the signature flow was
invisible on the one screen named for reviewing incoming applicant
activity — not because the document was broken, but because the
*application* never surfaced there in that state.

**Why cc2's QA sweep passed while Johan's real use failed:** cc2 almost
certainly tested the linear happy path — full submit, both signatures,
*then* a document present, which lands in `returned` and always displayed
correctly. Real usage doesn't queue up that neatly: Johan uploading a
document without necessarily finishing signatures first is exactly the
ordering the happy-path QA sweep never exercised. The lesson generalised:
a scripted QA pass that only walks the linear/complete path is not
equivalent to proving what a real, non-linear user does — the gap here
was in test *coverage of ordering*, not in the document-handling code
itself, which was correct throughout.

**Fix:** `returned()` now includes `in_progress` in its status filter
(`app/Http/Controllers/CoreX/RentalApplicationController.php`); the status
tab bar in `returned.blade.php` gained a matching "In progress" tab.
`in_progress` deliberately still shows on `index()` too — left there
rather than removed, so nothing an agent currently relies on seeing there
disappears as a side effect of this fix.

**Proven end to end, not in isolation**
(`tests/Feature/RentalApplications/RentalApplicationDocumentVisibilityTest.php`):
a real multipart file uploaded through the actual public
`uploadDocuments()` route (black-box — cc4's endpoint, never edited),
confirmed to leave status at `in_progress`; the application then appears
on both Returned Applications and the main index; `show()` displays the
document; the document downloads correctly. Scoping re-confirmed on this
exact fixture afterward: a same-agency unrelated agent gets 403 on the
download, a different-branch agent gets 403 on both the download and the
PDF, a different agency gets 404 on both (route-model-binding never
resolves it), and the owning agent still succeeds.

**cc4's leftover audit fixtures — reported, not touched (no hard
deletes):** `qa-audit-{agentx,agenty,bmz,adminw,outsiderv}@test.local`
users, rental applications 14/15/16, documents 2281/2282. Applications 14
and 15 are `agency_id=1` (Johan's real agency) with status `sent` and
full names "Audit App owned by AgentX (branch1)" / "HACKED BY adminW" —
**these DO currently appear on the real agency-1 index screen** (any
`admin`/`all`-scope viewer, including Johan's own account, would see them
mixed in with real applications) — not a data-corruption risk, but
visibly confusing if left there. Application 16 is a different agency
(7) and does not interfere with agency 1's view. None of this is mine to
clean up (cc4's own test data, and the standing no-hard-deletes rule
means it needs an explicit soft-delete decision, not a unilateral one).

**Resolved 2026-09-07:** all 5 fixture applications/documents archived
(soft delete — `deleted_at` set, files untouched on disk), 4 of the 5
`qa-audit-*` users archived (agency-1 accounts, visible in `/admin/users`
and any agent picker); the agency-7 outsider account left active since
it's invisible to Johan's own agency-scoped views and useful for
re-running the scoping audit later. Verified clean by rendering both
`corex/rental-applications` and `corex/rental-applications/returned`
through the full kernel as user 22 (johan@hfcoastal.co.za) — zero
occurrences of the fixture names in either. Genuine data counts confirmed
unchanged before/after (cross-checked by exact archive timestamp, since a
scope mistake on the first count attempt briefly double-counted
already-trashed rows from other lanes' historical test data).

---

## Post-submission document lock (Johan, 2026-09-07 — 3rd pass)

**The rule, verbatim:** "submitted docs are submitted. they can add, but
not replace or remove." Concretely:
- **Before submission:** the applicant has full document CRUD — add,
  replace, remove (archive, never hard delete).
- **After submission** (`RentalApplication::isSubmitted()`, i.e.
  `submitted_at !== null`): **add only.** Replace and remove are locked,
  application-wide, for every document on the application — including one
  added after submission. What was submitted stays exactly as submitted.

**Reasoning (evidentiary, not a UX preference):** once an agent has
received an application, the applicant must not be able to quietly swap a
payslip or pull a document the agent has already seen — that would let an
applicant retroactively alter what was actually reviewed. But the
applicant must still be able to send more: an agent asking "can you also
send your bank statements" is a normal, expected request and must not
require reopening or resetting anything.

**This is a correctness rule, not a setting.** No agency toggle, no
threshold, no configurable window — enforced identically for every
agency. Per Johan: "do not make it agency-configurable."

### Enforcement — server-side, not blade-only

`RentalApplicationSigningController::assertDocumentsNotLocked()` is the
single choke point both `removeDocument()` and `replaceDocument()` call,
right after `scopedDocument()` establishes the document genuinely belongs
to this application (so the check order is: expired token → document
exists and belongs here → submission lock). `viewDocument()` and
`uploadDocuments()` are deliberately NOT gated by this check — viewing and
adding remain available regardless of submission state (only token expiry
gates those two).

The check reads `RentalApplication::isSubmitted()` — a single-field
`submitted_at !== null` check — rather than re-deriving "is this
submitted" from the status enum at each call site. One source of truth,
so a later refactor that moves or renames a status value can't silently
un-lock this without also breaking `isSubmitted()`'s own callers.

**Proven with real requests, not asserted**
(`tests/Feature/RentalApplications/RentalApplicationDocumentLockTest.php`):
before submission, upload → replace → remove all succeed via the real
public routes (replace confirmed atomic: old doc archived, new doc
created; remove confirmed archived, not hard-deleted — row still present
via `assertDatabaseHas`). After submission: a replace POST against the
original document redirects back with a flash error and leaves the
original completely untouched (`deleted_at` still null, document count
unchanged — not even a new document was filed); a remove POST against the
same document is refused the same way, row still present with
`deleted_at` still null; an add POST still succeeds, taking the document
count to 2 and the new document's `created_at` provably later than
`submitted_at`. A dedicated test asserts the locked remove is a full
no-op at the database level (`assertDatabaseHas` with `deleted_at: null`),
not merely "still recoverable via `withTrashed()`."

### UI — the applicant sees why, not just a disabled control

Per Johan: "a greyed-out button with no explanation is a support call."
`_document-list.blade.php` (shared by `show.blade.php` and
`already-submitted.blade.php`) checks `$application->isSubmitted()`: once
true, each document's Replace/Remove controls are replaced with a plain
"Submitted — locked" label, and a line beneath the list reads "The
documents above were submitted with your application and can't be
changed. Need to send something else? Add it below — your agent will see
it as a new document." The upload form itself is never hidden or altered
— add keeps working exactly as before.

### Agent-side visibility of late additions — agreed with cc3, not built by cc4

Requirement: "Anything added AFTER submission must be visibly
distinguishable to the agent — timestamp it and surface that on the
agent's view." This is cc3's file (`corex/rental-applications/show.blade.php`),
not cc4's — no schema change needed, no new column: a document is "late"
whenever `$document->created_at->gte($rentalApplication->submitted_at)`.

**Agreed with cc3, 2026-09-07:** confirmed shape — a badge next to any
matching document in `show.blade.php` (their `returned.blade.php` doesn't
currently list individual documents, only a signed/incomplete summary, so
the badge is expected to live in `show.blade.php` only). cc3 is
implementing this after a separate, already-in-flight, Johan-approved
task (a platform-wide `SyncPermissions` fix) lands.

**Correction, 2026-09-07 (before cc3 built it, caught by this lane's own
regression test):** the comparison was originally proposed and agreed as
strict `>` (`created_at->gt(...)`). A fast automated test run exposed
that `submitted_at` and a same-request-cycle-adjacent document's
`created_at` can land in the same second-precision timestamp column,
making `>` occasionally false for a document that genuinely was added
after submission — not a business-logic error, purely a precision tie.
**Use `>=` (`gte`), not `>`.** Safe because `submit()` never creates a
document itself, so anything that already exists at the moment
`submitted_at` is set was necessarily created in an earlier, separate
request — `>=` can never misclassify a genuinely-original document as
late. cc3 has not yet implemented the badge, so no follow-up correction
is needed on their side — flagging here so the corrected version is what
ships the first time.

**End-to-end proof, real requests against live QA1** (application id 20,
token generated fresh, not a synthetic fixture): pre-submission — added
`original.pdf` (doc 2284), replaced it with `replacement.pdf` (doc 2285,
old doc's `deleted_at` set, file untouched on disk), removed it (doc
2285's `deleted_at` set) — all three actions HTTP 200/302 with their
success flash. Re-added `original2.pdf` (doc 2286), submitted both
signatures (`status` → `returned`, `submitted_at` set). Post-submission:
a REPLACE attempt against doc 2286 redirected back with the lock message
and left doc 2286 completely untouched (`deleted_at` still null, document
count still 1 — no sneaky replacement was even filed); a REMOVE attempt
against the same document was refused identically, doc 2286 still
untouched; an ADD of `bankstatement.pdf` (doc 2287) succeeded (HTTP 200,
"was uploaded"), confirmed `doc 2287.created_at` (09:44:37) is after
`submitted_at` (09:42:38) — the exact comparison cc3's badge will use.
The rendered `already-submitted.blade.php` page shows "Submitted —
locked" against the original document, zero occurrences of "Replace" or
"Remove", and the explanatory line beneath the list.

---

## Agent-side bug (Johan, QA1) — "adding and saving" the email did not persist

**This section covers the agent-facing edit screen
(`corex/rental-applications/{id}` — `RentalApplicationController`,
`show.blade.php`) — normally cc3's lane, fixed here directly on Johan's
explicit, highest-priority instruction while he was blocked and waiting.**

**Johan's words, verbatim:** "Tried rental application - loaded test
contact, no email. moans no email correctly, but adding and saving do not
persist. thats rookie coding issues that I should not have to run into."

**Root cause — NOT a persistence bug. Two different "email" values, one
visible field.** `rental_applications.email` (the "Email address" field
on the edit screen, `show.blade.php:113`) always saved correctly — proven
with real dispatched requests before touching a single line of the fix.
But `send()` (`RentalApplicationController.php:233`, pre-fix) and
`RentalApplicationMailer::sendInvite()` (pre-fix) both read
`$rentalApplication->contact->email` — a completely different column, on
the linked `Contact` record, which this screen offers no way to edit.
Sequence: contact has no email → Send correctly reports "no email on
file" (`contacts.email` genuinely empty) → agent types an email into the
only field the screen shows and saves → it saves, correctly, to
`rental_applications.email` → nothing that decides whether/where to mail
ever reads that column, so sending still (correctly, by its own logic)
reports "no email." From the agent's side this is indistinguishable from
"I saved it and it didn't stick."

**Fix:** `RentalApplication::recipientEmail()` — `$this->email ?:
$this->contact?->email` — single choke point both `send()` and
`sendInvite()` now call. The application's own, agent-editable field
takes priority (it's the one this screen exists to let the agent fix);
falls back to the contact's email if the application's own field was
never set. Deliberately does NOT write back to `contacts.email` — that's
a separate, bigger decision (should fixing the email here update the
contact's own record for every other feature that reads it?) outside
this bug's scope; flagged, not decided.

**Fix the class, not the instance (Johan's explicit instruction):** while
tracing this, found the SAME symptom already existed for other fields on
this exact form — but for the DB-persistence angle, not the "wrong field
consulted" angle. Three `<textarea>` fields
(`current_residential_address`, `employer_address`, `special_conditions`)
and the `employment_type` `<select>` read straight from
`$rentalApplication->X` on redisplay, never `old('X', ...)` — so ANY
validation failure elsewhere on this one-big-form silently reverted all
four to their stale DB value, while the `<x-rental-application-field>`
component-based inputs (already fixed once, on the public-facing form)
correctly preserved what was typed. Fixed all four to use `old()`.

Also found and fixed: **this screen had zero validation-error visibility
at all** — no summary banner, no per-field `@error` messages anywhere. A
failed save looked visually identical to a successful one; the only
difference was which fields silently reverted. Added a "Please check the
highlighted field(s) below — nothing was saved" banner and `@error`
blocks under all four raw fields, matching the pattern already used by
`<x-rental-application-field>`.

**Proven end to end, real requests as user 22, live QA1:**
- Contact with no email (id 8324, application id 5) → Send → flash
  correctly reads "This contact has no email on file"; `Mail::fake()`
  equivalent (real Mailpit check) confirms nothing sent.
- Fill `email` field only, save → `HTTP 302`, DB `email` column
  persisted, reloaded show page's input carries the value.
- Send again → flash: "Sent to johan-test-recipient@example.com" —
  **confirmed via the real Mailpit API**, the captured message's `To`
  header is exactly that address, not the (still-empty) contact email.
- Full-form submission with every field filled to a distinct value and
  ONE field deliberately invalid (`adults = 'abc'`, fails `integer`):
  redirect back, error banner and per-field message both render, DB
  confirms nothing at all was written (all-or-nothing), and — the actual
  regression check — every single field the agent typed, including the
  four previously-reverting ones, is still shown on redisplay, not the
  stale DB value. Corrected resubmission then saves everything.

**Regression tests** (`tests/Feature/RentalApplications/RentalApplicationAgentControllerTest.php`):
`test_editing_the_applications_own_email_field_is_what_send_actually_uses`
and `test_every_field_on_the_form_survives_a_validation_failure_not_just_the_corrected_one`.
Both drive the real routes end to end (`Mail::fake()` +
`Mail::assertSent(...->hasTo(...))` for the email-target assertion) —
this is exactly the class of rule ("input typed must never be silently
discarded") that a later refactor could quietly undo with no failing
assertion to catch it, hence locking it in as a real HTTP-level test
rather than a unit assertion on the model alone.

**Also found while running this file's OTHER (pre-existing, cc3's)
tests, and fixed as an environment gap, not a code bug:** this worktree
has no built frontend bundle (`public/build/manifest.json` was never
generated here — no `npm run build` was ever run in this specific
worktree), so every test hitting a `@vite()`-using view 500'd with
`ViteManifestNotFoundException` — 5 of cc3's own pre-existing tests in
this file, unrelated to this bug, were failing for that reason alone.
Added `$this->withoutVite();` to this test class's `setUp()` (the correct
Laravel testing pattern for exactly this situation, already used in
`RentalApplicationDocumentLockTest.php`). All 10 tests in this file now
pass, 73 assertions.
