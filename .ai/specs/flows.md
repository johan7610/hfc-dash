# Spec: Flows

**Status:** Architecture defined — UI implementation pending consolidation sprint  
**Priority:** Core — foundational to Phase 2 pipeline views

---

## What Flows Are

A Flow is a guided, stateful, multi-step process that moves a real estate transaction from one stage to the next. Flows are the operating model of CoreX — not a module, not a feature. They are how the system moves.

Real estate is a lifecycle. Every stage flows into the next. CoreX makes each transition a button click, not a manual task list.

---

## The Full Lifecycle

```
PROPERTY IDENTIFIED
      ↓
LISTING APPOINTMENT → Presentation Flow
      ↓
MANDATE SIGNED → Mandate Flow (sole mandate / open mandate / rental mandate)
      ↓
PROPERTY MARKETED → Listing live (P24, portal, marketing)
      ↓
OFFER RECEIVED → OTP Flow
      ↓
OFFER ACCEPTED → Sale Flow (Deed of Sale, FICA, compliance docs)
      ↓
REGISTERED → Deal closed → Commission calculated → Agent paid
      
══════════════════════════ RENTAL TRACK ══════════════════════════

RENTAL MANDATE SIGNED → Rental Pack Flow
      ↓
TENANT FOUND → Lease Flow (Lease Agreement, deposit receipt, FICA)
      ↓
LEASE ACTIVE → Property under management
      ↓
RENEWAL DUE → Renewal Flow (new terms, updated lease, re-sign)
      ↓
TENANT VACATES → Exit Inspection Flow → Re-listing Flow
      ↓
BACK TO TOP → Property re-enters the lifecycle
```

---

## Flow Principles

### 1. Every arrow is a button click
When a flow stage completes, the system surfaces the next logical step. It does not wait for the agent to figure out what comes next. Example: Mandate signed → system prompts "Mandate complete. Create listing?" → agent clicks Yes → listing form pre-filled.

### 2. Flows carry data forward
Every piece of data from previous stages pre-fills into subsequent stages. The system knows the property, the contacts, the deal type, the agent. Agents never re-enter data the system already holds.

### 3. Flows save state
An agent can start a flow, close the browser, and resume exactly where they left off. The system holds the current step and all entered data.

### 4. Completed flows enrich the pillars
Every completed flow writes back to Property, Contact, Deal, and Agent. A completed lease flow updates the property's tenancy status, creates the deal record, links the tenant contact, and records the agent's deal. This is the data flywheel — every completed flow makes the next one faster and smarter.

### 5. Flows know history
When a re-listing flow starts for a property that's been through three previous cycles, the system pre-loads three cycles of context — previous tenant, previous rent amount, previous lease terms, compliance history. The agent starts from a position of full knowledge, not a blank form.

---

## Flow States

| State | Meaning |
|-------|---------|
| `draft` | Started but not yet in active progress |
| `in_progress` | Agent is actively working through steps |
| `awaiting_signature` | Document sent — waiting for signing party |
| `awaiting_counterparty` | Waiting on other party (e.g. OTP awaiting seller acceptance) |
| `complete` | All steps done, data written back to pillars |
| `archived` | Soft-deleted — recoverable by admin |

---

## How Flows Consume the Four Pillars

Every flow requires at minimum:

| Pillar | What the flow needs |
|--------|-------------------|
| Property | Which property this flow is for |
| Contact | Who the parties are (seller, buyer, landlord, tenant) |
| Deal | The transaction record this flow creates or advances |
| Agent | Which agent is running this flow |

**Rule:** A flow cannot start without a linked Property. All other pillars are added progressively through the flow steps.

---

## Flow Types (Initial Set)

| Flow | Trigger | Output |
|------|---------|--------|
| Presentation Flow | Listing appointment booked | Presentation document, property record created |
| Mandate Flow (Sale) | Seller agrees to list | Signed sole/open mandate, listing record |
| Mandate Flow (Rental) | Landlord agrees to list | Signed rental mandate, listing record |
| OTP Flow | Buyer submits offer | Signed OTP, deal record opened |
| Sale Flow | OTP accepted | Deed of Sale, FICA pack, compliance checklist |
| Rental Pack Flow | Tenant found | Application, credit check, approval |
| Lease Flow | Tenant approved | Signed lease, deposit receipt, FICA |
| Renewal Flow | Renewal date approaching | Updated lease, re-signed by both parties |
| Exit Inspection Flow | Tenant gives notice | Inspection report, deposit reconciliation |
| Re-listing Flow | Property vacant | New mandate, updated listing |

---

## The Flow Dashboard

The Flow Dashboard is the home screen of CoreX for agents. It is the first thing they see on login.

```
ACTIVE FLOWS
──────────────────────────────────────────────────────
  14 Marine Drive, Uvongo
  📋 Sale Flow — Step 3 of 6  (Awaiting seller FICA documents)
  Agent: Sarah M.    Started: 3 days ago    [Resume →]

  Unit 7, Margate Gardens
  📋 Renewal Flow — Step 1 of 4  (Enter new rental terms)
  Agent: Darren K.    Started: Today    [Resume →]

  22 Hibiscus Road, Ramsgate
  📋 Lease Flow — Step 5 of 5  (Awaiting tenant signature)
  Agent: Petra N.    Started: 2 days ago    [Resume →]

──────────────────────────────────────────────────────
START A NEW FLOW
  🏠  New Listing Appointment
  📋  Sale Pack
  📋  Rental Pack
  🔄  Lease Renewal
  🔎  Re-list a Property
```

---

## Dependencies (Must Be Complete First)

Flows depend on the four pillars being connected:

- Property ↔ Contact owner link (Phase 1 consolidation item)
- Deal record with all parties linked (Phase 1 consolidation item)
- DocuPerfect write-back to pillars (Phase 1 consolidation item)

**Flows cannot carry data forward if the pillars don't hold it.** Complete Phase 1 consolidation before building Flow UI.

---

## Phase 2 Build Scope

When consolidation is complete, the Flow UI build includes:

1. Flow Dashboard (home screen widget for agents)
2. Flow state machine (state transitions, step tracking, data persistence)
3. Flow runner (step-by-step UI — progress indicator, pre-filled fields, next step prompt)
4. Flow history (per property — full lifecycle audit trail)
5. Flow templates (configurable step sequences per mandate/deal type)

Full spec required before Andre begins.
