# E-Sign V3 Phase 1B — Build Notes (Pagination + Wet-ink + Signing UI integration)

These three areas of the Phase 1B spec ship in V1 as **scoped-down deliverables with documented limitations**. Full implementation is deferred to a follow-up because each has architectural depth that exceeds the Phase 1B time budget without compromising data-layer correctness.

---

## 1. Pagination recalc engine (Part 13) — V1: SIMPLIFIED

**Phase 1B ship:** No real-time pagination recalculation. Adding a condition or strikethrough updates the data layer (`document_conditions` + `signature_templates.other_conditions_text`) but the rendered document's page count is recomputed only when the document is re-rendered (next view of the signing surface, or PDF flatten on completion).

**Why:** The existing render pipeline (`cds_drafts.cds_json → tagged_html → WebTemplatePdfService → Puppeteer/PDFKit`) is invoked on demand. Trying to do mid-edit pagination introduces a synchronous render call into the API path or requires an async queue with a "render in progress" state surfacing back to the agent review screen.

**Acceptable degraded behaviour for V1:**

- Conditions are appended to `other_conditions_text` as numbered lines.
- The agent review surface (§7.5.6) renders the conditions list as a structured diff, NOT as a paginated preview.
- The final PDF flatten on completion picks up the conditions automatically via the existing render pipeline — page-bottom initial slots are added by the existing pagination layer at that time.

**Follow-up work (Phase 9.5.1):**

- Add a synchronous "preview" render endpoint that returns the new page count.
- Surface "+N pages added" in the agent review modal.
- For documents already in `STATUS_AMENDMENT_INITIALING`, ensure newly-added pages receive initial slots in the focused initialing view.

---

## 2. Wet-ink rendering (Part 14) — V1: DEFERRED to render layer

**Phase 1B ship:** No special wet-ink template treatment in this build. The existing wet-ink PDF generator (`WebTemplatePdfService` / `SignaturePdfService`) is unchanged.

When a template marked `wet_ink` delivery enters the render pipeline, the `~~~~OTHER_CONDITIONS~~~~` marker is currently treated like any other text placeholder. The Blade-generation pipeline strips placeholders that don't have a registered conditions array; the marker token therefore renders literally as visible text.

**Follow-up work (Phase 9.5.2):**

- Extend `WebTemplatePdfService::renderSection()` (or the generation path inside `TemplateController::generateCdsBladeView()`) to recognise `insertable_block_placeholder` items and emit either:
  - For **e-sign mode**: a styled block showing the live conditions list (already covered by the signing-page partial in this build).
  - For **wet-ink mode**: an empty numbered-line grid (2 blank lines per slot, 5 slots default) for manual writing.

**Acceptable V1 workaround:** Templates that need wet-ink Other Conditions should bake the empty numbered lines directly into the source `.docx` (5 lines pre-formatted) rather than relying on the `~~~~OTHER_CONDITIONS~~~~` marker for wet-ink output. The marker is fully functional for e-sign delivery.

---

## 3. Signing-experience integration (Parts 10 + 11) — RESOLVED in Phase 1B.5

**Phase 1B shipped backend + reusable Blade partial.**

**Phase 1B.5 closes the loop with full signing-view integration.** Shipping commit: see git log for "Phase 1B.5 — signing-view embeds".

- `app/Services/Docuperfect/InsertableBlockRenderer` replaces every `~~~~MARKER~~~~` in the document's HTML body with styled, contextual block partials. Threaded into the existing `SigningController::show()` pipeline right after `LetterheadRefresher::refresh()`.
- Recipient signing context emits "+ Add condition" buttons inside each block — these dispatch a `CustomEvent` that the embedded `add-condition-modal` partial listens for.
- Override modal partial scans the document body on load, wraps numbered clauses (regex `/^\s*(\d+(?:\.\d+)*)\b[\.\s]/`) with click handlers that dispatch `open-override-modal` with the clause ref + text.
- Sign-token API endpoints added:
  - `POST /sign/{token}/conditions` — recipient adds a condition
  - `POST /sign/{token}/strikethroughs` — recipient proposes a strikethrough
  - `POST /sign/{token}/initial-amendments` — submits per-party initials during the cascade
- `SigningController::show()` view-switch fires when `amendment_status = 'amendment_initialing'` — routes to the new focused initialing view at `resources/views/docuperfect/signatures/external/initialing.blade.php` showing only the changed regions for the current party.
- `LegacyOtherConditionsBridge` bridges the wizard's free-text textarea into structured `document_conditions` rows so recipient signing renders them — idempotent via `custom_label` signature column, cleans up its own rows on textarea-emptying.
- Backfill migration `2026_05_22_120001` scans existing docs with non-empty `other_conditions_text` and zero structured rows, runs the bridge once.

**Still deferred (not user-facing experience gaps):**
- Wet-ink empty-slot rendering — separate prompt.
- Pagination recalc — separate prompt.

---

## Summary of V1 scope ship

| Item | V1 status |
|---|---|
| Schema (3 new tables + `insertable_blocks` JSON on `docuperfect_templates`) | SHIPPED |
| Models with relationships + immutability on `ConditionInitial` | SHIPPED |
| `CdsParserService::detectMarkers()` extended for `~~~~<PURPOSE>~~~~` | SHIPPED |
| `SignatureService::requeueAllPartiesForInitialing()` + `rejectAmendmentChange()` + `rejectAmendmentDocument()` | SHIPPED |
| `STATUS_AMENDMENT_INITIALING` constant + amendment-status sub-states | SHIPPED |
| `ConditionsController` (POST condition + strikethrough) + routes | SHIPPED |
| `AmendmentController` (review / approve / reject / reject-document) + view + routes | SHIPPED |
| CDS builder "Insert Block" + "Clauses" panel | SHIPPED |
| Signing partial for add-condition + strikethrough modal | SHIPPED (Phase 1B) |
| **Signing-view embed** (renderer + modals + click handlers + initialing view + legacy bridge) | **SHIPPED in Phase 1B.5** |
| Pagination recalc | DEFERRED — V1 ships static; covered by final-flatten render |
| Wet-ink empty-slot rendering | DEFERRED — V1 relies on source-doc pre-formatted lines |

Spec ref: `.ai/specs/esign-v3-complete-spec.md` §7.5, §8, §17 ES-3, §17 ES-9.
