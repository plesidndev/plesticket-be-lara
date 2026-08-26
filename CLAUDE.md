# Plesticket Backend (Laravel)

## Tech Stack
- **Language:** PHP 8.3
- **Framework:** Laravel 13 (laravel/framework v13.5)
- **Database:** PostgreSQL (pgsql driver)
- **Auth:** JWT via `tymon/jwt-auth` v2.3
- **Container:** Docker (nginx + php-fpm + supervisor)

## Project Structure
```
/app
  /Enums            - UserRole, OrganizerRole
  /Http
    /Controllers/Api - API controllers (no web controllers)
    /Middleware      - RoleMiddleware, Authenticate
    /Requests        - Form request validation classes (per domain/action)
    /Resources       - API resource transformers
  /Models            - Eloquent models
  /Providers         - AppServiceProvider, RepositoryServiceProvider
  /Repositories
    /Contracts       - Repository interfaces
    *.php            - Eloquent implementations
  /Services          - Business logic (coordinates repos, throws exceptions)
  /Traits            - ApiResponse (shared JSON helpers)
/bootstrap
  app.php            - Application bootstrap (routes, middleware, exception handlers)
  providers.php      - Service providers list
/database
  /migrations        - Laravel migration files
  /seeders           - SuperAdmin + Province + City seeders
/docker              - nginx.conf, supervisord.conf
/routes
  api.php            - All API routes (no routes/web.php)
```

## Architecture Pattern
Every domain follows: `Model` → `RepositoryInterface` → `Repository` → `Service` → `Controller`

- **Model:** Eloquent, no business logic
- **Repository:** Implements interface, all DB queries here
- **Service:** Business logic, throws PHP exceptions (never returns responses)
- **Controller:** Resolves request → calls service → returns JSON via `ApiResponse` trait
- **FormRequest:** Validates input, returns 422 JSON on failure (overrides `failedValidation`)
- **Resource:** Transforms Eloquent model to JSON array

## Authentication & Roles

### Two separate auth systems

| | Platform | Organizer |
|---|---|---|
| Login endpoint | `POST /api/auth/login` | `POST /api/organizer-auth/login` |
| Guard | `auth:api` | `auth:organizer` |
| Credentials | `email` + `password` | `uid` + `password` |
| Model | `User` | `OrganizerMember` |

### Platform roles (`App\Enums\UserRole`) — stored in `users.role`
```php
UserRole::SuperAdmin     // 'SUPER_ADMIN'      full platform access
UserRole::RegisteredUser // 'REGISTERED_USER'  creates events, manages organizer members
```

### Organizer roles (`App\Enums\OrganizerRole`) — stored in `organizer_members.role`
```php
OrganizerRole::EoStaff        // 'EO_STAFF'
OrganizerRole::GateOfficer    // 'GATE_OFFICER'
OrganizerRole::MitraTicketBox // 'MITRA_TICKET_BOX'
OrganizerRole::Band           // 'BAND'
OrganizerRole::Media          // 'MEDIA'
OrganizerRole::Sponsor        // 'SPONSOR'
```

### Buyer vs. EO — one account, two hats
`users.is_organizer` is a **flag, not a role**: the same account can organise
events and buy tickets to someone else's. `UserRole` stays two-valued
(`SUPER_ADMIN`, `REGISTERED_USER`).

- Activate with `POST /api/eo/activate` (self-serve, idempotent). It returns a
  **replacement token** — the caller's existing JWT claims `is_organizer:false`
  and would be refused until it expired. Clients must swap it in.
- Gate EO-only routes with the `eo` middleware:
  `Route::middleware(['auth:api', 'eo'])`
- The JWT and `UserResource` both carry `is_organizer`, so a frontend can gate
  without an extra call.
- A refusal returns **403** with `errors.code = EO_NOT_ACTIVATED`, so the EO
  frontend can route to onboarding instead of showing a permission error.

**Naming:** the middleware is `eo`, never `organizer` — in this codebase
"organizer" already means `organizer_members` (the staff an EO hires, on the
`auth:organizer` guard). An EO is a `users` row with `is_organizer = true`.

### User flow
1. User registers → `REGISTERED_USER`, `is_organizer = false`
2. User activates EO access, then creates an event
3. `SUPER_ADMIN` verifies the event
4. Verified event owner adds organizer members via API (with name, password, role)
5. Organizer members login at `POST /api/organizer-auth/login` using `uid` + `password`
6. Organizer member JWT carries: `uid`, `name`, `role`, `event_id`, `guard: organizer`

### Organizer member UID format
- Current: `OM{id:04d}` (e.g. `OM0001`)
- Future (after events domain): `{event_id}-{sequence:04d}` (e.g. `PRFKLK02399-0001`)

### JWT Claims
**Platform (`User` model):** `uid`, `name`, `email`, `role`
**Organizer (`OrganizerMember` model):** `uid`, `name`, `role`, `event_id`, `guard`

