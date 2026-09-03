# Admin console — answers, contracts, and three blockers

Verified against the live dev API on 2026-08-27, not from memory.

---

## 1. API environment

| | |
|---|---|
| Base URL | `https://apidevticket.plescreative.com/api` |
| Reachable | Yes — verified from the dev machine |
| Super admin | `superadmin@plesticket.com` / `adminpass` |
| Auth | JWT bearer, `Authorization: Bearer <token>` |
| Token TTL | 1 hour (observed `exp - iat = 3600`) |

These are the seeded dev credentials, already documented in `CLAUDE.md` — not
production secrets. They should still be changed on any shared environment.

**The dev database is MariaDB/MySQL, not PostgreSQL.** `CLAUDE.md` and
`docker-compose.yml` both say PostgreSQL. This mismatch is the cause of blocker
#1 below, and it means local Docker and dev do not behave identically.

CORS already allows `http://localhost:3000` and `http://127.0.0.1:3000`, so a
local admin frontend on port 3000 works. Any other port or host needs adding to
`public/.htaccess` (Apache/dev), **not** `config/cors.php` — that file is
inert on the dev server.

---

## 2. Response contracts

Captured live. Raw JSON in `admin-contracts.json`.

### Envelope

Success:
```json
{ "status": "success", "message": "Users retrieved.", "data": {} }
```

Error:
```json
{ "status": "error", "message": "Human readable reason.", "errors": {} }
```

`errors` appears only on 422. `message` on 4xx is written for humans and is safe
to surface directly.

### Pagination — NOT Laravel's default shape

```json
{
  "status": "success",
  "message": "Users retrieved.",
  "data": [ {...} ],
  "meta": { "total": 6, "page": 1, "limit": 2, "pages": 3 }
}
```

Note the key names: `page` / `limit` / `pages`, **not** `current_page` /
`per_page` / `last_page`. Any off-the-shelf pagination component expecting
Laravel's default will silently show one page. Query param is `?limit=`, and
`?search=` where supported.

### Validation — 422

```json
{
  "status": "error",
  "message": "Validation failed",
  "errors": { "email": ["The email field is required."] }
}
```

`errors` is `{ field: [messages] }`. Minor inconsistency worth tolerating: the
login endpoint returns `"Validation failed"` while others return
`"Validation failed."` with a period. Do not match on the message text.

### Auth failures

```
401 no/expired token   { "status": "error", "message": "Unauthenticated." }
401 bad credentials    { "status": "error", "message": "Invalid email or password." }
403 wrong role         { "status": "error", "message": "Insufficient permissions." }
```

Both 401 shapes are identical apart from the message, so distinguish by which
call failed, not by parsing.

### Resource shapes

`users` and `admin/categories` are flat. `admin/events` is **nested** — `pic`,
`schedule`, `location`, and `verification` are sub-objects, not flat columns:

```json
{
  "id": "01a03ec4-...", "event_id": "EVT9001",
  "title": "...", "slug": "...", "category": "Music", "banner_url": null,
  "pic":      { "name": "...", "identity_type": "ktp", "identity_type_label": "KTP",
                "identity_number": "...", "npwp": "..." },
  "schedule": { "start_date": "2026-09-05", "end_date": "...",
                "start_time": "15:00:00", "end_time": "23:00:00" },
  "location": { "is_online": false, "venue_name": "...", "address": "...", ... }
}
```

`pic.identity_number` and `pic.npwp` are **decrypted PII** (KTP / tax IDs) served
in plain text to super admins. Do not log them, do not put them in error
reports, and mask them by default in the UI with an explicit reveal.

---

## 3. Three blockers — please read before estimating

### Blocker 1: every `search` filter 500s on dev

Four repositories use PostgreSQL's `ilike`, which is a **syntax error** on
MariaDB:

```
CategoryRepository  1 use
EventRepository     5 uses
OrderRepository     6 uses
UserRepository      2 uses
```

Live result:
```
GET /users?search=demo          → 500  "syntax ... near 'ilike ? or `email` ilike ?'"
GET /admin/events?search=jakarta → 500  same
```

So **user search and event search — both in the proposed scope — are broken on
dev today**. Listing without a search term works fine.

Fix is one of: swap `ilike` for `LIKE` (MySQL's `LIKE` is already
case-insensitive under the usual collation), or move dev to PostgreSQL to match
`docker-compose.yml`. This is a backend fix; do not work around it in the admin
UI.

### Blocker 2: `/admin/talents` and `/talents` both 500

```
Table 'u994554137_plesticketdev.talent' doesn't exist
```

