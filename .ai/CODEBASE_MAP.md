# CoreX OS — Codebase Map

Quick reference for locating key files. Always verify with `find` or `ls` before writing prompts — structure evolves.

---

## Repository Root (`/hfc`)

```
/hfc
├── app/
│   ├── Http/
│   │   ├── Controllers/        ← All controllers
│   │   └── Middleware/
│   ├── Models/                 ← Eloquent models (pillar models live here)
│   ├── Services/               ← Business logic layer
│   │   ├── KnowledgeSearchService.php   ← Ellie KB search (hybrid cosine+structural)
│   │   ├── P24MarketDataService.php     ← P24 market data integration
│   │   └── ...
│   └── ...
├── resources/
│   ├── views/
│   │   ├── layouts/
│   │   │   ├── corex-app.blade.php       ← Main authenticated layout
│   │   │   └── corex-sidebar.blade.php   ← Sidebar navigation
│   │   ├── listings/
│   │   ├── contacts/
│   │   ├── deals/
│   │   ├── documents/          ← DocuPerfect views
│   │   ├── presentations/
│   │   ├── tracker/            ← Agency Tracker views
│   │   └── ...
│   └── js/
├── database/
│   ├── migrations/
│   └── seeders/
├── routes/
│   └── web.php
├── .env                        ← Credentials — NEVER commit, NEVER hardcode
├── .env.example
└── ...
```

---

## Key Models (Four Pillars + Supporting)

| Model | Table | Notes |
|-------|-------|-------|
| `Property` | `properties` | Core pillar — all listings attach here |
| `Contact` | `contacts` | Core pillar — buyers, sellers, landlords, tenants |
| `Deal` | `deals` | Core pillar — sale/rental transaction record |
| `User` | `users` | Core pillar (agent role) — agents, principals, admin |
| `Listing` | `listings` | Attaches to Property + User(s) |
| `Document` | `documents` | Attaches to Property + Contact + Deal + User |
| `Signature` | `signatures` | Belongs to Document + Contact/User |
| `Commission` | `commissions` | Belongs to Deal + User |

---

## Key Services

| Service | Location | Purpose |
|---------|----------|---------|
| `KnowledgeSearchService` | `app/Services/` | Ellie KB hybrid search |
| `P24MarketDataService` | `app/Services/` | P24 market data for presentations |

---

## Python AI Service

- Location: `/opt/hf-ai/app.py`
- Port: `3100`
- Not tracked in git
- Restart manually: `sudo systemctl restart hf-ai` (or equivalent)
- Handles: Ellie embedding, vector search, OpenAI routing

---

## Branch Strategy

| Branch | Purpose | Rule |
|--------|---------|------|
| `main` | Production server | Only merge tested, reviewed code |
| `HFC2402` | Johan's dev branch | Johan's feature work |
| `andre` | Andre's dev branch | Andre's feature work |

**Always:** `git diff main..HFC2402 --stat` before merging to check what's ahead.
**Always:** Check for Andre's commits on `main` before pushing to avoid overwriting.

---

## Environment Notes

- Local: Laragon + MySQL + PHP 8.x
- Server: Ubuntu 91.99.130.85, MySQL, PHP 8.x, Node/Puppeteer installed
- `npm run dev` — local HMR
- `npm run build` — production build (run before deploying)
- `opcache_reset()` — run after deploy to clear PHP opcode cache
