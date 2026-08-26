# Build the Plesticket checkout payment flow

Implement the payment step of checkout against the Plesticket API. The backend is
done — you are building UI only. Do not build any webhook, callback, or
gateway-facing code: payment confirmation arrives server-side and the client
learns about it by polling.

## Conventions

**Base URL:** `{API_BASE_URL}` — all paths below are relative to it.

**Auth:** every endpoint except `GET /api/payment-methods` needs the buyer's JWT:

```
Authorization: Bearer <token>
```

**Every response uses the same envelope.** Success:

```json
{ "status": "success", "message": "Payment created.", "data": { } }
```

Error:

```json
{ "status": "error", "message": "Human-readable reason.", "errors": { } }
```

`errors` appears only on 422 validation failures. `message` is safe to show to
the user on 4xx — it is written for humans (e.g. `Payment method "Bank Mandiri
Transfer" is not available yet.`).

**Money:** `amount` and `total_price` are JSON numbers in IDR. Format as Rupiah
with no decimal places: `300000` → `Rp 300.000`.

**Timestamps:** ISO 8601 UTC (`2026-08-26T05:50:18.000000Z`). Convert to local
time for display.

---

## The flow

```
1. Order already exists (status: pending_payment, 30-minute hold)
2. GET  /api/payment-methods            → let the buyer pick
3. POST /api/orders/{orderNumber}/payments  { method_code }  → get instruction
4. Show the instruction + countdown; poll until status changes
5. status: "paid" → success screen
```

---

## 1. List payment methods

```
GET /api/payment-methods
```

Public — no auth needed, so you can render the picker before the buyer logs in.

Methods come **pre-grouped by type**. Render the groups as sections; do not
regroup client-side.

```json
{
  "status": "success",
  "message": "Payment methods retrieved.",
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

Notes:

- **Only enabled methods are returned.** Today that is QRIS alone. Bank BRI and
  Mandiri are configured but switched off, and will appear in this same shape
  when they go live — so drive the UI entirely off this response. Never hardcode
  a method list, and make sure a single-method response doesn't look broken.
- `code` is what you send back in step 3. Everything else is display data.
- `logo_url` is absolute and may 404 while artwork is pending — render a
  fallback, don't let a broken image break the row.
- `fee` is informational. **Do not add it to the amount shown to the buyer** —
  Plesticket absorbs it. Display it only if product asks.
- `min_amount` / `max_amount` are in IDR and may be `null`. Grey out (don't hide)
  a method the order total falls outside, so the buyer understands why.

## 2. Create the payment

```
POST /api/orders/{orderNumber}/payments
Content-Type: application/json

