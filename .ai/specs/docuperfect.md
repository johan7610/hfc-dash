# Spec: DocuPerfect

**Status:** Live — consolidation items pending  
**Module:** Document generation, linked fields, write-back

---

## What DocuPerfect Is

DocuPerfect is the document engine of CoreX. It generates, manages, and routes all agency documents — mandates, OTPs, deeds of sale, leases, renewal agreements, addendums, and any other document an agency uses.

It is not a generic document builder. It is a real estate document system that understands the four pillars and keeps documents connected to the data that powers them.

---

## Core Concept: Linked Fields

Documents in DocuPerfect are not static templates with blank spaces. Every field is linked to a data source — one of four data buckets:

| Bucket | Source |
|--------|--------|
| Property | Property record in CoreX |
| Contact | Contact record(s) linked to the deal |
| Deal | Deal record |
| Agent | Agent (User) profile |

Fields use dot-notation syntax: `property.address`, `contact.full_name`, `deal.purchase_price`, `agent.cell_number`.

When a document is generated, CoreX pulls the current values from the linked pillars and populates every field automatically. The agent reviews, adjusts if needed, and sends for signature.

---

## Write-Back (Consolidation Pending)

When a document is completed (all signatures collected), DocuPerfect writes key values back to the relevant pillars:

- Purchase price on signed OTP → updates Deal record
- Tenant details on signed lease → updates Contact record as tenant of Property
- Landlord details on mandate → updates Contact record as owner of Property
- Agent commission rate on signed mandate → updates Deal record

**Status:** Write-back consolidation is a Phase 1 checklist item. Not yet fully implemented across all document types.

---

## Document Types

All documents are HTML/Blade web documents rendered via Puppeteer. The old PDF overlay system is retired.

Document fidelity is non-negotiable: character-perfect rendering, no autocorrection, no smart quotes, no reformatting.

---

## Outgoing Emails

All DocuPerfect outgoing emails (document delivery, signature requests, completion notifications) must include the sending agent's email signature. Signatures are Outlook-style, per-user, configured in user settings.

**Status:** Email signature injection is a Phase 1 consolidation item.

---

## Consolidation Items (Phase 1)

- [ ] Write-back to all four pillars on document completion
- [ ] Email signature injection on all outgoing emails
- [ ] Migrate any remaining PDF overlay templates to Blade web documents
