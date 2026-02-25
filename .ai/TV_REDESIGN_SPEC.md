# TV DISPLAY REDESIGN — FULL SCREEN BRANCH PERFORMANCE

> **Status:** Queued — do not start until current batch finishes
> **Saved:** 2026-02-24

The TV display (`/tv/display/{code}`) is shown on a wall-mounted TV in branch offices. It must be readable from 3+ metres, look professional, and motivate agents. Currently it's functional but flat — everything the same size, no colour coding, no leaderboard.

## READ FIRST:

- `resources/views/tv/display.blade.php` (or wherever the TV display view lives)
- `resources/views/bm/tv-messages.blade.php` (for hero + ticker message context)
- The TV controller that feeds data to the display
- `resources/css/nexus.css` — brand colours (navy #0b2a4a, cyan #00b4d8, crimson #c41e3a)

## DESIGN PRINCIPLES:

- Dark background (reduces TV glare, looks premium)
- Readable from 3 metres — minimum font 1.5rem for data, 3rem+ for hero numbers
- Answer "Are we winning?" in 2 seconds flat
- Motivate through competition (leaderboard) and urgency (remaining targets)
- Auto-refresh every 5 minutes (already implemented — preserve this)
- NO sidebar, NO header, NO scrolling — everything fits on one screen
- TV resolution: assume 1920×1080

## LAYOUT — 3 ZONES:

```
┌─────────────────────────────────────────────────────────────┐
│ HEADER BAR                                                  │
│ Branch Name (large, white)                    Live Clock    │
│ ═══════════════════════════════════════════════════════════ │
│ HERO MESSAGE (if active)                                    │
│ "SHELLY: Only 14 deals to go this month!"                  │
├────────────────────┬────────────────────┬───────────────────┤
│                    │                    │                   │
│   DEAL STATUS      │   VALUE & TARGET   │  LISTING STOCK   │
│   Pending  3 🟡    │                    │                   │
│   Granted  1 🟢    │   R 3,190,000      │  Active    66    │
│   Registered 1 🔵  │   / R 16,960,000   │  Avg DOM   208   │
│   Declined  1 🔴   │   ████░░░ 18.8%    │  Stale     66   │
│                    │                    │  Expiring  13    │
│                    │   Points 43,845    │  Expired    7    │
│                    │   / 135,000        │                   │
│                    │   ████░░░ 32.5%    │                   │
├────────────────────┴────────────────────┴───────────────────┤
│ AGENT LEADERBOARD                                           │
│ ┌──┬─────────────┬──────────┬───────────┬────────┬────────┐│
│ │🥇│ Falan Du Bois│ 2 deals  │R1,595,000 │ 9,195  │ ████░ ││
│ │🥈│ Gerda Baard  │ 0 deals  │R 0        │ 5,165  │ ███░░ ││
│ │🥉│ Rochelle C   │ 1 deal   │R 445,000  │ 0      │ ░░░░░ ││
│ │4 │ Agent Name   │ 0 deals  │R 0        │ 2,100  │ ██░░░ ││
│ └──┴─────────────┴──────────┴───────────┴────────┴────────┘│
├─────────────────────────────────────────────────────────────┤
│ ▸▸▸ TICKER: Company message scrolls here ▸▸▸              │
└─────────────────────────────────────────────────────────────┘
```

## ZONE 1 — HEADER (fixed height ~80px)

- Branch name: white, bold, 2.5rem
- Live clock: white, 2.5rem, updates every second via JS (already exists — keep it)
- Thin cyan line separator below

## ZONE 2 — HERO MESSAGE (conditional, ~60px)

- Only shows if an active hero message exists for this branch
- Full-width banner, navy background (#0b2a4a), white text, 1.5rem, centered
- Dynamic placeholders already resolved by controller — just display the text
- If no hero message, this zone collapses and gives space to the grid below

## ZONE 3 — METRICS GRID (3 columns, fills middle ~40% of screen)

### Column 1: Deal Status

- 4 rows: Pending (amber #f59e0b), Granted (green #059669), Registered (navy #0b2a4a), Declined (crimson #c41e3a)
- Each row: coloured dot/bar on left, status label, count number (large, 2rem)
- Simple, scannable from across the room

### Column 2: Value & Points

- Sales Value: hero number (3rem+, white), target below in grey, progress bar (green if >50%, amber if 25-50%, crimson if <25%)
- Points: same pattern below value
- "Still to target: R X" in cyan below progress bars

### Column 3: Listing Stock

- 5 metrics in a compact stack: Active, Avg DOM, Stale, Expiring, Expired
- Active in white (large), rest in grey (smaller)
- Stale/Expiring/Expired in amber or crimson if concerning numbers

## ZONE 4 — AGENT LEADERBOARD (fills bottom ~35% of screen)

- This is the killer feature. Ranked by points (or deals, or value — use points as default)
- Show top 8-10 agents (whatever fits)
- Columns: Rank (medal emoji for top 3, number for rest), Agent Name (white, bold), Deals, Sales Value, Points, Progress bar vs target
- Top agent gets a subtle glow or highlight row
- Alternating row backgrounds (dark grey / slightly lighter)
- If agent has 0 deals and 0 points, show in dimmed text (still visible, still motivating through shame)

## ZONE 5 — TICKER (fixed height ~50px, bottom of screen)

- Scrolling text marquee, right-to-left
- Only shows if active ticker messages exist for this branch
- Dark background, cyan text, smooth CSS animation
- If multiple ticker messages, concatenate with separator (★ or •)
- If no ticker messages, this zone collapses

## DATA REQUIREMENTS:

The TV controller already loads branch performance data. It needs to ALSO load:

- **Agent leaderboard data:** list of agents in the branch with deals count, sales value, points (actual and target) for the current period
- This data likely comes from the same source as the "Agents (targets vs actuals)" table on the BM performance page

Check the BM PerformanceController to see how it loads agent data, and replicate that query in the TV controller.

## HERO + TICKER MESSAGES:

The TV controller should load active messages for this branch from the tv_messages table (or whatever table stores them). Check:

- `bm/tv-messages` route → find the model/table
- Filter: active messages where branch matches OR message is global
- Separate hero messages (`show_on = 'Hero'` or `'Hero + Ticker'`) from ticker messages (`show_on = 'Ticker'` or `'Hero + Ticker'`)
- Replace `{{placeholders}}` with actual values (the controller likely already does this)

## COLOURS (dark theme):

```
Background:         #0f1923 (very dark navy)
Card backgrounds:   #1a2937 (dark slate)
Text primary:       #ffffff
Text secondary:     #94a3b8 (grey)
Cyan accent:        #00b4d8
Green (on track):   #059669
Amber (warning):    #f59e0b
Crimson (behind):   #c41e3a
Navy (registered):  #0b2a4a
Progress track:     #334155
```

## CSS:

All styles should be INLINE or in a `<style>` block within the TV display view. The TV page does not use the nexus layout or nexus.css — it's standalone. Do not add external CSS dependencies.

## JS:

- Keep existing auto-refresh (meta refresh or JS interval)
- Keep existing live clock
- Add smooth ticker scroll animation (CSS `@keyframes marquee` or similar)
- No external JS dependencies

## DO NOT TOUCH:

- TV code auth system (TvController verify/index)
- TV messages management page (bm/tv-messages)
- Any other controller or service EXCEPT the TV display controller (to add agent leaderboard data)
- The deactivated view

## THE ONLY CONTROLLER CHANGE ALLOWED:

`app/Http/Controllers/TV/TvController.php` — the `display()` method — to add agent leaderboard data and hero/ticker messages to the view data. Use the same data sources as the BM performance page.

## TEST:

- Navigate to `/tv` → enter code → display loads
- Branch name and clock visible from 3 metres (test by zooming browser to 50%)
- Deal status cards colour-coded correctly
- Value and points show with progress bars
- Agent leaderboard shows ranked agents with deals/value/points
- Hero message displays if one exists for the branch
- Ticker scrolls if ticker messages exist
- Auto-refresh still works (wait 5 minutes or check meta tag)
- Page fits on 1920×1080 without scrolling
- `php -l` on TV controller
- `php artisan view:clear`
- `scripts/dev-check.ps1`
