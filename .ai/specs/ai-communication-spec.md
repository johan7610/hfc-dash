# AI Communication — Complete Specification

**Spec ID:** `ai-communication-spec`
**Date:** 2026-05-21
**Owner:** Johan Reichel (HFC / CoreX)
**Status:** Approved for build
**Target file path:** `.ai/specs/ai-communication-spec.md`
**Builds on:** `mic-complete-spec.md` (uses AnthropicGateway from Phase B1)

---

## 0. How to Use This Document

Single source of truth for every AI-generated communication surface in CoreX. WhatsApp pitches today; email, listing descriptions, seller updates, viewing feedback messages, and Ellie chat tone all flow from the same foundation.

Hard rules from CLAUDE.md apply. Builds on `AnthropicGateway` (mic-complete-spec §4.8 / Phase B1).

---

## 1. Vision and Constraints

### 1.1 The mission statement

**AI-powered messages are better than template messages.** That's the bar. Not personality engines. Not seven-language support. Not psychometric segmentation. Just: a message generated for *this* seller, on *this* property, with *this* market context — sharper, more relevant, and more likely to get a reply than a generic template.

### 1.2 The five constraints that govern every pitch

These are not preferences. They are non-negotiable rules the AI is forbidden to break.

**Constraint 1 — Under 50 words.**
A WhatsApp message that takes more than 5 seconds to read is closed and forgotten. The AI is hard-capped at 50 words. Empirically the best pitches are 30-40.

**Constraint 2 — Never demand without realism anchor.**
This is the overpricing trap. "We sold 12 properties like yours last month" sounds like a great hook but produces sellers who anchor 15% above market value, refuse to budge, and watch their property sit for 6 months. Every demand signal in a pitch MUST be paired with a realism anchor.

❌ "12 properties like yours sold last month — there's huge demand!"
✅ "12 properties like yours sold last month. The ones that priced within 5% of market sold in 38 days. The rest are still sitting."
✅ "Strong demand in your area, but the homes winning are priced sharply. Happy to show you what that looks like."

The AI's system prompt explicitly contains this rule with examples. Audit logs flag any output that mentions sales count, demand, or buyer count without a matching realism anchor.

**Constraint 3 — One number, one question, one signature.**
- One concrete number (19 buyers, 38 days, 4.2% growth)
- One soft question ("worth a chat?" / "want to know what it could fetch?")
- One sign-off ("— Johan, HFC Coastal")
No paragraphs, no lists, no two numbers competing for attention.

**Constraint 4 — Specific to this seller, this property, this moment.**
Generic = ignored. Every pitch must reference at least one of:
- Their suburb specifically (not "the South Coast")
- Their property size, type, or price band
- A market signal tied to their suburb in the last 30 days

**Constraint 5 — No emoji. No "Dear". No "Hope this finds you well".**
"Hi Steve" or just the first fact. Sign-off is one name + agency. Nothing else. This is WhatsApp, not a letter.

### 1.3 Language policy

**English is the default for every pitch.** Always. No detection, no guessing, no auto-translation.

**The agent can pick another language** from a dropdown on the pitch compose surface. V1 supports: English, Afrikaans. Future: Portuguese, isiZulu, others as agents request.

**No language is suggested by the system.** If the AI guessed "this seller is Afrikaans" and the agent doesn't speak Afrikaans, the reply lands in a language the agent can't handle. That's worse than no pitch at all. The agent knows their client. The system trusts the agent.

When Afrikaans is selected, the AI **generates natively in Afrikaans** from the start — not translated. Cultural register, formality (oom/tannie), and idiomatic phrasing matter. The system prompt for Afrikaans pitches differs from English (more relational warmth, more formal address with older sellers).

---

## 2. The Pitch Hook Library

The AI picks one of six hook types per pitch, based on what the underlying market data actually supports.

### 2.1 The six hooks

**Hook 1 — Buyer demand**
> *"Hi Steve — 19 buyers searching in Lawrence Rocks right now. 3 want exactly your size and price band. Worth a quick chat about what your place could fetch in this market?"*

Triggers when: strong-tier buyer matches ≥ 3 for properties similar to the subject (same suburb + bedroom count + price band ±20%).

Realism anchor required: "in this market" or "what's realistic" — must not promise inflated value.

**Hook 2 — Scarcity**
> *"Hi Steve — only 4 properties like yours listed in Lawrence Rocks right now. Last 3 similar sold in 38 days, averaging 2% under asking. Want to know what yours could realistically fetch?"*

