# EO dashboard — API endpoint reference

Every endpoint the organizer dashboard needs. Shapes captured from the live dev
API, not written from a schema.

**Base URL:** `{API_BASE_URL}` (dev: `https://apidevticket.plescreative.com/api`)
**Auth:** `Authorization: Bearer <token>` on everything except the public reads.

Envelope is always `{status, message, data}`, plus `meta` on paginated lists and
`errors` on 422. See `eo-auth-spec.md` for register/login/session.

---

## Endpoint map

| Method | Path | Purpose |
|---|---|---|
| **Auth (shared with buyer app)** |||
| POST | `/auth/register` | create account |
| POST | `/eo/activate` | become an organizer |
| POST | `/auth/login` | log in |
| GET | `/auth/me` | rehydrate session |
| POST | `/auth/logout` | end session |
| POST | `/profile/photo` | avatar (multipart) |
| **Events** |||
| GET | `/events/my` | the organizer's events (paginated) |
| POST | `/events` | create, with ticket types |
| PUT/POST | `/events/{id}` | update |
| POST | `/events/{id}/banner` | upload banner (multipart) |
| PATCH | `/events/{id}/toggle` | publish / unpublish |
| **Staff & agents** |||
| GET | `/events/{eventId}/members` | list staff |
| POST | `/events/{eventId}/members` | add staff |
| PUT | `/events/{eventId}/members/{memberId}` | update staff |
| DELETE | `/events/{eventId}/members/{memberId}` | remove staff |
| **Agent sales reports** |||
| GET | `/events/{eventId}/agents/summary` | all agents, totals |
| GET | `/events/{eventId}/agents/orders` | all agent orders |
| GET | `/events/{eventId}/agents/{agentId}/summary` | one agent |
| GET | `/events/{eventId}/agents/{agentId}/orders` | one agent's orders |
| **Talents** |||
| GET | `/talents/mine` | own submissions |
| POST | `/talents` | submit a talent |
| PUT | `/talents/{id}` | update |
| DELETE | `/talents/{id}` | delete |
| **Event lineup** |||
| GET | `/events/{eventId}/talents` | lineup (public) |
| POST | `/events/{eventId}/talents` | add to lineup |
| PUT | `/events/{eventId}/talents/{id}` | update entry |
| DELETE | `/events/{eventId}/talents/{id}` | remove entry |
| **Reference data (public)** |||
| GET | `/categories` · `/provinces` · `/cities` · `/banks` · `/talents` | pickers |

All the EO rows above sit behind the `eo` middleware — see "EO gate" at the end.

---

## Events

### GET /events/my

```
GET /events/my?limit=15&search=jakarta
```

Paginated. `meta` is `{ total, page, limit, pages }` — **not** Laravel's
`current_page` / `per_page` / `last_page`.

Each event is **nested**, not flat:

```json
{
  "id": "01a03ec4-5f28-73b9-8844-d6fc3c141e7d",
  "event_id": "EVT9001",
  "title": "Jakarta September Music Fest 2026",
  "slug": "jakarta-september-music-fest-2026",
  "description": "...", "category": "Music", "banner_url": null,
  "pic":      { "name": "Ples Demo Organizer", "identity_type": "ktp",
                "identity_type_label": "KTP",
                "identity_number": null, "npwp": null },
  "schedule": { "start_date": "2026-09-05", "end_date": "2026-09-05",
                "start_time": "15:00:00", "end_time": "23:00:00" },
  "location": { "is_online": false, "venue_name": "Lapangan Banteng",
                "address": "...", "city": "...", "province": "...",
                "latitude": null, "longitude": null }
}
```

`pic.identity_number` and `pic.npwp` come back **null for the owner** — they are
only decrypted for `SUPER_ADMIN`. So the EO cannot read back the KTP/NPWP they
submitted. Do not render an edit form pre-filled from these; treat them as
write-only and leave blank unless changing.

**Two id fields.** `id` is the UUID primary key; `event_id` is the human code
(`EVT9001`). Path params accept **either** — the API sniffs the format. Prefer
`event_id` in URLs; it is what users recognise.