{ "method_code": "qris" }
```

`201` on success:

```json
{
  "status": "success",
  "message": "Payment created.",
  "data": {
    "reference_id": "ORD202608260001-J9WXEC",
    "method_code": "qris",
    "type": "qris",
    "provider": "xendit",
    "status": "pending",
    "status_label": "Waiting for payment",
    "amount": 300000,
    "expires_at": "2026-08-26T05:50:18.000000Z",
    "paid_at": null,
    "created_at": "2026-08-26T05:20:18.000000Z",
    "instruction": {
      "qr_string": "00020101021226670016COM.NOBUBANK.WWW0118936005030000089856..."
    }
  }
}
```

### The `instruction` object — read this carefully

`instruction` holds only the key relevant to the chosen method. **Irrelevant keys
are absent, not null.** Branch on presence, and branch on `type` rather than on
`method_code`, so new banks work without a code change:

| `type` | Key present | What to render |
|---|---|---|
| `qris` | `qr_string` | A QR code — see below |
| `virtual_account` | `account_number` | The VA number, with a copy button |
| `bank_transfer` | `account_number` | Account number + bank name from the method |

**`qr_string` is NOT an image URL.** It is a raw EMVCo QRIS payload. You must
render it into a QR code client-side with a QR library (`qrcode.react`,
`qrcode`, `react-native-qrcode-svg`, …). Passing it to an `<img src>` will
silently show nothing. Also offer it as copyable text — some banking apps accept
a pasted payload.

Render at 256px or larger with error-correction level M or higher; QRIS payloads
are long and a small QR fails to scan on cheap phones.

### Switching payment method

Post again with a different `method_code`. The backend retires the old charge and
returns a fresh one, so **the old QR or VA number stops working immediately** —
always replace what is on screen with the new `instruction`, never show both.

Posting the **same** `method_code` while a payment is live returns the existing
one unchanged (still `201`). Safe on refresh or double-click; no need to guard.

## 3. Poll for confirmation

```
GET /api/orders/{orderNumber}/payments
```

Returns the same object as step 2. Poll it while the buyer pays — there is no
websocket or callback to the client.

Suggested strategy:

- Every **3 seconds** for the first 2 minutes, then every **10 seconds**.
- Stop on any terminal status, on `expires_at`, or when the tab is hidden
  (resume on focus, with an immediate fetch).
- Never poll faster than 2s.

### Status values

| `status` | Terminal | Meaning / what to do |
|---|---|---|
| `pending` | no | Awaiting payment. Keep polling. |
| `paid` | **yes** | Success. Stop polling, go to the success screen. |
| `expired` | **yes** | The charge lapsed. Offer to start a new payment. |
| `failed` | **yes** | Rejected or underpaid. Offer to retry; tell them to contact support if money left their account. |
| `cancelled` | **yes** | Superseded because they switched method. You should already be showing the new payment — don't surface this as an error. |

`status_label` is a ready-made human string for each. Use it rather than writing
your own map, so new statuses degrade gracefully.

### Expiry countdown

Run a countdown to `expires_at`. At zero, stop polling and show an expired state
with a "choose another method" action — **do not auto-create a new payment**, the
order may have expired too.

The order itself has a 30-minute hold. The payment never outlives it, so
`expires_at` is the only clock you need.

## 4. Confirm the order

Once `status` is `paid`, fetch the order for the tickets:

```
GET /api/orders/{orderNumber}
```

```json
{
  "status": "success",
  "message": "Order retrieved.",
  "data": {
    "order_number": "ORD202608260001",
    "status": "paid",
    "total_price": 300000,
    "payment_method": "qris",
    "paid_at": "2026-08-26T05:20:18.000000Z",
    "event": {
      "event_id": "EVT0001", "title": "Konser Senja", "slug": "konser-senja",
      "banner_url": null, "start_date": "2026-09-02",
      "venue_name": null, "city": null
    },
    "items": [
      {
        "id": 1, "ticket_type_id": 1, "ticket_type_name": "Presale",
        "unit_price": 150000, "quantity": 2, "subtotal": 300000,
        "tickets": [
          { "ticket_code": "71IFUUJ1N5JS", "holder_name": "Budi Santoso", "status": "active", "scanned_at": null },
          { "ticket_code": "BC93XHOMMECS", "holder_name": "Budi Santoso", "status": "active", "scanned_at": null }
        ]
      }
    ]
  }
}
```

Tickets are issued at the moment of payment, so they are present as soon as
`status` is `paid`. `ticket_code` is what gets scanned at the gate — render each
as its own QR code.

`banner_url`, `venue_name`, and `city` are nullable. Handle that.

---

## Error handling

| Code | When | Do |
|---|---|---|
| `401` | Missing/expired JWT | Redirect to login, preserve the order number |
| `404` | Unknown `method_code`, order not the buyer's, or no payment started yet | Show `message`; for "no payment started" send them back to method selection |
| `422` | Method disabled, amount out of range, order expired or already paid, or validation failed | Show `message`. If `errors` is present it is `{ field: [messages] }` |
| `502` | Payment provider rejected or unreachable | **Retryable.** Show `message` with a "try again" button — the buyer did nothing wrong and no money moved |
| `500` | Server error | Generic error + retry |

`502` is the one to get right: it means Xendit failed, not the buyer. Never show
it as "payment failed" — nothing was charged.

---

## Requirements

1. Drive the method picker entirely off `GET /api/payment-methods`. No hardcoded
   list, no assumption that QRIS is the only option.
2. Branch rendering on `instruction` key presence and `type` — never on
   `method_code`. New banks must work with zero changes.
3. Render `qr_string` with a QR library, not an `<img>`.
4. Poll with backoff, stop on terminal status or expiry, pause when hidden.
5. Show a live countdown to `expires_at`.
6. Switching method replaces the on-screen instruction completely.
7. Show `message` from 4xx responses; treat `502` as retryable, not as failure.
8. Handle the single-method case, nullable fields, and a missing logo without
   layout breakage.

## Out of scope

- Webhook or callback handling (server-side only)
- Talking to Xendit directly, or any Xendit SDK/key in the client
- Computing or adding gateway fees to the total
- Refunds
