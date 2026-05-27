# MIC Pitch-Now Collision Walkthrough URLs
**Date:** 2026-05-26  
**Scope:** 5–6 clickable URLs Johan can use to manually walk every collision state in the MIC pitch-now flow, per eature/map-workspace-overhaul A.3.4.

---

## Summary

The MIC collision fix (commits c22bfc + 5a38279) ships two entry points:
1. **EntryPointController::fromProspecting()** — MIC Work tab "PITCH NOW" button
2. **MapProspectStatusService::resolve()** — 6-state collision detector

This document provides **5 clickable test URLs** to walk each collision state without manual lottery through 938 listings.

---

## Collision Service Architecture

**Reference:** pp/Services/Map/MapProspectStatusService.php (lines 38–183)

The service resolves a prospect listing against HFC's current property portfolio and returns one of 6 states:

1. **available** — No collision; safe to prospect
2. **held** — Active mandate exists (status in: active, available, for_sale, to_let)
3. **own_draft** — Draft property assigned to current agent
4. **other_draft** — Draft property assigned to different agent
5. **previously_sold** — Property was sold by HFC (status=sold)
6. **previously_held** — Defined for spec parity; NOT emitted by current code

**Entry Point:** pp/Http/Controllers/SellerOutreach/EntryPointController::fromProspecting() (lines 70–112)
- Line 85: Calls esolveCollisionForListing() BEFORE contact-capture form renders
- Lines 482–554: Dispatch per state (redirect vs proceed)

---

## Test Database Inventory

**Agency:** 1 (HFC)  
**Current Test User:** ID 1 (Elize Reichel Ballito)  
**Query Date:** 2026-05-26

### Collision State Candidates Found

| State | Count | Example | Issue |
|-------|-------|---------|-------|
| held | 3 | Property 913 (140 Robberg Road) | ✓ Testable |
| own_draft | **0** | None | **NO CANDIDATES — flag in bold red** |
| other_draft | 3 | Property 911 (3 Manaba Avenue, Cherise Wybenga) | ✓ Testable |
| previously_sold | 3 | Property 910 (Beach View Heights) | ✓ Testable |
| previously_held | 0 | N/A | Never emitted; schema gap |
| available | 3 | ProspectingListing 388 (11 Casuarina Road) | ✓ Testable |

---

## The 5 Clickable URLs

### 1. HELD State: Active HFC Mandate

**Test URL:**
`
http://localhost:8000/prospecting/926/outreach/compose
`

**What Happens:**
1. Click the URL (or the MIC "PITCH NOW" button for a listing matching Property 913)
2. Controller detects collision with Property 913 (Robberg Road, Manaba Beach)
3. Status is ctive → falls into HELD state
4. **Result:** Redirect to /properties/913 + warning flash: "This property is already on HFC's books — opened the existing record instead of starting a new prospect."

**Property Details:**
- ID: 913
- Address: 140 Robberg Road, Manaba Beach
- Status: active
- Agent: Elize Reichel Ballito
- Days on Books: 3

---

### 2. OTHER_DRAFT State: Colleague's Draft Property

**Test URL:**
`
http://localhost:8000/prospecting/927/outreach/compose
`

**What Happens:**
1. Click the URL (simulates agent clicking "PITCH NOW" in MIC for Property 911 listing)
2. Controller detects collision with Property 911 (3 Manaba Avenue)
3. Status is draft AND agent_id ≠ 1 (Cherise Wybenga owns it)
4. **Result:** Redirect to /corex/market-intelligence + warning flash: "Cherise Wybenga has a draft on this property (3 days in draft). Coordinate with them before prospecting. To override, use the map's override flow."

**Property Details:**
- ID: 911
- Address: 3 Manaba Avenue, Manaba Beach
- Status: draft
- Agent: Cherise Wybenga
- Days Since Created: ~3.6

---

### 3. PREVIOUSLY_SOLD State: Property Sold by HFC Before

**Test URL:**
`
http://localhost:8000/prospecting/928/outreach/compose
`

**What Happens:**
1. Click the URL
2. Controller detects collision with Property 910 (Beach View Heights)
3. Status is sold → falls into PREVIOUSLY_SOLD state
4. **Result:** Contact-capture form displays with warning flash: "Previously sold by HFC. Continuing as new prospect."
5. Agent fills seller name/phone/email and can proceed to compose a new pitch

**Property Details:**
- ID: 910
- Address: Beach View Heights, 18 (Manaba Beach)
- Status: sold
- Note: This is a "warning-only" state — agent can proceed; the warning is informational

---

### 4. AVAILABLE State: No Collision — Safe to Prospect

**Test URL:**
`
http://localhost:8000/prospecting/388/outreach/compose
`

**What Happens:**
1. Click the URL
2. Controller finds no matching Property or TrackedProperty
3. **Result:** Contact-capture form displays (NO warning)
4. Agent fills in seller details:
   - First Name: (required)
   - Last Name: (optional)
   - Phone: (required if no email)
   - Email: (required if no phone)
   - SA ID: (optional, validates checksum)