### POST /events

Creates the event **and** its ticket types in one call. Lands in
`verification_status: "pending"` — it cannot sell until a SUPER_ADMIN verifies.
Surface that state clearly; organizers otherwise think publishing is enough.

| Field | Rules |
|---|---|
| `title` | **required**, max 255 |
| `slug` | optional, lowercase-hyphen only (`^[a-z0-9]+(?:-[a-z0-9]+)*$`); auto-generated if omitted |
| `description` | optional |
| `category` | optional, max 100 — free string, **not** an FK. Populate the picker from `GET /categories` but send the name |
| `pic_name` | **required**, max 255 |
| `pic_identity_type` | **required**, one of `ktp` `sim` `passport` |
| `pic_identity_number` | **required**, max 50 |
| `pic_npwp` | optional, max 30 |
| `start_date` | **required**, `YYYY-MM-DD`, today or later |
| `end_date` | **required**, `YYYY-MM-DD`, ≥ `start_date` |
| `start_time` / `end_time` | optional, **`HH:mm`** (not `HH:mm:ss`) |
| `is_online` | optional bool |
| `venue_name` / `address` / `city` / `province` | optional |
| `latitude` / `longitude` | optional, −90..90 / −180..180 |
| `show_status` | optional bool |
| `ticket_types[]` | optional array |
| `ticket_types.*.name` | **required**, max 100 |
| `ticket_types.*.price` | **required**, numeric ≥ 0 |
| `ticket_types.*.quota` | **required**, integer ≥ 1 |
| `ticket_types.*.description` | optional |
| `ticket_types.*.is_active` | optional bool |
| `ticket_types.*.sale_start` / `sale_end` | optional date, `sale_end` ≥ `sale_start` |

Note the asymmetry: **times are `HH:mm`, sale windows are full datetimes**
(`2026-08-01 00:00:00`).

422 with an empty body returns exactly:
```json
{"errors":{"title":[...],"pic_name":[...],"pic_identity_type":[...],
           "pic_identity_number":[...],"start_date":[...],"end_date":[...]}}
```

### PUT or POST /events/{id}

Same fields, all `sometimes` — send only what changed. `POST` exists as a
multipart-friendly alias (PHP does not parse multipart on PUT); use `POST` when
sending files, `PUT` for JSON.

**`ticket_types` replaces the whole set.** Omit the key to leave them alone;
send it and whatever you send becomes the complete list. Never send a partial
array — that silently deletes the rest.

### POST /events/{id}/banner

`multipart/form-data`, field `banner`. jpg/jpeg/png/webp, **max 5 MB**.
(`POST /events/{id}` also accepts `banner`.)

### PATCH /events/{id}/toggle

No body. Flips published state and returns the event. `message` tells you which
way it went: `"Event activated."` / `"Event deactivated."` — the boolean is not
in a dedicated field, so read the message or re-read the event.

---

## Staff & agents

Members are a **separate auth system** — they log in at
`/organizer-auth/login` with `uid` + password, not email. The dashboard creates
them; it does not log in as them.

### POST /events/{eventId}/members

| Field | Rules |
|---|---|
| `name` | **required**, max 255 |
| `password` | **required**, min 8 |
| `role` | **required**: `EO_STAFF` `GATE_OFFICER` `MITRA_TICKET_BOX` `BAND` `MEDIA` `SPONSOR` |
| `email` | optional, max 100 |
| `commission_rate` | optional, 0–100 (percent; only meaningful for `MITRA_TICKET_BOX`) |

The response contains the generated `uid`
(`{event_code}-{role_prefix}-{seq}`, e.g. `EVT9001-AGT-0001`). **Show it on
creation and make it copyable** — it is the member's login username, and the
password is never retrievable afterwards.

Role prefixes: `STF` `GTE` `AGT` `BND` `MDA` `SPN`.

### PUT /events/{eventId}/members/{memberId}