### Token flow — Platform
- `POST /api/auth/register` → creates `REGISTERED_USER`, returns `{ token, user }`
- `POST /api/auth/login`    → returns `{ token, user }`
- `GET  /api/auth/me`       → requires `auth:api`
- `POST /api/auth/logout`   → requires `auth:api`

### Token flow — Organizer
- `POST /api/organizer-auth/login`  → returns `{ token, member }`
- `GET  /api/organizer-auth/me`     → requires `auth:organizer`
- `POST /api/organizer-auth/logout` → requires `auth:organizer`

### Protecting routes
```php
Route::middleware('auth:api')->group(fn() => ...);
Route::middleware('auth:organizer')->group(fn() => ...);
Route::middleware(['auth:api', 'role:SUPER_ADMIN'])->group(fn() => ...);
```

## Environment Variables
```
APP_NAME, APP_ENV, APP_KEY, APP_DEBUG
DB_CONNECTION=pgsql, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD
JWT_SECRET, JWT_TTL (minutes, default 60)
CACHE_STORE, SESSION_DRIVER, QUEUE_CONNECTION
```

## Database Migrations
- `0001_01_01_000000` — users (id, uid, name, email, phone, password, role, is_active)
- `0001_01_01_000001` — cache
- `0001_01_01_000002` — jobs
- `2026_04_21_000001` — provinces (id, code, name)
- `2026_04_21_000002` — cities (id, province_code FK, name, type KABUPATEN|KOTA)
- `2026_04_21_000003` — organizer_members initial (superseded by 000004)
- `2026_04_21_000004` — organizer_members (id, uid, owner_id FK, event_id nullable, name, email, password, role, is_active)

Latest migration: `2026_04_21_000004_redesign_organizer_members_table`

## Seeders
```bash
php artisan db:seed                          # runs all seeders
php artisan db:seed --class=SuperAdminSeeder # superadmin@plesticket.com / adminpass
php artisan db:seed --class=ProvinceSeeder
php artisan db:seed --class=CitySeeder
```

## Response Format
Use the `ApiResponse` trait in every controller:
```php
$this->success('message', $data);           // 200
$this->created('message', $data);           // 201
$this->error('message', 422, $errors);      // any error code
$this->paginated('message', $data, $paginator); // paginated
```

JSON shape:
```json
{ "status": "success", "message": "...", "data": {} }
{ "status": "success", "message": "...", "data": [], "meta": { "total": 0, "page": 1, "limit": 10, "pages": 0 } }
{ "status": "error",   "message": "...", "errors": {} }
```

## Repository Binding
All interface→implementation bindings are in `RepositoryServiceProvider`:
```php
$this->app->bind(UserRepositoryInterface::class, UserRepository::class);
$this->app->bind(OrganizerMemberRepositoryInterface::class, OrganizerMemberRepository::class);
```
Register new bindings there — never in `AppServiceProvider`.

## Docker
```bash
# Run with compose (port 8081, separate postgres on port 5433)
docker-compose up --build

# Migrate + seed inside container
docker exec plesticket-lara-app php artisan migrate --seed
```

Container runs nginx (port 8080 internal → 8081 host) + php-fpm via supervisord.

## Payments

Payment methods are decoupled from the gateways that process them, so adding a
bank later is a config entry plus (if it is a new provider) one class.

```
method (qris, bri_va, mandiri_transfer)  →  provider (xendit, manual)  →  gateway class
```

### Layout
```
/app/Services/Payments
  Contracts/PaymentGatewayInterface.php  - createCharge / verifyWebhook / parseWebhook
  Gateways/XenditGateway.php             - QRIS today, VA wired but disabled
  Gateways/ManualTransferGateway.php     - bank transfer confirmed by a human
  Data/PaymentMethod.php                 - a typed catalog entry
  Data/ChargeResult.php                  - normalised "here is how to pay"
  Data/WebhookEvent.php                  - normalised callback
  PaymentGatewayManager.php              - PaymentProvider enum → gateway
  PaymentMethodCatalog.php               - reads config/payments.php
  PaymentGatewayException.php            - provider rejected / unreachable → HTTP 502
/app/Services/PaymentService.php         - orchestrates orders + gateways
```

### The catalog
`config/payments.php` holds the method list. Only `qris` ships enabled; BRI VA,
Mandiri VA, and Mandiri transfer are present but `'enabled' => false`. Activating
one is a config flip — no code change unless it needs a new provider.

### Adding a provider
1. Add a case to `App\Enums\PaymentProvider`
2. Write a gateway implementing `PaymentGatewayInterface`
3. Map it in `config/payments.php` under `providers`
4. Bind it in `RepositoryServiceProvider` if its constructor takes credentials
5. Add its methods to the `methods` array
6. Add a webhook route + controller if it calls back

### Xendit API choice
The gateway uses the **Payment Requests API** (`POST /v3/payment_requests`), not
the older per-instrument endpoints (`/qr_codes`, `/callback_virtual_accounts`).
Two reasons:

- Every instrument is created the same way (one `payment_method` block) and
  retracted the same way (`POST /v3/payment_methods/{id}/expire`), so QRIS and VA
  share one code path.
