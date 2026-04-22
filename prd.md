# Plesticket — Product Requirements Document

## Overview

Plesticket is a ticketing platform backend that enables event organizers to create and manage events, and allows platform admins to verify those events before they go live. Each verified event has its own organizer member accounts for on-ground operations.

---

## User Roles

### Platform Roles (login via `POST /api/auth/login`)

| Role | Description |
|---|---|
| `SUPER_ADMIN` | Full platform access — manages users, verifies/rejects/suspends events |
| `REGISTERED_USER` | Creates and manages their own events; adds organizer members to verified events |

### Organizer Roles (login via `POST /api/organizer-auth/login`)

These accounts are created per event by the event owner. They authenticate using `uid` + `password`.

| Role | Description |
|---|---|
| `EO_STAFF` | Event organization staff |
| `GATE_OFFICER` | Manages entry gates |
| `MITRA_TICKET_BOX` | Ticket box partner |
| `BAND` | Performer access |
| `MEDIA` | Press/media access |
| `SPONSOR` | Sponsor access |

---

## User Flows

### 1. Platform Registration & Login
1. User registers with name, username, email, password, phone, date of birth → account created as `REGISTERED_USER`
2. User logs in → receives JWT
3. User can upload a profile photo via a separate endpoint

### 2. Event Creation & Verification
1. `REGISTERED_USER` creates an event with full details (title, schedule, location, PIC data)
2. Event starts with `verification_status = pending`
3. `SUPER_ADMIN` reviews the event and either:
   - **Verifies** → status becomes `verified`, event becomes eligible for organizer members
   - **Rejects** → status becomes `rejected` with a reason
   - **Suspends** → status becomes `suspended` (for already-verified events)
4. Only `verified` events are visible on the public listing

### 3. Organizer Member Management
1. Owner of a **verified** event adds organizer member accounts (name, email, password, role)
2. Each member gets a UID in format `EVT0001-0001` (event code + sequence)
3. Organizer members log in using their `uid` + `password`
4. Their JWT carries: uid, name, role, event_id

---

## Domain Features

### Authentication
- Platform: JWT via email + password, guards: `auth:api`
- Organizer: JWT via uid + password, guards: `auth:organizer`
- Token TTL configured via `JWT_TTL` env variable

### User Management (SUPER_ADMIN only)
- List users with filters: role, is_active, search
- View, update, delete any user

### Profile
- Authenticated user can upload a profile photo (jpg/jpeg/png/webp, max 2MB)
- Old photo is deleted from storage when a new one is uploaded

### Events
**Public endpoints (no auth):**
- List verified events — filterable by search, category, city, is_online
- Get event detail by slug

**Owner endpoints (REGISTERED_USER):**
- List own events
- Create event
- Update event (only while status is `pending` or `rejected`)
- Delete event

**Admin endpoints (SUPER_ADMIN):**
- List all events — filterable by verification_status, search
- Get any event (sees full PIC data including identity number and NPWP)
- Verify / Reject (with reason) / Suspend event

**Event data:**
- Basic info: title, slug (auto-generated from title if not provided), description, category, banner URL
- PIC (Person In Charge): name, identity type (KTP/SIM/PASSPORT), identity number (encrypted), NPWP (encrypted)
- Schedule: start date, end date, start time, end time
- Location: is_online, venue name, address, city, province
- Visibility: show_status, is_published
- Human-readable event ID: `EVT0001`

**Response visibility rules:**
- PIC block only shown to admin or event owner
- Identity number and NPWP only shown to SUPER_ADMIN
- `verified_by` only shown to SUPER_ADMIN

### Organizer Members
- Scoped per event (not per user)
- Only the event owner can manage members for their own **verified** events
- List, add, update, remove members
- Member UID format: `{event_code}-{sequence:04d}` e.g. `EVT0001-0001`
- Email uniqueness enforced per event

### Master Data
- **Provinces** — public listing, searchable
- **Cities** — public listing, filterable by province code
- **Banks** — public listing (15 Indonesian banks seeded)

---

## Data Model Summary

| Table | Key Fields |
|---|---|
| `users` | uid (U000001), name, username, email, phone, date_of_birth, photo, password, role, is_active |
| `events` | id (UUID), event_id (EVT0001), user_id FK, title, slug, pic fields (encrypted), schedule, location, verification_status, verified_by FK, soft deletes |
| `organizer_members` | id, uid (EVT0001-0001), owner_id FK, event_id FK (UUID), name, email, password, role, is_active |
| `banks` | id, code, name, is_active |
| `provinces` | id, code, name |
| `cities` | id, province_code FK, name, type (KABUPATEN\|KOTA) |

---

## API Base URL

```
http://localhost:8081/api
```

---

## Seeded Accounts

| Account | Email | Password | Role |
|---|---|---|---|
| Super Admin | superadmin@plesticket.com | adminpass | SUPER_ADMIN |

---

## Tech Stack

- PHP 8.3 / Laravel 13
- PostgreSQL
- JWT (`tymon/jwt-auth` v2.3)
- Docker (nginx + php-fpm + supervisor)
