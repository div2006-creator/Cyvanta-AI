# CYVANTA

AI-powered criminal network analysis and investigation-support platform.
*"Connecting Evidence. Revealing Networks. Supporting Investigations."*

---

## ⚠️ Important: testing status of this build

This project was built in a sandboxed environment **with no PHP runtime, no
MySQL server, and no network access** — it was not possible to install PHP,
start MySQL, or run the application to execute the test checklist end to end.

Every file has been written carefully and checked with static tooling
(brace/parenthesis balance across all 83 PHP files and 12 JS files, include-path
depth verification for every API endpoint), but **you must run the install and
test steps below yourself** before treating this as verified. Please do not
take "delivered" to mean "tested on a live stack" — it means "a complete,
consistent codebase that is ready for you to test."

If anything doesn't run cleanly, the most common culprits are typos in a path
or a missing PHP extension — see **Troubleshooting** below.

---

## 1. Requirements

- PHP 8.1+ with `pdo_mysql`, `fileinfo`, and `curl` extensions
- MySQL 8+ (or MariaDB 10.6+)
- A modern browser
- macOS / Windows / Linux — no OS-specific code is used

Check your PHP extensions:
```bash
php -m | grep -E "pdo_mysql|fileinfo|curl"
```

## 2. Folder structure

```
crimegraph-ai/
├── public/              ← Web server document root (point Apache/PHP here)
│   ├── index.php         Landing page
│   ├── login.php, dashboard.php, cases.php, case-details.php, ...
│   ├── admin/            Admin panel pages
│   ├── api/              All REST-style JSON endpoints (see §6)
│   └── assets/           CSS / JS / images
├── config/               config.php (.env loader), database.php (PDO)
├── includes/             auth.php, functions.php, bootstrap.php, partials/
├── services/             DocumentProcessingService.php, AnalysisService.php
├── middleware/           Thin OOP wrappers around includes/auth.php
├── database/             schema.sql, seed.php
├── websocket/            server.php (dependency-free WebSocket server)
├── storage/uploads/      Uploaded files (outside the web root, not in public/)
└── .env.example
```

**Note on the structure vs. the original spec:** the spec's suggested layout
put `api/` and `assets/` as siblings of `public/`. Because `public/` is the
web server document root (`php -S localhost:8000 -t public`), anything that
needs a browser-reachable URL — `api/` and `assets/` — was placed *inside*
`public/` instead, so links actually resolve. `config/`, `includes/`,
`services/`, `middleware/`, `database/`, `websocket/`, and `storage/` all stay
outside `public/`, which is *better* for security (nobody can browse to your
DB credentials or uploaded evidence files) and matches §44's requirement to
store uploads outside the web root.

**Note on `controllers/`/`models/`:** the spec listed these as suggested
folders. This build uses a lean pattern instead — each `public/api/**/*.php`
file *is* a small controller (validates input, calls a service or runs a
scoped query, returns JSON), and `services/DocumentProcessingService.php` /
`services/AnalysisService.php` hold the non-trivial business logic. For a
project this size that keeps the codebase easy to trace; a team preferring
strict MVC could lift the query logic out of the API files into `models/`
without changing any behavior.

## 3. Installation

```bash
# 1. Copy environment config
cp .env.example .env
# Edit .env with your DB credentials if not using local defaults

# 2. Create the database and tables
mysql -u root -p < database/schema.sql

# 3. Seed roles, the default admin, and demo data
php database/seed.php
```

`database/seed.php` (not a raw `.sql` INSERT) is used deliberately for the
admin account so the password is hashed with PHP's own `password_hash()` at
seed time — never a precomputed hash checked into source control.

## 4. Running the application

```bash
# Terminal 1 — the web application
php -S localhost:8000 -t public

# Terminal 2 — the WebSocket server (optional but recommended)
php websocket/server.php
```

Open **http://localhost:8000**.

If you skip Terminal 2, the app still works fully — `assets/js/app.js`
detects the missing WebSocket connection within ~2.5 seconds and silently
switches to AJAX polling for notifications and the admin activity feed. The
status dot next to the bell icon reflects which mode you're in.

### Default login

| Field | Value |
|---|---|
| Username | `adminsumitgu` |
| Password | `sumitgu` |
| Role | Super Admin |

You'll be forced to change this password on first login (`must_change_password`
is set by the seeder). Additional demo accounts (all password `Xxxxx@123`,
see `database/seed.php` for exact values) are created for each role:
`priya.investigator`, `arjun.analyst`, `neha.admin`, `vikram.viewer`.

## 5. AI/NLP architecture

`services/DocumentProcessingService.php` is the abstraction described in the
spec. Two modes:

- **Demo mode (default)** — `runDeterministicExtraction()` runs real regex +
  dictionary-based extraction against the actual uploaded document text
  (for `.txt`/`.csv`) or the document's metadata (for binary formats this
  build doesn't parse, like `.pdf`/`.docx` — see Known Limitations). This is
  genuine rule-based NLP, not a fake progress bar: it finds phone numbers,
  vehicle plates, emails, organization names, and person names, and infers
  relationships from co-occurrence.
- **Connected mode** — set `AI_SERVICE_ENABLED=true` and `AI_SERVICE_URL` in
  `.env` (or in Admin → Settings) to point at a real Python NLP microservice.
  It should expose `POST {AI_SERVICE_URL}/analyze` accepting
  `{"text": "..."}` and returning
  `{"entities": [...], "relationships": [...]}` in the same shape the
  deterministic engine produces. If the call fails, processing falls back to
  the deterministic engine automatically so the pipeline never silently
  stalls.

`services/AnalysisService.php` computes explainable graph analytics (degree
centrality, hub detection, bridge-entity candidates, repeated-relationship
detection) directly in PHP against the `entities`/`relationships` tables —
no external service required for this part.

