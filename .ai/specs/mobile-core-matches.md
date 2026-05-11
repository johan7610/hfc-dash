# Mobile Core Matches — Spec

> Mobile (`corex_mobile`) Core Matches module.
> Created: 2026-04-29

## Purpose

Agent on the road can review every Core Match they own, see how the client reacted to each result property (Interested / Not for me / Saved), edit the match filters, hide irrelevant results, and jump to the linked contact.

## Pillars

- **Contact** — every match belongs to a contact
- **Property** — match results are properties (`MatchingService::propertiesForMatch`)
- **Agent** — `created_by_user_id = auth()->id()` is the only scope on mobile

## Permissions

Own scope only. 403 if `match.created_by_user_id !== auth user`.

## API surface (auth:sanctum)

| Method | Route | Purpose |
|---|---|---|
| GET    | `/api/mobile/core-matches` | List own matches grouped by contact, each match carries `feedback_summary` counts |
| GET    | `/api/mobile/core-matches/{id}` | Match + contact + results (per-property: `hidden`, `reaction`, `reaction_note`) |
| PUT    | `/api/mobile/core-matches/{id}` | Edit filters |
| PATCH  | `/api/mobile/core-matches/{id}/status` | active / paused / fulfilled / expired |
| POST   | `/api/mobile/core-matches/{id}/hide/{propertyId}` | Toggle hide |
| DELETE | `/api/mobile/core-matches/{id}` | Soft delete |

Match creation is handled by the existing `POST /api/mobile/contacts/{id}/matches` endpoint.

## Result properties payload

Each result includes the client's reaction so the agent sees at a glance what the contact said via the shared link:

```json
{
  "id": 42,
  "address": "12 Beach Rd, Shelly Beach",
  "suburb": "Shelly Beach",
  "beds": 3, "baths": 2, "garages": 1,
  "price": 1850000, "price_display": "R 1,850,000",
  "thumbnail": "/storage/...",
  "hidden": false,
  "reaction": "interested",          // 'interested' | 'not_interested' | 'saved' | null
  "reaction_note": null
}
```

## User flow

```
Core Matches list (grouped by contact)
  └─ tap match → Match Detail
        ├─ summary chips: status, listing_type, price band, suburbs
        ├─ [Edit filters] → PUT
        ├─ [Status menu]  → PATCH /status
        ├─ [Open contact] → contact detail screen
        └─ Results list:
              • each tile shows reaction badge (green=interested, red=not_for_me, yellow=saved)
              • [Hide / Unhide] button → POST /hide/{propertyId}
              • Filter toggle: "Hide hidden" / "Show all"
```

## Files created/modified

- **NEW** `app/Http/Controllers/Api/MobileCoreMatchController.php`
- **MOD** `routes/api.php` — `mobile/core-matches/*` group
- **MOD** `.ai/MOBILE_APP.md`
- **NEW** `.ai/specs/mobile-core-matches.md`

## Acceptance criteria

- Agent A cannot view/edit Agent B's match (403)
- `feedback_summary` counts match the rows in `contact_match_feedback`
- Toggle hide flips `hidden_property_ids` on the match row and is reflected on the next GET
- Status PATCH persists and shows in the web Core Matches index
- All routes resolve, `php -l` clean
