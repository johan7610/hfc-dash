# Mobile Contacts Module — Spec

> Mobile app (Flutter `corex_mobile`) integration with the Contact pillar.
> Created: 2026-04-29

## What this feature does

Bring the Contacts pillar into the Flutter companion app so an agent on the road can:

1. View every contact created under their name
2. Edit a contact's basic identity fields (limited set)
3. Create a new contact
4. Trigger a WhatsApp conversation with the contact (records the touch)
5. Create a CoreMatch (buyer/tenant requirement) against a contact
6. From a contact, kick off the existing mobile property wizard so the new property is auto-linked back to that contact

## Pillars

- **Contact** — primary; full read of own contacts, scoped writes
- **Property** — write (creating a listing from a contact)
- **Agent** — every record is scoped by `created_by_user_id = auth user`

## Permissions / scope

Mobile is "own" scope only — agents see and edit only contacts where `created_by_user_id === auth()->id()`. No branch/all override on mobile (use the web for cross-agent work). All write endpoints abort 403 if the contact doesn't belong to the caller.

## Editable fields on mobile

The mobile edit form is **deliberately reduced** vs the web. Only:
- `first_name` (required)
- `last_name` (required)
- `phone` (required)
- `email` (optional)
- `id_number` (optional)

Bank details, tags, contact_type, address, birthday, and notes are **read-only** on mobile — those still live on the web. The PUT endpoint silently ignores any other fields.

## API surface (all under `auth:sanctum`)

| Method | Route | Purpose |
|---|---|---|
| GET  | `/api/mobile/contacts` | Paginated list of own contacts (search via `?search=`, `?per_page=`) |
| GET  | `/api/mobile/contacts/options` | Contact types for dropdowns |
| GET  | `/api/mobile/contacts/{id}` | Full contact + matches + linked properties |
| POST | `/api/mobile/contacts` | Create — `first_name, last_name, phone, email?, id_number?, contact_type_id?, notes?`. 422 on phone/email duplicate (returns `duplicate_id`) |
| PUT  | `/api/mobile/contacts/{id}` | Limited edit (5 fields above) |
| POST | `/api/mobile/contacts/{id}/whatsapp` | Increments `whatsapp_count`, sets `last_contacted_at`, returns `wa_link` (https://wa.me/27…) |
| POST | `/api/mobile/contacts/{id}/matches` | Create CoreMatch (listing_type required: sale/rental + filters) |

### Property creation linked to contact

Reuses `POST /api/mobile/properties` (existing `MobilePropertyController@store`). Two **new optional** params accepted:
- `link_contact_id` — must be a contact owned by the caller
- `link_contact_role` — free string, e.g. `seller`, `landlord`, `buyer`, `tenant`

The controller attaches via `contact_property` pivot inside the same request. If the contact isn't owned by the caller, the link is silently dropped (property still created — fail-safe, not fail-loud, matches existing pattern).

## User flow

```
List screen → tap contact → Detail screen
   ├─ Edit (5 fields)              → PUT /mobile/contacts/{id}
   ├─ WhatsApp button              → POST /…/whatsapp → launch wa_link
   ├─ Add CoreMatch                → POST /…/matches
   └─ Create Listing for contact   → property wizard, on submit pass
                                     link_contact_id = this contact
                                     link_contact_role = chosen role
```

`+` FAB on list → New Contact form → `POST /mobile/contacts`.

## Files created/modified

- **NEW** `app/Http/Controllers/Api/MobileContactController.php`
- **MOD** `app/Http/Controllers/Api/MobilePropertyController.php` — `store()` accepts `link_contact_id`/`link_contact_role`
- **MOD** `routes/api.php` — `mobile/contacts/*` group
- **MOD** `.ai/MOBILE_APP.md` — endpoint table updated
- **NEW** `.ai/specs/mobile-contacts.md` — this file

## Acceptance criteria

- Agent A cannot read/write Agent B's contact (403)
- Duplicate phone/email returns 422 with `duplicate_id`
- WhatsApp endpoint returns `wa.me` link with SA `0` → `27` rewrite
- Property created via mobile with `link_contact_id` shows on web Contact → Properties tab and on web Property → Contacts tab with the supplied role
- Matches created on mobile appear on web Contact detail Matches tab
- All routes resolve (`php artisan route:list --path=api/mobile/contacts`)
- `php -l` clean on changed files
