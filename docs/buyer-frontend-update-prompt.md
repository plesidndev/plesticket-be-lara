# Update the Plesticket buyer app for the new auth + payment API

You are updating an **existing** buyer-facing frontend. The backend has changed:
accounts now carry an `is_organizer` flag, organizer routes are gated, orders
expire for real, and checkout runs through a payment gateway (QRIS via Xendit).

Do not build any organizer/event-management UI, and do not build webhook or
gateway-facing code. This app is buyer-only.

## Conventions

**Base URL:** `{API_BASE_URL}` (e.g. `https://api.plesticket.com/api`)

**Auth:** JWT bearer on every authenticated request:
```
Authorization: Bearer <token>
```

**Envelope.** Success:
```json
{ "status": "success", "message": "...", "data": { } }
```
Error:
```json
{ "status": "error", "message": "...", "errors": { } }
```
`errors` appears only on 422 validation failures and on the 403 described below.
`message` on 4xx is written for humans and is safe to display.

**Money:** JSON numbers in IDR. Format with no decimals — `300000` → `Rp 300.000`.

**Timestamps:** ISO 8601 UTC (`2026-08-26T13:56:33.000000Z`). Convert for display.

---

# 1. Registration

```
POST /api/auth/register
Content-Type: application/json

{
  "name": "Budi Santoso",
  "email": "budi@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "phone": "081234567890",
  "username": "budi_s",
  "date_of_birth": "1995-05-15"
}
```

| Field | Required | Rules |
|---|---|---|
| `name` | yes | max 255 |
| `email` | yes | valid email, unique |
| `password` | yes | min 8, must match `password_confirmation` |
| `password_confirmation` | yes | must equal `password` |
| `phone` | no | max 20 |
| `username` | no | max 50, letters/numbers/dash/underscore only, unique |
| `date_of_birth` | no | `YYYY-MM-DD`, must be before today |

`201` on success — **the user is logged in immediately**, no separate login call:

```json
{
  "status": "success",
  "message": "Registration successful.",
  "data": {
    "token": "eyJ0eXAiOiJKV1Qi...",
    "user": {
      "id": 1,
      "uid": "U000001",
      "name": "Budi Santoso",
      "username": "budi_s",
      "email": "budi@example.com",
      "phone": "081234567890",
      "date_of_birth": "1995-05-15",
      "photo": null,
      "role": "REGISTERED_USER",
      "role_label": "Registered User",
      "is_organizer": false,
      "is_active": true,
      "created_at": "2026-08-26T13:26:33.000000Z"
    }
  }
}
```

`422` on validation failure — `errors` is `{ field: [messages] }`. Map each key
onto its form field; do not just show `message`:

```json
{
  "status": "error",
  "message": "Validation failed.",
  "errors": {
    "email": ["The email has already been taken."],
    "password": [
      "The password field must be at least 8 characters.",
      "The password field confirmation does not match."
    ]
  }
}
```

Note a field can carry **multiple** messages — render all of them.

# 2. Login

```
POST /api/auth/login
{ "email": "budi@example.com", "password": "password123" }
```

`200` returns the identical `{ token, user }` shape as register.

`401` on bad credentials:
```json
{ "status": "error", "message": "Invalid email or password." }
```
The message is deliberately vague about which field was wrong — keep it that way,
don't tell the user "no such email".

# 3. Current user

```
GET /api/auth/me
```

Returns the user object **directly at `data`** — not nested under `data.user`
like register and login. This asymmetry is easy to get wrong:

```json
{ "status": "success", "message": "ok", "data": { "id": 1, "uid": "U000001", ... } }
```

Also available: `POST /api/auth/logout`, and `POST /api/profile/photo`
(multipart, field `photo`, jpg/jpeg/png/webp, max 2MB).

# 4. `is_organizer` — what it means here

New field on every user object. `true` means the account can also organise
events, in the **separate organizer app**.

For this app:

- **Never call organizer routes.** `/events/my`, `POST /events`, `/talents/mine`,
  `/events/{id}/members`, `/events/{id}/agents/*` and similar return `403`.
- If you have any leftover "my events" / "create event" UI, remove it or turn it
  into a link out to the organizer app.
- A `403` from those routes looks like this — the account is valid, it just is
  not an organizer:

```json
{
  "status": "error",
  "message": "This account is not an event organizer yet.",
  "errors": { "code": "EO_NOT_ACTIVATED" }
}
```

**Do not log the user out on this 403.** Treat `errors.code === "EO_NOT_ACTIVATED"`
as "wrong app", not "session expired".

Optional, only if product asks for a "start selling your own event" entry point:
`POST /api/eo/activate` activates the account and returns `{ token, user }` with
a **replacement token** you must swap in. Then send them to the organizer app.

---

# 5. Checkout

## 5a. Payment methods

```
GET /api/payment-methods
```
Public — safe to render before login. Methods arrive **pre-grouped by type**;
render the groups as sections, don't regroup.