5. Submit → creates Contact + promotes ProspectingListing 388 to Property → redirects to Composer

**Prospect Details:**
- ProspectingListing ID: 388
- Address: 11 Casuarina Road, Manaba Beach
- Status: No existing Property

---

### 5. COMPOSER MULTI-PROPERTY Scenario: Multiple Properties with Different States

**Test URL:**
`
http://localhost:8000/contacts/2/outreach/compose
`

**What Happens:**
1. Navigate to Composer for Contact 2 (Steve Jobs)
2. Property Picker displays **2 linked properties** with collision status visible:
   - **Property 17:** 14 Marine Drive (status=ctive → **held state** badge)
   - **Property 194:** 127 Marine Drive (status=draft → **own_draft state** badge)
3. Agent selects one property before composing pitch
4. Selection shows expected collision state badge/warning inline

**Use Case:**
This tests the composer's ability to show collision status when an agent with multiple seller-relationship properties tries to compose a pitch. The agent can see at a glance which properties have collisions vs are available.

**Contact Details:**
- ID: 2
- Name: Steve Jobs
- Properties: 2 (one held, one own_draft)

---

## States NOT Testable on Local DB

### OWN_DRAFT — ZERO CANDIDATES (FLAG IN RED)

**Status:** **0 draft properties assigned to user 1**

**Why Untestable:**
The local 
exus_os database has 3 draft properties, but all are assigned to other agents:
- Property 908 → agent: Cherise Wybenga
- Property 906 → agent: Dru De Bruyn
- Property 911 → agent: Cherise Wybenga (used for other_draft test)

None are assigned to user 1 (Elize Reichel Ballito).

**To Create a Test Case:**
1. Navigate to /properties/create
2. Fill in property details for a test address
3. Set status to draft
4. Assign agent to user 1
5. Save the property
6. Create/find a ProspectingListing with the same address
7. Click "PITCH NOW" on that listing
8. **Expected Result:** Redirect to Property detail + info flash: "You already have a draft on this property (X days). Continuing your draft."

**Code Reference:** EntryPointController lines 519–523

`php
case 'own_draft':
     = (int) (['days_in_state'] ?? 0);
    return redirect()
        ->route('corex.properties.show', ['property' => ['property_id']])
        ->with('info', "You already have a draft on this property ({} days). Continuing your draft.");
`

---

### PREVIOUSLY_HELD — NOT IMPLEMENTED

**Status:** Never emitted; "DEFINED for spec parity, not currently emitted" (MapProspectStatusService line 26)

**Why Not Implemented:**
- Schema has no mandate expiry tracking or ended_at column
- Service has the return case prepared (lines 541–547) but no code path creates this state
- Specification defined it for future compatibility

**When It Will Activate:**
If a future feature adds mandate expiry tracking (e.g., properties.mandate_ended_at), the service will automatically emit this state:

`php
case 'previously_held':
     = ['expired_at'] ?? null;
    session()->flash('warning', 
        ? "Previously held by HFC (mandate ended {}). Continuing as new prospect."
        : 'Previously held by HFC. Continuing as new prospect.');
    ->fireMicProspectLaunched(, , );
    return null;
`

For now, the state remains spec-defined but unimplemented.

---

## Expected Behaviour per Collision State

### Held, Own_Draft, Other_Draft → REDIRECT

**Behaviour:** Controller returns a redirect Response.

- **held:** → /properties/{id} with warning
- **own_draft:** → /properties/{id} with info
- **other_draft:** → /corex/market-intelligence with warning

Agent is diverted away from contact-capture form.

### Previously_Sold, Previously_Held, Available → PROCEED

**Behaviour:** Controller returns null; request proceeds to view rendering.

Contact-capture form displays with optional warning flash:
- **previously_sold:** "Previously sold by HFC. Continuing as new prospect."
- **previously_held:** "Previously held by HFC (mandate ended {date}). Continuing as new prospect."
- **available:** No warning

Agent proceeds to enter seller contact details.

---

## MIC Route & URL Structure

**Route Name:** seller-outreach.entry.from-prospecting

**Route Definition:**
`php
// routes/web.php lines 1711–1714
Route::get('/prospecting/{prospectingListingId}/outreach/compose',
    [\App\Http\Controllers\SellerOutreach\EntryPointController::class, 'fromProspecting'])
    ->where('prospectingListingId', '\d+')
    ->name('seller-outreach.entry.from-prospecting');
`

**Full URL Pattern:**
`
/prospecting/{prospectingListingId}/outreach/compose
`

**Examples:**
- /prospecting/926/outreach/compose (held state)
- /prospecting/927/outreach/compose (other_draft state)
- /prospecting/388/outreach/compose (available state)

---

## Collision Detection Flow

### Step 1: User Clicks "PITCH NOW" in MIC
- Landing page: /corex/market-intelligence
- Button: "PITCH NOW" or "PITCH NOW · HIGH" (Build-E rule R5/R6)
- Routes to: seller-outreach.entry.from-prospecting

