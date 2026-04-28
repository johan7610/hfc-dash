# Mobile-app Claude — copy/paste prompt

Paste the following block into the mobile-app project's Claude Code session.

---

You are working on the **CoreX OS mobile app** (React Native / Expo). The CoreX backend (Laravel) just shipped a new pillar-aware notification system. Your job is to integrate the mobile app with it: register for push, fetch and render notifications, expose a settings screen for preferences, and surface "what's overdue right now" on the home screen.

## Backend — already done, do not change

- Base URL: same `/api` host the app already uses (Sanctum bearer tokens, login already working).
- All endpoints below sit under `auth:sanctum`. Send the existing `Authorization: Bearer <token>` header.
- Response shapes are plain JSON (no Laravel resource wrappers).

### Endpoints

**Device tokens (push registration)**
```
POST   /api/device-tokens                { platform: "ios"|"android", token: string, app_version?: string } -> { ok: true }
DELETE /api/device-tokens/{token}                                                                            -> { ok: true }
```

**Notification feed**
```
GET    /api/notifications?unread=1&limit=20    ->
{
  items: [{
    id, type, event_key, pillar, title, body,
    subject: { type, id, label } | null,
    action_url, severity: "info"|"warning"|"overdue",
    read_at, created_at, data
  }],
  unread: number
}
POST   /api/notifications/{id}/read           -> { ok: true }
POST   /api/notifications/mark-all-read       -> { ok: true }
```

**Live overdue snapshot — for home-screen badges + banners**
```
GET    /api/notifications/overdue             ->
{
  counts: { properties, contacts, deals, tasks, events, total },
  items: [{ event_key, pillar, subject, age_hours, severity, action_url, title, body, threshold_hit_at }]
}
```

**Preferences** (always per-user — agency mode does not apply here)
```
GET    /api/notification-preferences          ->
{
  master: { in_app, email, push },
  agency_controlled: false,        // always false; field kept for forward-compat
  groups: [{
    pillar: "property"|"contact"|"deal"|"agent",
    label,
    items: [{
      key, label, description, group,
      threshold_unit: "hours"|"days"|"none",
      threshold, threshold_min, threshold_max,
      enabled, channel_in_app, channel_email, channel_push,
      is_adapter
    }]
  }]
}
PUT    /api/notification-preferences
       { master?: {...}, preferences: [{ key, enabled, threshold, channel_in_app, channel_email, channel_push }, ...] }
       -> { ok: true, saved: N }
```

### FCM push payload sent by the server
```
{ notification: { title, body },
  data: { event_key, pillar, subject_type, subject_id, action_url, severity, notification_id } }
```
Use `data.event_key`, `data.subject_type`, `data.subject_id`, and `data.action_url` to deep-link.

## What to build

1. **Push registration**
   - On login (and at app start if a token exists), call `expo-notifications` to obtain a push token.
   - POST it to `/api/device-tokens` with `platform` and `app_version`.
   - On logout, DELETE `/api/device-tokens/{token}` then revoke locally.
   - Re-register if the OS rotates the token.

2. **Notification handlers**
   - Foreground: show a toast/banner; bump the bell-icon unread count.
   - Background tap: read `data.action_url` and navigate (deep-link map below).
   - Killed-state cold-start tap: same deep-link logic.

3. **Notifications screen** (`/notifications`)
   - Pull `GET /api/notifications` with infinite scroll.
   - Group visually by `pillar` and `severity` (overdue first).
   - Tap → mark read (`POST /api/notifications/{id}/read`) → navigate via `action_url`.
   - "Mark all read" button.

4. **Home screen "Overdue" widget**
   - On focus, call `GET /api/notifications/overdue`.
   - Show four pill buttons (Properties / Contacts / Deals / Tasks) with `counts.*`.
   - Tap a pill → filtered Overdue list, which renders `items` filtered by pillar.
   - Tap an item → navigate via `action_url`.
   - Refresh on pull-to-refresh and after every push received.

5. **Settings → Notifications screen**
   - Load `GET /api/notification-preferences`.
   - These preferences are always per-user editable. Ignore `agency_controlled` (will always be `false`) — no banner, no read-only state.
   - Render four collapsible groups (Property, Contact, Deal, My activity = pillar `agent`).
   - Each row:
     - Toggle for `enabled`.
     - When `threshold_unit !== "none"`, a numeric stepper bounded by `threshold_min`/`threshold_max`, suffix "hours" or "days".
     - Three checkboxes: In-app / Email / Push.
   - Master switches at the top: In-app / Email / Push.
   - "Save" sends `PUT /api/notification-preferences` with the full state. Show success toast on `{ ok: true }`. On 409 + `agency_controlled`, freeze the form and show the banner.

6. **Deep-link map** (translate server `action_url` paths into native routes)
   - `/properties/:id`        → PropertyDetail
   - `/contacts/:id`          → ContactDetail
   - `/deals/:id`             → DealDetail
   - `/corex/command-center/calendar?event=:id` → CalendarEvent
   - `/corex#task-:id`        → TaskDetail
   - Fallback: open in WebView.

## Constraints

- Use the app's existing auth helper, fetch wrapper, and design tokens — do **not** introduce a new HTTP client.
- Cache the preferences snapshot in SecureStore/AsyncStorage so the screen renders instantly; revalidate in the background.
- Treat 401 the same way as the rest of the app (redirect to login).
- Add types/interfaces for `NotificationItem`, `NotificationPreference`, `OverdueSnapshot`.

## Acceptance criteria

- [ ] Logging in registers a push token; logging out revokes it.
- [ ] An FCM push delivered while the app is foregrounded shows a banner and increments the bell.
- [ ] Tapping a push routes to the correct screen via the deep-link map.
- [ ] Notifications screen lists items with pillar grouping, severity colours, mark-read, and mark-all-read.
- [ ] Home screen shows live overdue counts that match `/api/notifications/overdue`.
- [ ] Settings → Notifications loads, edits, and persists preferences (always editable, no agency lock).
- [ ] All requests use the Sanctum bearer token from existing auth state.
- [ ] No new dependencies beyond `expo-notifications` (or the app's existing push lib).

Work iteratively, surface any divergence between the contract above and what the API actually returns, and report progress per acceptance-criteria checkbox.