- The older QR Codes API has no cancel endpoint, which left an abandoned QR
  scannable until it lapsed. Expiring the payment method closes that window.

This splits provider identity in two, hence two columns: `provider_reference`
holds the payment request (`pr-…`, what webhooks quote) and
`provider_method_reference` holds the payment method (`pm-…`, what expiry needs).

Webhook events are `payment.succeeded` / `payment.failed`. `mapStatus()` keys off
the payload's `status` field rather than the event name, so legacy `qr.payment`
callbacks still resolve identically — there is a test for that.

### Endpoints
```
GET  /api/payment-methods                      public; enabled methods grouped by type
POST /api/orders/{orderNumber}/payments        auth:api; body { method_code }
GET  /api/orders/{orderNumber}/payments        auth:api; poll while the buyer pays
     (re-POST with a different method_code to switch method)
POST /api/webhooks/xendit                      no auth; verified by x-callback-token
```

### Switching payment method
Re-posting to `/payments` with a different `method_code` switches the buyer over.
`PaymentService::supersede()` retires the old charge first, so an order never has
two payable instruments open at once:

1. `voidCharge()` expires the charge's payment method at the provider, which
   retracts the instrument itself — a QR that can no longer be scanned, a VA
   that can no longer be transferred to.
2. The old payment is marked `cancelled` locally regardless. This is the part
   that must not fail, so a provider error never blocks the switch.

Step 1 can still fail (provider down, expire rejected), so the safety net below
stays in place — it is what makes step 2 sufficient on its own:

- Order still unpaid → the payment is honoured, order settled, tickets issued.
  Money is never dropped because we had moved on.
- Order already paid by the new method → the second payment is stored with
  `requires_refund = true` and logged at `critical`. Tickets are not issued
  twice. Query `payments where requires_refund = true` for the refund queue.

### Webhook audit trail
Every callback that passes signature verification is written to
`webhook_deliveries` **before** being dispatched, so a payload that fails
mid-flight survives to be inspected and replayed. Rejected callbacks are logged
but never stored — the endpoint is public, and persisting unverified payloads
would let anyone fill the table.

`App\Enums\WebhookDeliveryStatus`:

| Status | Meaning |
|---|---|
| `received` | Stored, not yet dispatched |
| `ignored` | Parsed to nothing we act on |
| `applied` | Dispatched and it changed something |
| `skipped` | Recognised, nothing left to do (a redelivery) |
| `unmatched` | **Verified, but no payment matches** — money may have moved with nothing to attach it to |
| `failed` | Processing threw; the error is stored and the provider retries |

The two worth alerting on are `unmatched` and `failed` — `needsAttention()`
covers both:

```sql
SELECT * FROM webhook_deliveries WHERE status IN ('unmatched', 'failed');
```

Redeliveries deliberately create separate rows: this is an audit log, not a
deduplication key. Idempotency is enforced in `OrderService::markPaid()` instead.

### Order expiry / quota release
Quota is decremented when an order is created and returned when it expires. But
expiry is **lazy** — `OrderService::expire()` only fires when something touches
the order — so an abandoned checkout would hold its seats forever.

`php artisan orders:expire` sweeps lapsed unpaid orders and puts the quota back.
It is scheduled every five minutes in `routes/console.php`, and the container
runs `schedule:work` under supervisord. **Both halves are required**: without the
supervisord program the schedule never fires.

`expire()` is row-locked, because the sweeper runs alongside live requests that
also expire lapsed orders and a double restore would oversell the event.

If money arrives for an order the sweeper already released, `PaymentService`
stores the payment with `requires_refund = true` and logs at `critical` rather
than issuing tickets against quota that may now be sold.

### Ticket issuance
`OrderService::markPaid()` is the single place that settles an order and mints
tickets. Both the direct `POST /orders/{n}/pay` route and the gateway webhook go
through it. It is idempotent and row-locked, so a redelivered callback cannot
issue a second set of tickets.

### Environment
```
XENDIT_SECRET_KEY, XENDIT_CALLBACK_TOKEN, XENDIT_BASE_URL, XENDIT_TIMEOUT
PAYMENT_EXPIRY_MINUTES (default 30)
MANUAL_BANK_NAME, MANUAL_BANK_ACCOUNT_NUMBER, MANUAL_BANK_ACCOUNT_HOLDER
```

## Adding a New Domain
1. `php artisan make:model MyModel -m` — model + migration
2. Create `app/Repositories/Contracts/MyModelRepositoryInterface.php`
3. Create `app/Repositories/MyModelRepository.php`
4. Create `app/Services/MyModelService.php`
5. Create `app/Http/Controllers/Api/MyModelController.php` (use `ApiResponse` trait)
6. Add Form Request in `app/Http/Requests/MyModel/`
7. Add Resource in `app/Http/Resources/MyModelResource.php`
8. Bind interface in `RepositoryServiceProvider`
9. Register routes in `routes/api.php`