Triggers when: active listing count for this property type + suburb is in the bottom quartile of the 12-month range.

Realism anchor required: the "2% under asking" or "realistically" qualifier.

**Hook 3 — Market trend**
> *"Hi Steve — interest rates dropped 25 basis points last week. Buyer enquiries on Lawrence Rocks listings jumped 18% in 7 days. Properties priced right are moving faster. Curious what yours is worth today?"*

Triggers when: a market signal in the last 14 days (rate change, listing volume change, enquiry volume change in MIC) exceeds 10% movement.

Realism anchor required: "priced right" or equivalent.

**Hook 4 — Neighbour / competitor**
> *"Hi Steve — your neighbour at 125 Marine Drive just listed for R1.2M. 8 buyers are actively looking on your street in that band. If you've ever thought about selling, this is the conversation window."*

Triggers when: a property within 500m of the subject has been listed in the last 21 days (via tracked_properties geo proximity).

Realism anchor: not always required — the neighbour comp acts as its own anchor.

**Hook 5 — Free valuation (the fallback)**
> *"Hi Steve — quick fact: Lawrence Rocks properties have gained 4.2% in value over the last 12 months, slightly above the South Coast average. Curious what your specific place is worth today? Free valuation, no obligation."*

Triggers when: none of hooks 1-4 have strong signal. The "always works" fallback.

Realism anchor: built-in (factual stat, no future projection).

**Hook 6 — Recent sold comp (when fresh data lands)**
> *"Hi Steve — 18 Mitchell Street just sold for R1.45M last week. Similar size, similar street to yours. Curious how that lands against what you think yours is worth?"*

Triggers when: a recent comparable sale (last 30 days, within 500m or same complex) has been parsed from a CMA report or deeds office data.

Realism anchor: built-in (recent actual sale price).

### 2.2 Hook selection logic

The AI doesn't pick randomly. The system constructs a `PitchContext` object with all available data:

```
PitchContext {
  property: { suburb, type, beds, price_band, gps, ... }
  seller_known: { name, prior_interactions, ... }
  buyer_demand: { strong_tier_count, total_tier_count, recent_growth }
  supply: { active_listings_count, historical_avg, scarcity_score }
  trends: { rate_change_14d, enquiry_change_14d, listing_change_14d }
  neighbours: [ { address, list_date, list_price, ... } ]
  recent_sales: [ { address, sale_date, sale_price, ... } ]
  agent: { name, agency, voice_profile_ref, ... }
}
```

Each hook has a **trigger score** (0-100) computed from the context:
- Hook 1 score = function of strong_tier_count, recent growth, freshness
- Hook 2 score = function of supply scarcity vs 12-month baseline
- etc.

The AI receives:
- The full PitchContext
- The trigger scores per hook
- The agency's hook effectiveness ratings (from auto-learning — §5)
- The agent's voice profile

The AI then picks the hook with the **best combined score** (trigger × effectiveness), or uses **multi-armed bandit exploration** (§5.4) 20% of the time to try less-proven hooks.

The hook choice is recorded in `pitch_outcomes` for learning.

### 2.3 Hook composability

A pitch can lead with one hook but reinforce with a fact from another. The AI is encouraged to do this when context supports it. Example:

> *"Hi Steve — your neighbour just listed for R1.2M (hook 4), and we've got 8 buyers actively looking on your street (hook 1). Realistically priced homes here are going in under 45 days. Want a quick conversation?"*

But the AI is instructed: **lead with one hook clearly. Don't pile on three numbers in 40 words.** Pile-on is what makes pitches feel like spam.

---

## 3. The Pitch Compose Surface (Existing — Enhanced)

