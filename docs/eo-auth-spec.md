# EO dashboard — register, login, session

Implement authentication for the Event Organizer dashboard against the Plesticket
API. Every request/response below was captured from the live dev API, not
written from a schema.

**Base URL:** `{API_BASE_URL}` — dev is `https://apidevticket.plescreative.com/api`

---

## The one thing to understand first

**There is no separate EO account type.** One `users` table, one register
endpoint, one login endpoint — shared with the buyer app. What makes an account
an organizer is a boolean flag, `is_organizer`.

```
POST /auth/register  →  always is_organizer: false
POST /eo/activate    →  flips it to true
```

So **EO signup is two calls, chained**, and should look like one step to the
user. This is deliberate: the same person can organise events and buy tickets to
someone else's, so it is a flag rather than a role.

Consequences for you:

- Do not build a separate EO registration form expecting a different endpoint.
- An existing buyer can become an organizer without making a second account —
  they log in normally, then you call `/eo/activate`.
- `role` is **not** how you detect an organizer. It stays `REGISTERED_USER`
  forever. Check `is_organizer`.

---

## Envelope

Success:
```json
{ "status": "success", "message": "...", "data": { } }
```
Error:
```json
{ "status": "error", "message": "...", "errors": { } }
```

`errors` appears only on 422, shaped `{ field: [messages] }`. `message` on 4xx is
written for humans and is safe to display.

**Do not match on message text.** `/auth/login` returns `"Validation failed"`
while `/auth/register` returns `"Validation failed."` with a period. Branch on
HTTP status and the `errors` keys.

---

## 1. Register

```
POST /auth/register
Content-Type: application/json
```

| Field | Rules |
|---|---|
| `name` | **required**, max 255 |
| `email` | **required**, valid email, unique |
| `password` | **required**, min 8, must match `password_confirmation` |
| `password_confirmation` | **required** (this exact key) |
| `phone` | optional, max 20 |
| `username` | optional, max 50, letters/numbers/dash/underscore only, unique |
| `date_of_birth` | optional, **`YYYY-MM-DD`**, must be before today |

```json
{
  "name": "EO Spec Probe",
  "email": "organizer@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "phone": "081200000001",
  "username": "eo_spec_probe",
  "date_of_birth": "1990-03-11"
}
```

**201:**
```json
{
  "status": "success",
  "message": "Registration successful.",
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGci...",
    "user": {
      "id": 9, "uid": "U000009", "name": "EO Spec Probe",
      "username": "eo_spec_probe", "email": "organizer@example.com",
      "phone": "081200000001", "date_of_birth": "1990-03-11", "photo": null,
      "role": "REGISTERED_USER", "role_label": "Registered User",
      "is_organizer": false, "is_active": true,
      "created_at": "2026-09-03T06:59:37.000000Z"
    }
  }
}
```

Note `is_organizer: false` — registration alone does not make an organizer.

**422 examples** (all verified):

```json
{"errors": {"password": ["The password field must be at least 8 characters."]}}
{"errors": {"password": ["The password field confirmation does not match."]}}
{"errors": {"email":    ["The email has already been taken."]}}
{"errors": {"date_of_birth": ["The date of birth field must match the format Y-m-d."]}}
```

Note the confirmation error is keyed under **`password`**, not
`password_confirmation` — attach it to the right input yourself.

## 2. Activate organizer access

```
POST /eo/activate
Authorization: Bearer <token from register or login>
```

No body. Call it immediately after register in the signup flow.

**200:**
```json
{
  "status": "success",
  "message": "Event organizer access activated.",
  "data": { "token": "<replacement>", "user": { ..., "is_organizer": true } }
}
```

**Idempotent.** Calling it again returns 200 with
`"message": "This account is already an event organizer."` and the same shape —
so it is safe to call on every login if you prefer that to tracking state.

### About the replacement token

Store it and use it. But note what it is **not**: authorization does not depend
on it. The `eo` middleware reads `is_organizer` from the database, so the token
you already hold authorizes EO routes immediately after activation. The
replacement only stops a client that decodes the JWT from reading a stale
`is_organizer: false` claim.

Practically: swap it in, and do not build retry logic around "the old token was
rejected" — it will not be.

## 3. Login

```
POST /auth/login
Content-Type: application/json

{ "email": "organizer@example.com", "password": "password123" }
```

Same endpoint the buyer app uses.

**200:** identical shape to register — `{ token, user }` — with the current
`is_organizer` value.

**401** — wrong password *and* unknown email both return exactly:
```json
{ "status": "error", "message": "Invalid email or password." }
```
Deliberately identical, so do not try to tell the user which was wrong.

**422** — missing fields:
```json
{ "status": "error", "message": "Validation failed",
  "errors": { "email": ["The email field is required."],
              "password": ["The password field is required."] } }
```

### After login, branch on `is_organizer`

- `true` → into the dashboard
- `false` → this is a valid account that is not yet an organizer. Show an
  onboarding step offering activation, then call `/eo/activate`. **Do not treat
  it as a failed login.**

## 4. Session

```
GET  /auth/me       Authorization: Bearer <token>   → 200 { data: <user> }
POST /auth/logout   Authorization: Bearer <token>   → 200 { data: null }
```

`/auth/me` returns the user object directly in `data` (no `token`), with
`"message": "ok"`. Use it on app boot to re-hydrate the session and pick up an
`is_organizer` that changed elsewhere.

`/auth/logout` invalidates the token server-side — a subsequent `/auth/me` with
it returns 401. Clear local state regardless of the response.

### 401 handling

Missing token, malformed token, expired token, and logged-out token **all**
return the same thing:
```json
{ "status": "error", "message": "Unauthenticated." }
```

**Tokens last 1 hour** (`JWT_TTL=60` minutes) and there is **no refresh
endpoint**. So any 401 on any call means: clear the session and send the user to
login. Build one interceptor that does this globally — an EO filling in a long
event form will otherwise lose their work to a silent expiry.

Consider warning the user before the hour is up, or re-prompting for login
before submitting long forms.

## 5. EO route rejection — the one you must special-case

Every organizer route returns this when `is_organizer` is false:

```
403
{ "status": "error",
  "message": "This account is not an event organizer yet.",
  "errors": { "code": "EO_NOT_ACTIVATED" } }
```

Branch on `errors.code === "EO_NOT_ACTIVATED"`, not on the status or message.

**This is not an auth failure.** Route to the activation step. Logging the user
out here traps them in a loop: they log back in successfully and hit the same
403.

---

## Requirements

1. EO signup = `register` → `/eo/activate`, chained, presented as one step.
2. Detect organizer status with `is_organizer`, never with `role` — `role` stays
   `REGISTERED_USER`.
3. After login, `is_organizer: false` routes to activation, not to an error.
4. Handle `403` + `errors.code === "EO_NOT_ACTIVATED"` as "needs onboarding".
5. One global 401 interceptor → clear session → login. No refresh endpoint
   exists; tokens expire after 1 hour.
6. Send `password_confirmation`; render its error under the `password` field.
7. `date_of_birth` must be `YYYY-MM-DD`.
8. Never distinguish "wrong password" from "unknown email" in the UI.
9. Branch on HTTP status and `errors` keys, never on message text.

## Out of scope

- Any separate EO registration endpoint (there isn't one)
- Token refresh (no endpoint)
- Password reset (no endpoint exists yet — confirm before designing the flow)
- Admin routes; `SUPER_ADMIN` is a different console entirely, and admin is not
  a superset of EO
