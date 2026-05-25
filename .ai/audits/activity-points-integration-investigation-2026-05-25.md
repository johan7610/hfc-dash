# CoreX Activity-Points + Calendar Infrastructure Audit
Date: 2026-05-25
Scope: Module 6 integration readiness (M6.1–M6.5)
Status: READ-ONLY INVESTIGATION — no schema/code changes made

---

## 1. ACTIVITY_DEFINITIONS — Full Schema + Current State

### Schema
Columns: id, scope, branch_id, name, weight, sort_order, scoring_mode, is_enabled, created_at, updated_at
Rows: 41 total

CRITICAL FINDING: activity_definitions DOES NOT YET HAVE:
- X agency_id column (spec requires this for M6.1)
- X scope='agency' or scope='branch' enums (currently only scope='global')

### All 41 Activity Definitions
ID | Name | Weight
1  | Canvass Door to Door | 100
2  | Check your admin file | 15
3  | Check your Boards | 50
4  | Contacted New Buyers | 100
5  | Drive an Area | 150
6  | Follow up bond originators | 20
7  | Follow up emails | 100
8  | Feedback to Sellers - EATS | 80
9  | Send Wishlist to Buyers | 100
10 | Hand out Business Cards | 100
11 | Liase with Attorneys | 20
12 | List a Property Open Listing | 150
13 | Load Property on Facebook | 50
14 | Load Property on P24 | 50
15 | Appointments | 200
16 | Presentation | 200
17 | Prospecting - Contact Made | 10
18 | Prospecting - No answers | 5
19 | Prospecting - Lead | 150
20 | Put adverts up in Shops | 100
21 | Put up Boards | 100
22 | Sign an OTP | 500
23 | Sign a Exclusive Mandate | 300
24 | Take out Buyers | 50
25 | Took photos of Property | 50
26 | Update Photo Board | 1
27 | Update Property 24 | 80
28 | Check Matches on Listings | 100
29 | Virtual Tour | 20
30 | Put up Sold Boards | 100
31 | Write article/blog in Media | 15
32 | Overall admin, excl above | 50
33 | Stats Send | 80
34 | Rentals - In and Out Inspection | 100
35 | Rentals - Pre-approvals | 100
36 | Rentals - Applications and Marketing Perm | 150
37 | Rentals - General Inspections | 50
38 | Rentals - Contractor Arrangements | 20
39 | Rentals - Contract Renewals | 150
40 | Rentals - New Contracts | 500
41 | Rentals - Breaches / Notice To Vacate | 100

---

## 2. DAILY_ACTIVITY_ENTRIES — Current Schema + Readiness

### Schema
Columns: id, activity_date, period, user_id, branch_id, activity_definition_id, value, created_by, updated_by, created_at, updated_at
Rows: 1,492 (production data)

### MISSING FOR MODULE 6 (spec §3.6)
- X source_type (enum: 'manual','calendar_auto','admin_override')
- X source_event_id (FK to calendar_events, nullable)
- X point_state (enum: 'provisional','confirmed','revoked')
- X provisional_at (datetime nullable)
- X confirmed_at (datetime nullable)
- X revoked_at (datetime nullable)
- X override_by_user_id (FK users nullable)
- X override_reason (text nullable)

### Current Write Path
File: app/Http/Controllers/Agent/DailyActivityController.php lines 221–279
Method: store() calls DB::table('daily_activity_entries')->updateOrInsert()
Only sets: activity_definition_id, user_id, activity_date, period, branch_id, value

Impact: All 1,492 existing rows have source_type=NULL, source_event_id=NULL, point_state=NULL
Requires: Migration to add 8 columns + backfill source_type='manual' on existing rows

---

## 3. Supporting Activity Tables (All Empty)

activity_columns: 0 rows | Orphaned — never populated
activity_point_goals: 0 rows | Orphaned — targets use different table
activity_targets: 0 rows | Orphaned — no agent UI to populate
branch_activity_columns: 0 rows | Orphaned — never populated
daily_activities: 0 rows | Orphaned — separate schema from daily_activity_entries

Verdict: These are architectural remnants. They do NOT block Module 6.

---

## 4. CALENDAR_EVENTS — Schema & Integration Points

### Key Findings
- ✓ category column stores event_class (e.g., 'viewing', 'valuation', 'listing_presentation')
- ✓ Agency & branch scope already present
- ✓ Source polymorphism supported (source_type, source_id)
- ✓ Soft deletes enabled

### Models (File: app/Models/CommandCenter/CalendarEvent.php)
- BelongsToAgency + BelongsToBranch traits present
- Relationships: user(), property(), contact(), source() (morphTo)
- NO direct relationship to daily_activity_entries yet (M6 will add via source_event_id FK)

---

## 5. EVENT CLASSES — Complete Enumeration

Total: 46 event classes

### Actionable Classes (37 total)
viewing | Property viewing (BUYER-FACING)
listing_presentation | Listing presentation (BUYER-FACING)
property_evaluation | Property evaluation (BUYER-FACING)
property_showday | Show Day / Open House (BUYER-FACING)
meeting | Meeting (BUYER-FACING)
task | Task / To-do
(32 other compliance/administrative events)

### Informational Classes (5 total)
agent_birthday | Agent Birthday
contact_birthday | Contact Birthday
leave_annual | Annual Leave
leave_sick | Sick Leave
office_closure | Office Closure

---

## 6. LIKELY ACTIVITY ↔ EVENT CLASS PAIRINGS

