# Spec: E-Signature

**Status:** Live — both electronic and wet-ink implemented

---

## What E-Signature Does

Provides legally binding digital signatures for all CoreX documents — both electronic (drawn on screen) and wet-ink (uploaded scan).

---

## Electronic Signature

- Alpine.js canvas-based capture in browser
- Works on desktop and mobile (touch)
- Server-side rendering: `imagettftext` places signature onto document
- Identity verification gate before signing (ID number / date of birth confirmation)
- Sequential signing: signers are presented in order — next signer is only notified when previous signer completes
- Email notifications at each signing step

## Wet-Ink Signature

- Upload-on-behalf functionality: agent can upload a wet-ink scan on behalf of a party
- Uploaded scan replaces the electronic signature page image
- Document flattens: subsequent signers see the wet-ink scan as part of the document (not a separate attachment)
- DomPDF-compatible CSS overlay fallbacks for flattening (to be replaced with Puppeteer on consolidation)

## Signing Flow

```
Document created in DocuPerfect
      ↓
Signer 1 notified via email (link to signing portal)
      ↓
Signer 1 verifies identity (ID number / DOB gate)
      ↓
Signer 1 draws signature on canvas (or agent uploads wet-ink scan)
      ↓
Document flattened with Signer 1's signature
      ↓
Signer 2 notified → same process
      ↓
All signatures collected → document marked complete → DocuPerfect write-back triggered
```

---

## Consolidation Notes

- PDF flattening should move fully to Puppeteer (currently partial DomPDF CSS overlay)
- Deferred signing (park document, sign later) is a Phase 2 spec item