The compose surface already exists at `/corex/contacts/{id}/outreach/compose` and works on local + demo (per Johan's screenshot 21 May 2026). This spec describes the enhancements only.

### 3.1 What stays

- Property selector at top
- WhatsApp / Email toggle
- Template dropdown (now becomes "AI angle" dropdown — §3.3)
- Message body editor (agent can override AI output)
- Live Demand Facts right panel
- PPRA defensibility audit trail
- Tracking link generation
- Send button

### 3.2 What changes

**Templates → AI angles.** The current "Initial outreach — sale (default)" template dropdown becomes:

```
AI angle: [ Auto-pick (recommended) ▾ ]
  Auto-pick (recommended)
  Buyer demand
  Scarcity
  Market trend
  Neighbour comp
  Free valuation
  Recent sale comp
  ─────────────
  Try a different angle
  Custom (write my own)
```

"Auto-pick" lets the AI choose. The other options force a specific hook.

"Try a different angle" appears as a button below the message body after first generation — instantly re-generates with a different hook.

"Custom" disables AI entirely — agent writes from scratch (existing behaviour preserved).

**Language picker added** next to the AI angle dropdown:

```
Language: [ English ▾ ]
  English
  Afrikaans
```

Default English. Agent picks Afrikaans for known Afrikaans-speaking sellers. No auto-detect.

**Live Demand Facts panel — AI-narrated.**

Currently the panel shows static numbers ("19 buyers in Lawrence Rocks / 0 matching this specific property"). Per mic-complete-spec §4.4, the panel gains an AI-generated summary sentence above the numbers:

```
┌─────────────────────────────────────┐
│  LIVE DEMAND FACTS                  │
│                                     │
│  Demand here is steady — 19 active  │
│  buyers searching Lawrence Rocks,   │
│  but none specifically want a       │
│  3-bed apartment in your band. A    │
│  competitively priced unit would    │
│  attract attention.                 │
│                                     │
│  Buyers in Lawrence Rocks: 19       │
│  Matching this property: 0          │
│  Apartment / 3 beds, similar band   │
└─────────────────────────────────────┘
```

The narrative is generated on compose-page load, cached for 6 hours per (property × agent).

### 3.3 The agent's override flow

After AI generates, the agent sees the draft in the message body. They can:

1. **Send as-is** (most common — trust the AI)
2. **Edit then send** (light touch — change a word, fix tone)
3. **Try different angle** (button — instant re-generation)
4. **Re-write completely** (button — switches to Custom mode)

Every variant the agent sees is recorded. If they pick "Try different angle" 3 times before sending, all 4 attempts are logged (with the AI's hook choice and trigger scores for each) so we learn which generations agents reject.

The actually-sent message is what gets recorded in `pitch_outcomes` for learning. The discarded variants are kept too, marked as "rejected by agent" — they're useful negative signal.

---

## 4. Per-Agent Voice Training

The killer feature. Every agent's AI starts to sound like *them* over time.

### 4.1 The mechanism

A simple, opt-in pipeline:

**Step 1 — Sample collection (passive).**
Every time an agent edits or completely rewrites an AI draft before sending, the system captures:
- The AI's original draft
- The agent's edited version
- The delta (what they changed and why we can infer)

This builds a corpus of "what this agent actually sends" vs "what the AI suggested." Stored in a new table: `agent_message_samples`.

**Step 2 — Voice profile (active extraction).**
Once an agent has 20+ samples, a background job runs (manual trigger for V1, scheduled for V2):
- Feed all 20+ samples to Sonnet 4.6 with a structured prompt
- AI extracts the agent's voice signature: typical opening words, sentence rhythm, formality level, idioms they use, hooks they prefer, things they never say
- Output stored as a structured `agent_voice_profile` JSON blob on the user record

Example voice profile for Johan:
```json
{
  "tone": "warm but business-like",
  "formality": "first-name basis, no titles",
  "openings_preferred": ["Hi {name}", "{Name} — quick one"],
  "openings_avoided": ["Dear", "Good day"],
  "signature_style": "— Johan, HFC Coastal",
  "phrase_signatures": ["worth a chat?", "happy to share more", "no pressure"],
  "hook_preference": ["buyer_demand", "neighbour_comp"],
  "avg_word_count": 38,
  "uses_emoji": false,
  "uses_exclamation": "sparingly",
  "extracted_at": "2026-06-15",
  "samples_count": 47
}
```

**Step 3 — Voice-aware generation.**
When the AI generates a pitch for this agent, the agent's voice profile is injected into the system prompt:

```
SYSTEM: You are drafting a WhatsApp pitch for property agent Johan Reichel
at HFC Coastal. Johan's voice signature:
- Warm but business-like
- First-name basis, never titles
- Typical opening: "Hi {name}" or "{Name} — quick one"
- Signature phrases: "worth a chat?", "happy to share more", "no pressure"
- Never uses emojis
- Average pitch length: 38 words
- Preferred hooks: buyer demand, neighbour comp
- Never says: "Dear", "Good day", "Hope this finds you well"

Generate a pitch following all constraints (under 50 words, demand-with-realism,
one number/one question/one sign-off). Match Johan's voice.
```

The output starts to sound recognisably like Johan. Other agents get their own AI sounding like them.

### 4.2 Opt-in and override

- Agents can **opt out** of voice training (privacy-respecting)
- Agents can **review and edit** their voice profile via Settings
- Agents can **reset** their voice profile to start fresh
- Admins can **see voice profiles** but cannot edit other agents'

### 4.3 The data model

New table: `agent_message_samples`

| Column | Type | Notes |
|---|---|---|
| id | bigint | |
| agency_id | FK | |
| user_id | FK (agent) | |
| ai_draft_text | text | What the AI generated |
| agent_final_text | text | What the agent actually sent |
| was_edited | boolean | |
| edit_distance | int | Levenshtein distance (rough edit volume) |
| was_completely_rewritten | boolean | (agent chose Custom mode) |
| pitch_context_id | FK → pitch_outcomes nullable | Links to the outcome record |
| language | string(10) | en, af, etc. |
| created_at | timestamp | |
| (no updated_at, no soft delete — these are samples, append-only) |

Voice profile stored on users table as a `voice_profile_json` column (or new `agent_voice_profiles` table — implementation choice during build).

---

## 5. Auto-Learning System

How the AI gets sharper over time.

### 5.1 The learning substrate

New table: `pitch_outcomes`

| Column | Type | Notes |
|---|---|---|
| id | bigint | |
| agency_id | FK | |
| user_id | FK (agent who sent) | |
| contact_id | FK | |
| property_id | FK nullable | If linked at time of pitch |
| tracked_property_id | FK nullable | The TP graph node |
| hook_type | string(50) | buyer_demand / scarcity / trend / neighbour / free_valuation / recent_sale |
| was_auto_picked | boolean | true if AI chose, false if agent forced |
| was_exploration | boolean | true if bandit picked sub-optimal for learning |
| message_snapshot | text | Exactly what was sent |
| claimed_facts | json | The numbers we asserted, for later verification |
| pitch_context_snapshot | json | Full PitchContext at send time |
| seller_profile_snapshot | json | What we knew about seller |
| market_context_snapshot | json | Market conditions at send time |
| language | string(10) | |
| sent_at | timestamp | When the pitch went out |
| auto_outcome | enum nullable | win / warm / dead / lost / unknown |
| auto_outcome_at | timestamp nullable | |
| auto_outcome_reason | string nullable | Why we classified it that way |
| agent_feedback | enum nullable | got_meeting / called_back / positive / neutral / no / no_reply |
| agent_feedback_at | timestamp nullable | |
| effectiveness_score | smallint | Calculated, updated as signals come in |
| created_at / updated_at | |

This is the gold mine. Every pitch creates one row. Every outcome updates it.

### 5.2 Outcome classification (automatic)

A scheduled job runs daily and classifies pending outcomes:

| Auto-outcome | Score | Trigger |
|---|---|---|
| **WIN** (full credit) | +10 | Mandate signed against THIS property within 30 days |
| **WIN** (partial credit) | +6 | Mandate signed by THIS contact on a DIFFERENT property within 30 days |
| **WIN** (lower) | +8 | This property promoted to active stock within 60 days |
| **WARM** | +4 | Contact had any logged interaction (call/whatsapp/meeting) within 14 days |
| **COLD** | -2 | Contact explicitly responded negative (via agent feedback) |
| **DEAD** | -1 | No reply, no second pitch successful within 14 days |
| **LOST** | -5 | Property listed by another agency within 90 days (if data available via P24/PP) |
| **UNKNOWN** | 0 | 90 days passed, no clear signal — drop from training set |

The job runs nightly:
1. Find pitches > 7 days old with `auto_outcome IS NULL`
2. Walk the signal table (deals, claims, contact_activity_log, property status changes, P24 listing events)
3. Classify and write back

### 5.3 Agent feedback (optional, weighted lower)

Per Johan's "don't overload agents" principle: agent feedback is **opt-in, one tap, only when it matters**.

The system asks for feedback only when:
- The seller actually replied (system detects this via incoming WhatsApp/email logging, where wired)
- OR 7 days after send if no auto-outcome detected

The prompt appears as a small banner on the contact's timeline:

```
┌─────────────────────────────────────────────────┐
│  The pitch you sent Steve on 21 May —          │
│  what happened?                                 │
│                                                 │
│  [👍 Got meeting] [📞 Called back]              │
│  [💬 Positive] [😐 Neutral]                     │
│  [👎 No] [🤐 No reply] [✕ Skip]                 │
└─────────────────────────────────────────────────┘
```

One tap. No typing. Skippable. Each tap weighted half of auto-outcomes in the learning model (because of selection bias — agents remember the good ones).

### 5.4 The bandit logic

The AI uses **epsilon-greedy multi-armed bandit** selection:

- 80% of the time: pick the hook with the highest combined trigger × effectiveness score
- 20% of the time: pick a less-proven hook at random (weighted by trigger score so we don't fire totally inappropriate hooks)

This is critical. Without exploration, the system locks into local optima. A "weather hook" or "seasonal hook" might be incredibly effective in summer but never gets tested if we always pick the historical winner.

The exploration rate is configurable per agency (default 20%). New agencies start at 30% (no learning yet, more exploration). Mature agencies can drop to 10% (trust the learned model more).

### 5.5 Effectiveness recomputation

Once a month, a job recomputes:

- Per hook type: avg effectiveness, sample size, confidence interval
- Per hook × suburb: same
- Per hook × price band: same
- Per hook × seller age bracket (if ID available): same
- Per hook × agent: same (different agents have different audiences)

Results stored in `pitch_hook_effectiveness` table:

| Column | Type | Notes |
|---|---|---|
| id | bigint | |
| agency_id | FK | |
| hook_type | string(50) | |
| segment_key | string(100) | "global", "suburb:margate", "price:1-2m", "agent:5" |
| segment_value | string(200) | the actual value |
| sample_size | int | |
| avg_score | decimal(5,2) | |
| win_rate | decimal(5,2) | percentage |
| last_recomputed_at | timestamp | |

When the AI picks a hook, it queries this table for the most specific segment match. If no segment-specific data, falls back to global.

### 5.6 Monthly review for admins

A monthly job generates a markdown report for super admins:

```
PITCH EFFECTIVENESS REVIEW — May 2026

This month: 247 pitches sent across HFC Coastal.

TOP-PERFORMING HOOKS:
1. Neighbour comp (47 sends, avg score +5.8, win rate 23%)
2. Scarcity (62 sends, avg score +4.2, win rate 18%)
3. Buyer demand (89 sends, avg score +3.1, win rate 12%)

UNDER-PERFORMING:
- Free valuation fallback (31 sends, avg score +0.4, win rate 3%)
  → Used too often when other hooks had strong signal. Investigate.

BY SUBURB:
- Margate: Scarcity outperforms (avg +6.1)
- Lawrence Rocks: Buyer demand outperforms (avg +4.8)
- Ballito: Recent sale comp dominates (avg +7.2)

BY AGENT:
- Johan: Neighbour comp +6.4 vs agency avg +5.8
- Marie: Scarcity +5.1 vs agency avg +4.2
- New: agents with < 10 pitches not included

RECOMMENDATIONS:
- Increase scarcity weight in Margate
- Decrease free_valuation default — only use when no other hook signal
- Consider new hook variants (Ellie can generate proposals on request)
```

Admin can act on the report or let the bandit logic adjust automatically.

---

## 6. Anti-Overpricing Safeguards

This deserves its own section because it's the single biggest risk in the entire system.

### 6.1 The hard rules (in AI system prompt)

The AI is given these rules verbatim in every pitch prompt:

```
ANTI-OVERPRICING RULES — NON-NEGOTIABLE:

1. NEVER promise the seller their property is worth more than reasonable.
   The goal is to start a conversation, not anchor them high.

2. EVERY demand signal (buyer count, sales velocity, market growth) MUST
   be paired with a realism anchor in the same message. Examples of
   realism anchors:
   - "priced right"
   - "at market"
   - "the homes winning are the ones priced sharply"
   - "realistically priced"
   - "within X% of CMA"

3. NEVER use phrases that imply guaranteed upside:
   - "Properties are flying off the market"
   - "Prices are surging"
   - "You could easily get X million"
   - "Sellers are getting premium prices"

4. IF the property's last known asking price or seller expectation is
   already above CMA mid-range, the pitch MUST include a soft realism nudge:
   "Curious what the market is actually saying about your value today?"

5. NEVER give a specific value estimate in a pitch. Always invite a
   conversation or valuation. Specific numbers belong in the CMA, not
   the pitch.
```

### 6.2 Automated detection of risky pitches

After generation, a quick second pass runs:

- Does the message contain a demand signal without realism anchor? → block, regenerate
- Does it contain banned phrases? → block, regenerate
- Does it propose a specific value? → block, regenerate

If 3 regenerations still fail rules, the pitch is held for agent manual review with a notice: "AI couldn't produce a compliant pitch — please write manually."

This is rare but the safety net matters. Logged for prompt tuning.

### 6.3 Post-send monitoring

The `claimed_facts` JSON field on `pitch_outcomes` records every number the AI asserted. If a seller later complains "your agent told me my property was worth X" — the audit trail proves exactly what was claimed.

Per the PPRA defensibility framing already in the compose UI: every send is logged with snapshot of underlying data. The audit is bulletproof.

---

## 7. Integration with Existing Systems

### 7.1 MIC

The pitch system reads from MIC data (via existing services):
- `PropertyMatchScoringService` for buyer counts
- `MarketDataPoint` for suburb stats (Phase A1 tables)
- `OpportunityPocketService` for trend signals
- `TrackedProperty.primaryAddress` for neighbour proximity (Phase A1 address history)

No new MIC queries needed. The PitchContext assembly service consumes existing services.

### 7.2 Ellie (Python service at /opt/hf-ai/app.py)

Ellie and the pitch generator are **separate concerns** but share data:

- Pitch generator uses `AnthropicGateway` (Laravel side, this codebase)
- Ellie uses her own Python service (Sonnet 4.6 with RAG via embeddings)
- Both write to `agent_activity_events` when generating content
- Both update `ai_narrative_cache` for shared cost tracking
- Future: Ellie can query pitch_outcomes table to answer "what hooks work for me?"
- Future: pitch generator can call Ellie for context-aware enrichment

V1 build: keep separate, don't cross-integrate. Document the seam clearly so future bridges are clean.

### 7.3 Compose surface (existing)

`/corex/contacts/{id}/outreach/compose` enhancements:
- AI angle dropdown replaces template dropdown
- Language picker added
- Live Demand Facts gains AI narrative
- "Try different angle" button below message body
- Voice profile feeds into AI prompt invisibly

Implementation: existing `ComposerController` gets a new dependency injection for `PitchGeneratorService`. Existing UI stays substantially the same.

### 7.4 Contact pillar

The pitch outcomes link to contacts. Future surfaces (contact detail page) will show:
- Pitches sent to this contact (timeline view)
- Outcomes per pitch
- Agent's effectiveness with this contact

Out of V1 scope but data structure supports it.

### 7.5 Calendar (when built)

When a pitch results in a "got meeting" outcome, the agent gets a one-tap path to create the calendar appointment with the seller. Out of V1 scope but the `agent_activity_events` log captures the meeting trigger.

---

## 8. Cost Model

### 8.1 Per-pitch cost (typical)

- System prompt: ~600 tokens (cached after first hit = effectively free)
- PitchContext + voice profile injection: ~400 tokens
- User prompt: ~200 tokens
- Output: ~80 tokens (50-word pitch + slight overhead)

**Per pitch on Sonnet 4.6:**
- Input: 1,200 tokens × $3/MTok = $0.0036
- Output: 80 tokens × $15/MTok = $0.0012
- **Total: $0.005 per pitch ≈ R0.083 (~8 cents)**

With prompt caching on system prompt (after first hit):
- **~R0.04 per pitch (~4 cents)**

### 8.2 Per-agency monthly cost

At HFC's typical volume (8 agents × ~20 pitches/agent/month = 160 pitches/month):

| Cost type | Amount |
|---|---|
| Pitch generation | 160 × R0.04 = R6.40 |
| Live Demand Facts narratives | ~30 generations × R0.05 = R1.50 |
| Voice profile extraction (monthly per agent) | 8 × R0.20 = R1.60 |
| Hook effectiveness recompute | 1 × R0.50 = R0.50 |
| Monthly admin review | 1 × R0.30 = R0.30 |
| **Total monthly cost** | **~R10.30** |

Yes, ten rand. The entire AI communication system. Per agency. Per month.

### 8.3 Scaling consideration

At 100 agents × 50 pitches/agent/month = 5,000 pitches/month:
- ~R200/month per agency
- Still trivial relative to value generated

Cost is genuinely not the constraint. Quality and outcomes are.

---

## 9. Data Model Summary

New tables (this spec):
- `pitch_outcomes` — the learning substrate
- `pitch_hook_effectiveness` — running effectiveness ratings
- `agent_message_samples` — voice training corpus

Modified tables:
- `users` — add `voice_profile_json` column (or new related table — build decision)

Existing tables used (from MIC spec / earlier):
- `tracked_properties` + `tracked_property_addresses`
- `market_data_points`
- `contact_matches` + `prospecting_buyer_matches`
- `properties`, `contacts`
- `ai_narrative_cache` (for the Live Demand Facts narrative)
- `agent_activity_events` (every pitch fires events)

---

## 10. Build Sequence

### Phase W1 — Data model (half day)

- Create `pitch_outcomes` migration + model
- Create `pitch_hook_effectiveness` migration + model
- Create `agent_message_samples` migration + model
- Add `voice_profile_json` column to users
- Domain events: PitchGenerated, PitchSent, PitchOutcomeClassified, AgentVoiceProfileUpdated

### Phase W2 — PitchContext + Hook services (1 day)

- `PitchContextBuilder` — assembles all the data
- `HookSelector` — computes trigger scores per hook
- `HookEffectivenessRepository` — queries effectiveness table
- `EpsilonGreedyBandit` — selection logic with exploration

### Phase W3 — Pitch generator (1 day)

- `PitchGeneratorService` — orchestrates context, hook, voice, AI call
- System prompts per language (English, Afrikaans)
- Anti-overpricing safety filter
- Wires into AnthropicGateway

### Phase W4 — Compose UI enhancements (1 day)

- AI angle dropdown
- Language picker
- "Try different angle" button
- Live Demand Facts narrative
- Voice profile injection invisible to user

### Phase W5 — Outcome classification (half day)

- Scheduled job: classify pending pitches nightly
- Agent feedback prompt UI
- Manual feedback recording endpoint

### Phase W6 — Voice training (1 day)

- Sample collection on every pitch edit
- Voice profile extraction job (manual trigger V1)
- Voice profile review UI in agent settings
- Voice injection into AnthropicGateway calls

### Phase W7 — Effectiveness reporting (half day)

- Monthly effectiveness recompute job
- Admin review markdown generator
- Settings page to view ratings

### Phase W8 — Demo seed + verification (half day)

- Demo seeder creates realistic pitches across all hooks for known properties
- End-to-end test: agent opens compose → AI generates → agent sends → outcome classifies → effectiveness updates

**Total: ~6 working days. Could compress to 3-4 days with both Johan and Andre in parallel.**

---

## 11. Verification Checklist

### 11.1 Generation

- [ ] Open compose surface for a known contact + property
- [ ] AI angle dropdown shows all 6 hooks + Auto
- [ ] Language picker shows English + Afrikaans
- [ ] "Auto" generates a pitch that respects all 5 constraints
- [ ] Generated pitch is under 50 words
- [ ] Demand signal (if used) has realism anchor
- [ ] No banned phrases
- [ ] Sign-off matches agent's profile

### 11.2 Override

- [ ] Force-pick "Scarcity" — gets scarcity hook
- [ ] "Try different angle" generates with different hook
- [ ] Custom mode disables AI

### 11.3 Anti-overpricing

- [ ] Inject a test PitchContext designed to trigger overpricing risk
- [ ] AI cannot produce non-compliant output (safety filter blocks)
- [ ] 3 regenerations fail → falls back to manual mode

### 11.4 Voice training

- [ ] Edit an AI draft heavily, send
- [ ] Check `agent_message_samples` — row landed
- [ ] After 20 samples for an agent, run voice extraction manually
- [ ] Voice profile JSON populated on user
- [ ] Next pitch for this agent uses voice profile in system prompt

### 11.5 Outcome classification

- [ ] Send a pitch
- [ ] Manually create a "mandate signed" event for the same contact + property
- [ ] Run classification job
- [ ] `pitch_outcomes.auto_outcome` = 'win', score = 10

### 11.6 Bandit exploration

- [ ] Generate 100 pitches with high-effectiveness hook dominant
- [ ] Verify ~20% of pitches use exploration hook

### 11.7 Cost tracking

- [ ] Every pitch generation writes to `ai_narrative_cache`
- [ ] Cost in ZAR matches expected ~R0.04-0.08 per pitch
- [ ] Monthly cost aggregation matches projection

---

## 12. Future Enhancements (architectural notes, not V1 scope)

### 12.1 Multi-language expansion
isiZulu, Portuguese, Sesotho — add when agents request and Claude quality verifies.

### 12.2 Voice profile sharing
Agents can "borrow" a high-performing colleague's voice profile as a starting template.

### 12.3 Pitch-to-listing-content reuse
The voice profiles built from WhatsApp pitches can drive listing description AI later (per mic-complete-spec §15.3).

### 12.4 Seller-side intelligence
Track which sellers responded to which hooks. Build a per-seller communication profile to inform future agent interactions with the same contact.

### 12.5 Email parity
Same hooks, same effectiveness tracking, same voice — but longer-form for email pitches. Mostly same code, different output constraints.

### 12.6 Two-way integration (when API arrives)
When WhatsApp Business API is justified financially, incoming replies route to CoreX timeline automatically and trigger outcome classification without agent input.

---

## Appendix A: Sample Hook Prompts

### A.1 Hook 1 — Buyer demand (English)

```
SYSTEM: You write WhatsApp prospecting pitches for South African real estate
agents. Strict rules:
- Under 50 words
- One number, one question, one sign-off
- Demand signals MUST be paired with a realism anchor
- No emoji, no "Dear", no "Hope this finds you well"
- Sign-off: "— {agent.first_name}, {agency.name}"

ANTI-OVERPRICING RULES (NON-NEGOTIABLE):
- Never promise inflated value
- Every demand mention needs a realism anchor
- Never propose a specific value
- Banned phrases: "flying off market", "surging prices", "premium prices"

AGENT VOICE:
{voice_profile_json}

USER: Generate a WhatsApp pitch.

Context:
- Seller first name: {seller.first_name}
- Property: {property.summary} (suburb: {property.suburb})
- Strong-tier buyer count for similar properties: {context.strong_tier_count}
- Total matching buyers: {context.total_tier_count}
- Recent demand growth: {context.demand_growth_14d}%

Hook to use: BUYER_DEMAND.

Lead with the buyer count. Pair with a realism anchor about pricing. End
with a soft question. Match agent voice.
```

### A.2 Hook 2 — Scarcity (Afrikaans)

```
SYSTEM: Jy skryf WhatsApp boodskappe vir Suid-Afrikaanse eiendomsagente in
Afrikaans. Strenge reëls:
- Onder 50 woorde
- Een getal, een vraag, een afgesluiting
- Vraagseine (demand) MOET met 'n realisme-anker gepaard gaan
- Geen emoji nie, geen "Geagte" nie, geen "Hoop dit gaan goed met u" nie
- Afgesluiting: "— {agent.first_name}, {agency.name}"

ANTI-OORPRYSING REËLS (NIE-ONDERHANDELBAAR):
- Belowe nooit opgeblase waarde nie
- Elke vraag-vermelding benodig 'n realisme-anker
- Stel nooit 'n spesifieke waarde voor nie
- Verbode frases: "vlieg van die mark af", "stygende pryse", "premium pryse"

AGENT STEM:
{voice_profile_json}

GEBRUIKER: Skep 'n WhatsApp boodskap.

Konteks:
- Verkoper voornaam: {seller.first_name}
- Eiendom: {property.summary} (voorstad: {property.suburb})
- Aktiewe lysings van soortgelyke eiendomme: {context.active_listings_count}
- Skaarsheid-telling: {context.scarcity_score}/100

Haak om te gebruik: SKAARSHEID.

Lei met die lae aanbod-getal. Paar met 'n realisme-anker oor prysing. Sluit
af met 'n sagte vraag. Pas agent se stem aan.
```

---

## Appendix B: Outcome Classification Decision Tree

```
For each pitch where auto_outcome IS NULL AND sent_at > 7 days ago:

1. Check deals table:
   - Mandate signed by contact_id on property_id in last 30 days?
     → WIN (+10)
   - Mandate signed by contact_id on ANY property in last 30 days?
     → WIN partial (+6)

2. Check property status changes:
   - property_id moved to 'active' in last 60 days?
     → WIN (+8) (if mandate not detected above)

3. Check contact_activity_log:
   - Any call/whatsapp/meeting logged for contact_id since pitch sent?
     → WARM (+4)

4. Check agent_feedback:
   - Any feedback recorded for this pitch?
     → use that (weighted half)

5. Check competitor listings (if data available):
   - This property listed on P24/PP by another agency in last 90 days?
     → LOST (-5)

6. Default after 90 days no signal:
   → UNKNOWN (0) — exclude from learning

7. Update pitch_outcomes.auto_outcome, auto_outcome_at, effectiveness_score
8. Trigger pitch_hook_effectiveness recompute (lazy — daily aggregation)
```

---

**End of specification.**

**Approval:** Johan Reichel — implicit, per instruction to drive without spec re-reads.
**Implementation start:** After current MIC build phases complete (Phase B+ first).
**Build sequence reference:** §10 of this spec.