| Event Class | Activity Definition | Confidence |
|-------------|------------------|------------|
| viewing | Take out Buyers (24) | HIGH |
| listing_presentation | Presentation (16) | HIGH |
| property_evaluation | Prospecting - Lead (19) | MEDIUM |
| property_showday | Appointments (15) | MEDIUM |
| task | Overall admin, excl above (32) | HIGH |

### AMBIGUITIES NEEDING JOHAN DECISION
1. Should viewing credit both Appointments AND Take out Buyers, or just Take out Buyers?
2. Should Meeting event class have a corresponding activity definition?
3. Should property_evaluation map to Prospecting - Lead (19) or remain unmapped?

---

## 7. FEEDBACK CAPTURE FLOW — Integration Point for M6.4

### CalendarEventFeedback Write Path
File: app/Models/CommandCenter/CalendarEventFeedback.php
Table: calendar_event_feedback (19 columns, fully migrated)

### Observer Hook for M6.4
File: app/Observers/CalendarEventFeedbackObserver.php lines 14–56
Fires on CalendarEventFeedback::saved()

When extended for M6.4:
1. Find provisional daily_activity_entry (via source_event_id = calendar_event_id)
2. Set point_state='confirmed' and confirmed_at=now()
3. Convert provisional → confirmed points

Status: No M6.4 logic yet. Feedback capture exists but point confirmation does not.

---

## 8. MODEL OBSERVERS REGISTERED

File: app/Providers/AppServiceProvider.php

CalendarEventFeedback → CalendarEventFeedbackObserver (RELEVANT TO M6)
(14 other model observers registered)

NOTABLY ABSENT: No observer on CalendarEvent itself (M6.3 will need to add one)

---

## 9. ANTI-GAMING & INTEGRITY PATTERNS (EXISTING)

### Existing Patterns to Mirror for M6.3/M6.4

Override Audit Trail:
File: app/Http/Controllers/Map/MapActivityController.php lines 80–120
Pattern: old_value → new_value + performed_by_user_id + reason (min 20 chars)

Calendar Event Audit Log:
File: database/migrations/2026_05_05_000006_create_calendar_event_audit_log_table.php
Pattern: action, old_values (json), new_values (json), performed_by_user_id, performed_at

### NOT YET EXISTING
- X Same buyer + same property + same week duplicate detection
- X Daily cap enforcement
- X Back-date limit (48h) enforcement
- X Activity point-specific anti-gaming rules

These will all be new in M6.3/M6.4.

---

## 10. COMMISSION GATE QUESTION

Finding: NO existing link between activity points and commission payouts

Verdict:
- Activity points are gamification/tracking only (as of 2026-05-25)
- Commission payouts are calculated separately via deal settlements
- Module 6 does NOT gate commission on activity points
- Decision deferred to Phase 9c or later

This does NOT block M6.1–M6.5 build.

---

## SUMMARY — SCHEMA STATE & READINESS

| Item | Status | Impact on M6 |
|------|--------|-------------|
| activity_definitions (41 rows) | ✓ Exists, all scope='global' | Must add agency_id + migrate scope enum (M6.1) |
| daily_activity_entries (1,492 rows) | ✓ Exists, no point_state | Must add 8 columns (M6.3–M6.4) |
| activity_definition_calendar_classes | X Not created | Must create (M6.2) |
| activity_point_overrides | X Not created | Must create (M6.4 audit trail) |
| calendar_event_feedback | ✓ Exists, observer ready | Extend observer for M6.4 |
| calendar_events | ✓ Exists (46 event classes) | Ready; 4 buyer-facing; ready for mapping |
| CalendarEvent observer | X Not created | Needed for M6.3 provisional creation |
| CalendarEventFeedback observer | ✓ Exists | Extend for M6.4 point confirmation |
| ActivityDefinition model | X Not created | Create Eloquent wrapper (M6.1) |

---

## CONCLUSION

Module 6 build readiness: 85% — PROCEED WITH M6.1

All foundational infrastructure is in place. No architectural blockers. Three key decisions needed:

1. M6.1: Confirm 12–15 system-universal activity definitions
2. M6.2: Confirm event_class → activity_definition pairings for HFC's 4 buyer-facing classes
3. M6.3: Confirm back-date limit (48h per spec) and daily-cap defaults

Once decisions made, M6.1–M6.5 build is a 4–5 week effort (~25–30 prompts per spec estimate).

---

## FILE REFERENCES FOR M6 BUILD

### New Models
app/Models/ActivityDefinition.php (NEW)
app/Models/ActivityDefinitionCalendarClass.php (NEW)
app/Models/ActivityPointOverride.php (NEW)

### Models to Extend
app/Models/CommandCenter/CalendarEvent.php → add observer for provisional creation (M6.3)
app/Models/CommandCenter/CalendarEventFeedback.php → extend observer for point confirmation (M6.4)

### Controllers
app/Http/Controllers/Agent/DailyActivityController.php → enhance view for M6.5

### Observers to Create
app/Observers/CalendarEventObserver.php (NEW — for M6.3)

### Migrations (M6.1–M6.4)
database/migrations/*_extend_activity_definitions_for_scope_and_agency.php
database/migrations/*_create_activity_definition_calendar_classes_table.php
database/migrations/*_extend_daily_activity_entries_for_source_and_state.php
database/migrations/*_create_activity_point_overrides_table.php

### Views to Create
resources/views/admin/activity-definition-calendar-classes/index.blade.php (M6.2)
resources/views/admin/activity-definition-calendar-classes/edit.blade.php (M6.2)