The deployed code queries `talent` (singular); the migration creates `talents`.
Nothing in the current repo references the singular name, so **dev is running
different code from `main`** — most likely a stale or partial deploy.

**Talent listing/verification is in the proposed scope and is completely
non-functional.** Needs a redeploy and re-check before it can be built against.

### Blocker 3: 404s are returned as 500

```
GET /users/USR-NOPE  →  HTTP 500  { "message": "User not found." }
```

`UserController::show` does not catch the service's `RuntimeException`, so it
falls through to the generic handler. The body says "not found" but the status
says server error.

Build against the **message**, not the status, for this one endpoint until it is
fixed — or better, let it be fixed first. Worth auditing sibling controllers for
the same gap.

---

## 4. Scope

The proposed first release maps cleanly onto the 15 existing `role:SUPER_ADMIN`
routes. Two gaps:

**"Dashboard shell" has no data source.** There is no admin stats/summary
endpoint. Either ship the shell empty, or a small `GET /api/admin/summary`
needs adding backend-side first. Flag which you want.

**Two operational queues have no admin surface, and both involve real money:**

- `payments.requires_refund = true` — a buyer paid twice, or paid an order that
  had already been released. Currently only discoverable by SQL.
- `webhook_deliveries.status IN ('unmatched','failed')` — a verified provider
  callback that matched no payment, or that threw while processing.

Neither has an API endpoint yet. They are arguably more valuable than the
dashboard, because today nobody finds out except by querying the database. Worth
considering for release 1 or fast-following.

---

## 5. Behaviour decisions — answered from the code

### Delete User: **deactivate, do not delete**

This is not a preference. `User` has **no** `SoftDeletes`, and:

```php
// orders table
$table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
```

So `DELETE /api/users/{uid}` **hard-deletes the user and cascades away their
orders**, and through those their order items, tickets, and payment records.
Deleting a customer who has bought a ticket destroys the financial record of
that sale.

Use `PUT /api/users/{uid}` with `is_active: false` instead — the field exists
and is already exposed. I would suggest the admin UI not expose the delete
endpoint at all until it is changed to a soft delete backend-side.

### Delete Category: **deactivate**

Also a hard delete, but far less dangerous: `events.category` is a plain
`string(100)`, not a foreign key, so existing events keep their category text.
The only consequence is a historical value that no longer appears in the
picker. `categories.is_active` already exists — prefer toggling it.

### Rejected events: **reason is already required**

```php
// RejectEventRequest
'reason' => ['required', 'string', 'max:500'],
```

Omitting it returns 422. Make it a required field in the UI, max 500 chars.

### Multiple admin roles: **not now**

`UserRole` has exactly two cases — `SUPER_ADMIN` and `REGISTERED_USER`. There is
no admin sub-role and no permission table. Adding one means an enum change,
middleware changes, and a migration. Build for a single super-admin role; the
`role:SUPER_ADMIN` middleware already handles gating.

Note `users.is_organizer` is a **flag, not a role** — the same account can
organise events and buy tickets. Do not model it as a third role in the UI.

---

## 6. Frontend approach — decision needed

The suggestion of "Blade + Tailwind using existing dependencies" does not quite
match the repo:

- There are **no Blade views and no web controllers**. `routes/web.php` contains
  one route that returns JSON.
- `package.json` already has **React 19, Vite 8, Tailwind 4, react-router-dom**
  — the existing frontend dependencies are React, not Blade.
- Auth is **stateless JWT**. Blade would want session auth, so a Blade admin
  would either need a session layer bolted on, or would have to hold a JWT
  server-side per user — both awkward.

Also relevant: buyer and EO have already been split into separate frontends, and
the API deliberately serves all of them.

**Recommendation: a separate React SPA**, consistent with the buyer and EO apps
and with the JWT the API already speaks. Costs: a third deploy target and a
third CORS origin (add it to `public/.htaccess`).

If a single-repo Blade app is preferred for operational simplicity — one deploy,
no CORS, internal tool with few users — that is defensible, but the session auth
question needs answering first. Please pick before work starts; this is the one
decision that is expensive to reverse.

---

## Answers in one line each

1. Base URL above; reachable; dev super-admin credentials above.
2. Contracts above and in `admin-contracts.json`. Pagination is `page`/`limit`/`pages`.
3. Recommend separate React SPA, not Blade — see reasoning above. Your call.
4. No Figma or brand assets known. Propose a clean responsive design.
5. Scope agreed, minus talents (broken) and dashboard (no endpoint). Consider adding the refund and webhook queues.
6. Deactivate rather than delete for both. Reject reason already required. Single admin role.