## 6. API overview

All endpoints live under `public/api/` and return
`{"success": bool, "message": string, "data": {...}}`. State-changing
requests (`POST`) require an `X-CSRF-Token` header matching the session token
(`window.CG.csrfToken`, embedded per-page) — enforced centrally in
`includes/bootstrap.php`.

| Area | Endpoints |
|---|---|
| Auth | `auth/login`, `auth/logout`, `auth/change-password`, `auth/forgot-password`, `auth/reset-password` |
| Cases | `cases/list`, `create`, `update`, `assign`, `archive`, `delete`, `details`, `timeline`, `activity` |
| Documents | `documents/list`, `upload`, `process` |
| Entities | `entities/list`, `global-list`, `details` |
| Relationships | `relationships/list` |
| Evidence | `evidence/list`, `create` |
| Notes | `notes/list`, `create`, `delete` |
| Analysis | `analysis/run`, `list`, `overview`, `dashboard-metrics` |
| Notifications | `notifications/list`, `mark-read` |
| Search | `search.php?q=` (global) |
| Admin: Users | `users/list`, `create`, `update`, `toggle-status`, `reset-password`, `delete`, `activity` |
| Admin: Settings | `settings/get`, `update` |
| Admin: Audit | `audit/list` |
| Admin: Activity | `admin/dashboard-metrics`, `admin/activity-feed` |

## 7. Security notes

- Passwords: `password_hash()` / `password_verify()`, bcrypt.
- SQL: 100% PDO prepared statements, no string-concatenated queries.
- CSRF: token-per-session, verified on every state-changing API call.
- File uploads: extension allow-list + `finfo` MIME sniffing (never trusts
  the client-supplied `Content-Type`), random server-side filenames, stored
  outside `public/`.
- Sessions: `httponly` cookies, idle timeout (`SESSION_LIFETIME`, default 30
  min), regenerated ID on login.
- Login attempts: 5 failed attempts per username locks further attempts for
  15 minutes (`login_attempts` table).
- RBAC: enforced server-side on every page and API call via
  `cg_require_role()` — never trust a hidden nav link alone.
- Errors: `Database.php` never leaks connection strings or raw PDO
  exceptions to the browser.

## 8. Demo workflow (matches spec §54)

1. Log in as `adminsumitgu` (or `priya.investigator`).
2. Open **Operation Nexus** (`CASE-2026-001`) from Cases.
3. Go to the **Documents** tab → **Upload Document**. For a document that
   demonstrates real extraction, upload a `.txt` file containing something
   like: `Aarav Mehta met Rohan Verma near Andheri Warehouse District.
   Contact: 9876543210. Vehicle MH04AB1234 was seen at Nexus Freight Pvt
   Ltd.`
4. Click **Process** — watch the six-stage progress modal (this is driven by
   the real API call to `documents/process.php`, not a fake timer; the
   stage-by-stage UI ticks alongside the actual request).
5. Switch to **Network** — the new entities and relationships appear in the
   interactive graph immediately.
6. Switch to **Analysis** → **Run Pattern Analysis** — see analytical
   indicators (hub/bridge/repeated-relationship) with the required
   disclaimer.
7. Add a note in **Notes**.
8. As an admin, check **Admin → Audit Logs** — every action above should
   have a corresponding row.

## 9. Known limitations (please read before your test pass)

Being upfront about what's *not* fully built, so nothing surprises you:

- **PDF/DOC/DOCX parsing**: the deterministic extractor reads real text from
  `.txt`/`.csv` uploads. For binary formats it falls back to the document's
  name/description/source fields rather than parsing the binary content —
  full binary text extraction would need a library like `smalot/pdfparser`
  (Composer) or `pdftotext`, which weren't available to install in the build
  sandbox. This is flagged clearly in `DocumentProcessingService::extractText()`.
- **WebSocket server**: hand-rolled (no Composer/Ratchet) to avoid any
  install-time dependency. It has been written carefully to the RFC 6455
  handshake/framing spec but has never been run against a real browser
  WebSocket client — please verify it in your environment. If it doesn't
  work, the app still functions fully via the automatic polling fallback.
- **Reports (§31)**: the Reports tab currently offers print-to-PDF via the
  browser's native print dialog rather than a server-generated PDF export.
- **Geographic map / case similarity / task assignment / chain-of-custody
  detail** (spec §76, "optional advanced features"): not implemented — the
  spec marked these optional/time-permitting.
- **Email delivery**: `forgot-password.php` generates a real, valid reset
  token but returns the link directly in the API response instead of
  emailing it (no SMTP server was available to configure/test). Swap in
  `mail()` or a mailer library and remove the link from the JSON response
  before using this in anything beyond a demo.
- This build has not been executed against a live PHP/MySQL stack (see the
  warning at the top of this file).

## 10. Troubleshooting

| Symptom | Likely cause |
|---|---|
| "Database connection failed" page | Check `.env` credentials; confirm `mysql -u root -p` works with the same credentials |
| Blank white page | Set `APP_ENV=development` in `.env` to surface PHP errors, or check your PHP error log |
| Uploads fail silently | Confirm `storage/uploads/` is writable (`chmod -R 775 storage`) |
| WebSocket status shows offline | Confirm `php websocket/server.php` is running and `WEBSOCKET_PORT` in `.env` matches `WEBSOCKET_URL` |
| "Invalid or expired security token" on every POST | Your session cookie isn't persisting — check the browser isn't blocking cookies for `localhost` |
| Login works but every page 404s | Confirm you started the server with `-t public`, not from the project root |

---

© 2026 CYVANTA. Built as an intelligence and investigation **support**
tool — analytical results are indicators, not proof of guilt, and human
investigators remain responsible for interpretation and decisions.