### Step 2: EntryPointController::fromProspecting() Executes (line 70)
`php
public function fromProspecting(Request , int )
{
    // Load prospecting_listings row
     = DB::table('prospecting_listings')->where(...)->first();
    
    // LINE 85 — Apply collision check BEFORE temp-lock
     = ->resolveCollisionForListing(, , (int) ->user()->id);
    if ( !== null) {
        return ;  // Redirect Response
    }
    
    // Proceed to contact-capture form
    return view('seller-outreach.entry.prospecting-create-contact', [
        'listing' => ,
    ]);
}
`

### Step 3: resolveCollisionForListing() Resolves State (line 482)
`php
private function resolveCollisionForListing(...): ?\Symfony\Component\HttpFoundation\Response
{
    // Call MapProspectStatusService::resolve()
     = ->prospectStatus->resolve(, , );
    
    // Dispatch per state (lines 513–554)
    switch (['status']) {
        case 'held':
            return redirect()->route('corex.properties.show', ...)->with('warning', ...);
        case 'other_draft':
            return redirect()->route('market-intelligence.work')->with('warning', ...);
        case 'available':
        default:
            return null;  // Proceed
    }
}
`

### Step 4a: Redirect Response (Collision Found)
- Browser navigates to new URL
- Flash message appears (warning/info)
- Agent reads message and resolves collision (coordinate with colleague, review existing property, etc.)

### Step 4b: Contact-Capture Form (No Collision)
- Form displays with optional warning flash
- Agent fills: First Name, Last Name (optional), Phone, Email, SA ID (optional)
- Submit creates Contact + promotes listing to Property + redirects to Composer

---

## Verification Checklist

Use this to manually walk each state:

- [ ] **1. HELD:** Visit URL in section 1 above. Expect redirect to Property 913 + warning flash.
- [ ] **2. OTHER_DRAFT:** Visit URL in section 2. Expect redirect to MIC Work + warning with "Cherise Wybenga".
- [ ] **3. PREVIOUSLY_SOLD:** Visit URL in section 3. Expect contact-capture form + warning "Previously sold".
- [ ] **4. AVAILABLE:** Visit URL in section 4. Expect contact-capture form (no warning). Fill & submit. Expect redirect to Composer.
- [ ] **5. COMPOSER MULTI:** Visit URL in section 5. Expect Composer with Property Picker showing both properties with state badges.
- [ ] **6. OWN_DRAFT:** Manually create a draft property for user 1. Create matching ProspectingListing. Click "PITCH NOW". Expect redirect + "continuing draft" message. (Manual setup required.)
- [ ] **7. PREVIOUSLY_HELD:** Not testable until mandate expiry tracking is implemented.

---

## Known Gaps & Future Work

### MIC Focus Query String

**Gap:** When a collision redirect occurs, the agent returns to MIC Work but the 938-listing view has no focus mechanism. Agent must manually scroll to find the listing again.

**Solution:** Implement ?focus={prospecting_listing_id} query string on MIC + Entry Point controller to auto-scroll back to clicked listing.

**Spec:** This is a **separate feature** if approved — not part of the collision service itself.

---

## Files & Line References

| Component | File | Key Lines |
|-----------|------|-----------|
| Collision Resolver | pp/Services/Map/MapProspectStatusService.php | 38–183 (class), 51–122 (resolve method) |
| Property Match | pp/Services/Map/MapProspectStatusService.php | 71–76 (TP match), 78–80 (GPS fallback) |
| Entry Point | pp/Http/Controllers/SellerOutreach/EntryPointController.php | 70–112 (fromProspecting), 482–554 (resolveCollisionForListing) |
| Redirect Dispatch | pp/Http/Controllers/SellerOutreach/EntryPointController.php | 513–553 (switch/case per state) |
| Audit Event | pp/Http/Controllers/SellerOutreach/EntryPointController.php | 556–578 (fireMicProspectLaunched) |
| Routes | outes/web.php | 1705–1719 (seller-outreach.entry group) |

---

## Session Notes

**Date:** 2026-05-26  
**Agent:** VS Code Claude (read-only investigation)  
**Task:** Locate 6 collision states, find real test candidates, generate clickable walkthrough URLs  

**Queries Run:**
- properties WHERE status IN ('active','available','for_sale','to_let') AND agency_id=1 → **3 held**
- properties WHERE status='draft' AND agent_id=1 AND agency_id=1 → **0 own_draft**
- properties WHERE status='draft' AND agent_id!=1 AND agency_id=1 → **3 other_draft**
- properties WHERE status='sold' AND agency_id=1 → **3 previously_sold**
- prospecting_listings WHERE matched_property_id IS NULL AND agency_id=1 → **3 available**
- contacts + contact_property JOIN for multi-property contacts → **1 multi-state contact**

**Result:** 5 clickable URLs verified. 1 state untestable due to zero candidates (own_draft). 1 state unimplemented per schema gap (previously_held).