All `sometimes`: `name`, `email`, `password` (min 8), `role`, `is_active`,
`commission_rate`. Prefer `is_active: false` over DELETE.

---

## Agent sales reports

### GET /events/{eventId}/agents/summary

```json
{
  "data": {
    "totals": { "total_orders": 0, "total_tickets_sold": 0,
                "total_revenue": 0, "total_commission_owed": 0 },
    "agents": []
  }
}
```

Not paginated — `data` is an object, not a list. `agents[]` carries the same
per-agent figures. Money is a plain IDR number; format as Rupiah, no decimals.

### GET /events/{eventId}/agents/orders

Paginated, `?limit=` and `?search=` (order number, buyer name, buyer phone).
Per-agent variants take `{agentId}` — the numeric member id, **not** the uid.

---

## Talents and lineup

Two different things:

- **Talent** — a performer in the shared directory, submitted by an EO and
  verified by an admin. Reusable across events.
- **Lineup entry** — a talent (or a free-text name) attached to one event.

### POST /talents

| Field | Rules |
|---|---|
| `name` | **required**, max 150 |
| `type` | **required**: `personal` or `group` |
| `category` | **required**: `music` `band` `dj` `comedian` `speaker` `mc` `dancer` `other` |
| `slug` `genre` `bio` (max 2000) `origin_city` | optional |
| `contact_name` `contact_phone` `contact_email` | optional |
| `instagram` `tiktok` `youtube` `spotify` | optional |

Submissions start unverified; admin verifies them.

### POST /events/{eventId}/talents

| Field | Rules |
|---|---|
| `talent_id` | optional, must exist |
| `free_name` | optional, max 150 |
| `role` | **required**: `headliner` `opening_act` `dj` `mc` `special_guest` `performer` |
| `performance_order` | optional integer ≥ 0 |
| `performance_time` | optional string, max 50 (free text, e.g. `"20:00"`) |

**One of `talent_id` or `free_name` is required** — omitting both returns 422
with `"Either talent_id or free_name is required."` and no `errors` object, so
handle that message directly. `free_name` covers acts not in the directory.

`GET /events/{eventId}/talents` is public and returns a bare array (no `meta`).

---

## The EO gate

Every EO endpoint returns this when `is_organizer` is false:

```
403 { "status": "error",
      "message": "This account is not an event organizer yet.",
      "errors": { "code": "EO_NOT_ACTIVATED" } }
```

Branch on `errors.code`, route to activation, **do not log the user out.**

Ownership is checked separately: touching an event you do not own returns
`403 "Forbidden."` or `404`. Those are real errors — show them.

---

## Known-broken on dev (verify before building against)

- `GET /users?search=` and `GET /admin/events?search=` return **500** — the code
  uses PostgreSQL `ilike` and dev runs MariaDB. `GET /events/my?search=` was
  working when checked, but the same operator appears in `EventRepository`,
  `OrderRepository`, `UserRepository` and `CategoryRepository`, so treat every
  `search` param as unverified until the backend fix lands.
- 404s from some endpoints arrive as **HTTP 500** with a "not found" message
  (e.g. `GET /users/{uid}`). Check the message, not just the status.
- There is **no password reset endpoint**. Confirm before designing that flow.
- There is **no EO dashboard summary/stats endpoint**. Build the shell from
  `/events/my` plus the per-event agent summaries, or ask for one.

## Requirements

1. Paginate on `meta.page` / `meta.limit` / `meta.pages`.
2. Events are nested — read `pic`, `schedule`, `location` as sub-objects.
3. Treat `pic.identity_number` / `pic.npwp` as write-only; they read back null.
4. Times are `HH:mm`; sale windows are full datetimes.
5. Sending `ticket_types` replaces every ticket type on the event.
6. Show `verification_status` prominently — pending events cannot sell.
7. Display the member `uid` on creation; it is their login and it is not
   recoverable later.
8. Handle `403` + `errors.code === "EO_NOT_ACTIVATED"` as onboarding.
9. Use `event_id` (`EVT9001`) in URLs, not the UUID.
