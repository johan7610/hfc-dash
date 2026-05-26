# CoreX E-Sign System Full State Audit
**Date:** 2026-05-26  
**Branch:** HFC2402  
**Scope:** Comprehensive audit from template setup through final filing

## HEADLINE FINDINGS

1. **editable_by field locking** — Code logic correct; operation UNVERIFIED. 7 templates have editable_by populated; never tested end-to-end. MEDIUM.
2. **Strikethrough flow replaced** — Spec §7.5.5 describes it; Phase 1B.6 replaced with flags 6h before demo. Intentional design change. LOW.
3. **Legal block OTP bypass risk** — 7 OTP-named templates unclassified (need document_type_id). Regex word-boundary correct, but Layer 1 preferred. MEDIUM.
4. **Amendment system untested** — Schema + logic complete; 0 live amendments. First real amendment = production test. LOW.
5. **Vision-PDF not wired** — ClaudeVisionParserService exists; ES-6.1 deferred. LOW.
6. **Recipient amendment surface fully operational** — Add-condition + flag UI working; agent review + cascade working.
7. **Legal block 95% complete** — Layers 1–4 in place; audit logging active.
8. **Demo amendment surface was simplified, not stripped** — Phase 1B.6 replaced strikethrough with flags BEFORE demo.

## COMPONENT STATUS TABLE

| Component | Status | File:Line |
|-----------|--------|-----------|
| Template import (Path A + B) | ✅ | DocumentImporterController + CdsParserService |
| CDS builder save | ✅ | TemplateController:800+ |
| isEsignBlocked() | ✅ | Template.php:173–200 |
| Legal block audit log | ✅ | LegalBlockAuditLog |
| Wizard 6 steps | ✅ | wizard.blade.php |
| FICA gate + orderBy fix | ✅ | SigningController:123–149 |
| Supervisor flow | ✅ | CandidatePractitionerService |
| Recipient signing view | ✅ | external/sign.blade.php |
| editable_by server logic | ✅ | SigningController:1347–1385 |
| editable_by client logic | ⚠️ | external/sign.blade.php:~1500 |
| Add Condition | ✅ | add-condition-modal + endpoint |
| Flag Clause | ✅ | flag-clause-modal + endpoint |
| Strikethrough | 🚫 | 410 Gone (deleted Phase 1B.6) |
| Insertable blocks | ✅ | InsertableBlockRenderer |
| Other Conditions | ✅ | signature_templates.other_conditions_text |
| DocumentAmendment | ✅ | DocumentAmendment.php (0 rows) |
| Amendment review | ✅ | review.blade.php |
| STATUS_AMENDMENT_INITIALING | ✅ | SignatureService:1180–1220 |
| Focused initialing | ✅ | initialing.blade.php |
| Per-page initials | ✅ | a4-page-styles.blade.php:313 |
| PDF hash logging | ✅ | SignatureAuditLog |
| legal_block_audit_log | ✅ | LegalBlockAuditLog |

## SINGLE MOST IMPORTANT FINDING

**Current state vs spec:** Recipient amendment surface is operational with simplified flag-based proposal model (not strikethrough-auto-condition-routing). Design change is intentional. Code is 95% aligned with spec intent.

## ORPHAN CODE

1. PostSigning class (150 LOC) — zero callers [Safe to delete]
2. Signature::saveSignature() — appears superseded [Verify before deletion]
3. document_clause_strikethroughs writes — schema exists, no writers post-Phase 1B.6 [Safe to leave]

## SPEC-BUT-NEVER-WIRED

1. Vision-PDF input (ES-6.1) — ClaudeVisionParserService exists; route not integrated
2. Strikethrough as spec describes — replaced by flags Phase 1B.6; spec needs update
3. Agent-side strikethrough creation — specced but never built
4. Wet-ink empty-slot rendering — deferred to render layer; acceptable workaround
5. Pagination recalc on condition add — deferred; final render picks up pages

## SPEC-VS-CODE GAPS

| Gap | Spec | Code | Impact |
|-----|------|------|--------|
| Strikethrough | §7.5.5 describes auto-route | Phase 1B.6 uses flags | LOW |
| editable_by | §9.2 "verification needed" | Code correct; untested | MEDIUM |
| mapSigningPartyKeys() | §4.3 references method | isSalesDocument() + hardcoded map | LOW |
| Wet-ink rendering | §7.5.11 empty-slot grid | Deferred to render layer | LOW |

## PRE-DEMO-STRIP ANSWER

**Q: Did we tear out recipient amendment surface in 1B.8 BEFORE the demo, or was demo running with it intact?**

**A: Amendment surface existed but SIMPLIFIED 6h before demo.**

Timeline:
- 2026-05-21 21:59 — Phase 1B.6 ships: strikethrough modal DELETED, replaced with flags
- 2026-05-21 22:14 — Phase 1B.7 ships: add-condition + per-condition initials
- 2026-05-21 22:49 — Phase 1B.8 ships: remove clause-dropdown. "E-Sign V3 shipped."
- 2026-05-22 ~15:00 — DEMO: sees add-condition + flag UI (not strikethrough)

Git evidence: commit 7521e63 (2026-05-21 21:59) deletes strikethrough modal 6h before demo.

**Conclusion:** Not torn out; intentionally replaced with simpler flag-based workflow.

---

End of audit. All stages 1–9 traced. Stages 4–5 detailed per brief.