```json
{
  "status": "success",
  "data": [
    {
      "type": "qris",
      "label": "QRIS",
      "methods": [
        {
          "code": "qris",
          "name": "QRIS",
          "description": "Scan with GoPay, OVO, DANA, ShopeePay, or any mobile banking app.",
          "type": "qris",
          "provider": "xendit",
          "logo_url": "https://api.example.com/images/payments/qris.png",
          "min_amount": 1000,
          "max_amount": 10000000,
          "fee": { "flat": 0, "percent": 0.7 }
        }
      ]
    }
  ]
}
```

- Only **enabled** methods are returned. Today that is QRIS alone — make sure a
  single-method list doesn't look broken. Bank VA methods will appear in this
  same shape when enabled, with no frontend change required.
- `logo_url` artwork may not exist yet and can 404 — always render a fallback.
- `fee` is informational. **Do not add it to the buyer's total** — Plesticket
  absorbs it.
- `min_amount` / `max_amount` may be `null`. Grey out (don't hide) a method the
  order total falls outside.

## 5b. Create the order

Two modes. **Use Mode B** — the buyer picks their payment method on the checkout
screen, so booking and payment go out in a single call and they land straight on
the QR. Mode A exists for flows where the method is not yet known.

You still need the standalone payment endpoint (5c) regardless, because switching
method and retrying after a gateway failure both go through it.

**Mode A — order only, method chosen later:**
```
POST /api/orders
{ "event_id": "EVT9001", "items": [{ "ticket_type_id": 1, "quantity": 1 }] }
```
`data` is the order object directly:
```json
{ "status": "success", "message": "Order created.",
  "data": { "order_number": "ORD2026082600001", "status": "pending_payment", ... } }
```

**Mode B — order + payment in one call (USE THIS):**
```
POST /api/orders
{ "event_id": "EVT9001", "payment_method": "qris",
  "items": [{ "ticket_type_id": 1, "quantity": 1 }] }
```
`data` is now `{ order, payment }`:
```json
{
  "status": "success",
  "message": "Order and payment created.",
  "data": {
    "order":   { "order_number": "ORD2026082600002", "status": "pending_payment", ... },
    "payment": { "reference_id": "...", "instruction": { "qr_string": "0002010102..." }, ... }
  }
}
```

Rules:
- `event_id` accepts the public event code (`EVT9001`) or the event UUID.
- `quantity` is 1–10 per item.
- `payment_method` must be an **enabled** `code` from `/api/payment-methods`.
- Write the response parser to handle both anyway — `const order = data.order ?? data`
  — so the app survives if someone starts sending `payment_method`.

### Mode B failure: HTTP 502, but the order EXISTS

This is the one case Mode B makes you handle, and it must be handled — getting it
wrong double-books ticket quota.

If the gateway rejects or is unreachable, you get `502` — and the order was
still created. **Do not re-post the order, you will double-book quota.**

```json
{
  "status": "error",
  "message": "API version in header is required",
  "data": {
    "order": { "order_number": "ORD2026082600008", "status": "pending_payment", ... },
    "payment": null,
    "payment_retry_url": "https://api.../api/orders/ORD2026082600008/payments"
  }
}
```

Keep `data.order.order_number`, show a retry button, and retry the **payment**
against that order (5c). `message` is the provider's own text — show a generic
"payment provider unavailable, please try again" instead of surfacing it raw.

## 5c. Create / switch payment

```
POST /api/orders/{orderNumber}/payments
{ "method_code": "qris" }
```

`201`:
```json
{
  "status": "success",
  "message": "Payment created.",
  "data": {
    "reference_id": "ORD2026082600002-8ZJN7R",
    "method_code": "qris",
    "type": "qris",
    "provider": "xendit",
    "status": "pending",
    "status_label": "Waiting for payment",
    "amount": 300000,
    "expires_at": "2026-08-26T13:56:33.000000Z",
    "paid_at": null,
    "created_at": "2026-08-26T13:26:33.000000Z",
    "instruction": { "qr_string": "00020101021226670016COM.NOBUBANK..." }
  }
}
```

### The `instruction` object

Holds only the key relevant to the method. **Absent, not null**, when not
applicable. Branch on `type`, never on `method_code`, so new banks need no code
change:

| `type` | Key | Render |
|---|---|---|
| `qris` | `qr_string` | A QR code — see below |
| `virtual_account` | `account_number` | VA number + copy button |
| `bank_transfer` | `account_number` | Account number + bank name |

**Defensive note:** when nothing is populated the API returns `instruction` as an
empty **array** `[]`, not `{}`. Guard with `instruction?.qr_string` and treat a
missing value as "payment could not be prepared — retry", not as a blank screen.

**`qr_string` is NOT an image URL.** It is a raw EMVCo QRIS payload. Render it
with a QR library (`qrcode.react`, `qrcode`, `react-native-qrcode-svg`). Passing
it to `<img src>` silently shows nothing. Render at 256px+ with error correction
level M or higher — QRIS payloads are long and small QRs fail to scan on cheap
phones. Offer the raw string as copyable text too.

### Switching method

Post again with a different `method_code`. The backend retires the old charge —
**the previous QR / VA stops working immediately** — so replace the on-screen
instruction entirely. Never show two.

Posting the **same** `method_code` while a payment is live returns the existing
one unchanged. Safe on refresh or double-click.

## 5d. Poll for confirmation

```
GET /api/orders/{orderNumber}/payments
```
Same object as 5c. There is no websocket or client callback — polling is the
only mechanism.

- Every **3s** for the first 2 minutes, then every **10s**
- Stop on any terminal status, at `expires_at`, or when the tab is hidden
  (resume on focus with an immediate fetch)
- Never faster than 2s

| `status` | Terminal | Meaning |
|---|---|---|
| `pending` | no | Keep polling |
| `paid` | yes | Success → success screen |
| `expired` | yes | Charge lapsed; offer a new payment |
| `failed` | yes | Rejected or underpaid. Offer retry; tell them to contact support if money left their account |
| `cancelled` | yes | Superseded by a method switch. You should already be showing the new payment — do not surface as an error |

`status_label` is a ready-made human string. Prefer it over your own map so new
statuses degrade gracefully.

`404` with "No payment has been started for this order." means exactly that —
send them back to method selection.

## 5e. Order expiry — this is now enforced

Orders hold ticket quota for **30 minutes**, and a background job now genuinely
releases abandoned ones. Previously an order lingered forever; it no longer does.

- Run a visible countdown to the order's / payment's `expires_at`.
- At zero: stop polling, show an expired state with "choose another method".
  **Do not auto-create a new payment** — the order may be gone too.
- A stale `order_number` from a previous session may now fail with `422`
  `"Order has expired. Please create a new order."` Handle it by sending the
  user back to the event page, not by showing a dead end.

## 5f. Success

```
GET /api/orders/{orderNumber}
```
Tickets are minted at payment time, so they are present as soon as `status` is
`paid`:

```json
{
  "data": {
    "order_number": "ORD2026082600002",
    "status": "paid",
    "total_price": 300000,
    "payment_method": "qris",
    "paid_at": "2026-08-26T13:26:33.000000Z",
    "event": { "event_id": "EVT9001", "title": "...", "slug": "...",
               "banner_url": null, "start_date": "2026-09-05",
               "venue_name": "Lapangan Banteng", "city": "Kota Jakarta Pusat" },
    "items": [{
      "id": 1, "ticket_type_id": 1, "ticket_type_name": "Festival Pass",
      "unit_price": 150000, "quantity": 2, "subtotal": 300000,
      "tickets": [
        { "ticket_code": "71IFUUJ1N5JS", "holder_name": "Budi Santoso", "status": "active", "scanned_at": null },
        { "ticket_code": "BC93XHOMMECS", "holder_name": "Budi Santoso", "status": "active", "scanned_at": null }
      ]
    }]
  }
}
```

`ticket_code` is scanned at the gate — render each as its own QR code.
`banner_url`, `venue_name`, `city` are nullable.

Other buyer endpoints: `GET /api/orders?limit=15` (paginated, `meta` has
`total/page/limit/pages`), `POST /api/orders/{n}/cancel`,
`GET /api/tickets/{code}`.

---

# Error handling

| Code | When | Do |
|---|---|---|
| `401` | Missing/expired JWT | Redirect to login, preserve intent |
| `403` + `EO_NOT_ACTIVATED` | Organizer route from a buyer account | **Not a session problem.** Don't log out |
| `404` | Unknown method, order not yours, no payment started | Show `message` |
| `422` | Validation, disabled method, expired/already-paid order | Show `message`; map `errors` to fields when present |
| `502` | Gateway rejected/unreachable | **Retryable, nothing was charged.** Never say "payment failed". On Mode B, the order still exists — retry payment, don't re-create |
| `500` | Server error | Generic + retry |

# Requirements

1. Full registration form with per-field `errors` mapping, including multiple
   messages per field.
2. Remember `/auth/me` returns the user at `data`, while register/login nest it
   at `data.user`.
3. Drive the method picker entirely off `/api/payment-methods` — no hardcoded list.
4. Branch on `instruction` key presence and `type`, never `method_code`.
5. Render `qr_string` with a QR library, not `<img>`. Guard against `[]`.
6. Poll with backoff; stop on terminal status, expiry, or hidden tab.
7. Live countdown to `expires_at`.
8. Use Mode B (order + payment in one call). On `502` the order already exists:
   keep `data.order.order_number`, retry via `POST /orders/{n}/payments`, and
   **never re-post the order** — that double-books quota.
9. Because the gateway is called inside order creation, that request can take
   several seconds. Show a blocking progress state and disable the submit button
   so an impatient buyer cannot fire a second order.
10. Remove or externalise any organizer UI; never treat `EO_NOT_ACTIVATED` as logout.

# Out of scope

- Event creation / management (separate organizer app)
- Ticket scanning (separate gate app)
- Webhooks, Xendit SDKs, or any gateway credential in the client
- Adding gateway fees to the total
- Refunds
