# QA Plan: EO Event Creation → Audience Ticket Purchase

## Prerequisites
- App running at `http://localhost:8000` (php artisan serve + vite dev)
- Super Admin account: `superadmin@plesticket.com` / `adminpass`
- Fresh test EO account (register during testing)
- Fresh test audience account (register during testing)

---

## Flow 1: EO Registration & Login

| # | Step | Expected Result | Status |
|---|---|---|---|
| 1.1 | Go to `/admin/register` | Registration form shown (name, username, email, password, phone, dob) | ✅ PASS |
| 1.2 | Submit with all required fields filled | Redirected to `/events`, JWT stored | ✅ PASS |
| 1.3 | Submit with missing name | Validation error / stays on register page | ✅ PASS |
| 1.4 | Submit with invalid email format | Blocked by HTML5 or server validation | ✅ PASS |
| 1.5 | Submit with password < 8 chars | Validation error shown | ✅ PASS |
| 1.6 | Submit with duplicate email | API error message shown | ✅ PASS |
| 1.7 | Go to `/admin/login`, log in with registered credentials | Redirected to `/admin/events` | ✅ PASS |
| 1.8 | Go to `/admin/login`, submit wrong password | Error message shown | ✅ PASS |

---

## Flow 2: EO Creates Event

| # | Step | Expected Result | Status |
|---|---|---|---|
| 2.1 | While logged in as EO, navigate to `/admin/events` | My Events list shown | ✅ PASS |
| 2.2 | Click "Create Event" → `/admin/events/create` | Event form loads with all sections (Basic Info, PIC, Schedule, Location, Ticket Types, Visibility) | ✅ PASS |
| 2.3 | Submit empty form | HTML5 required fields block submit, stays on page | ✅ PASS |
| 2.4 | Fill Title only, submit | Remaining required field errors shown | — (not automated) |
| 2.5 | Fill all required fields, no ticket types, submit | Event created → redirect to `/admin/events` | ✅ PASS |
| 2.6 | Fill all required fields + 1 ticket type (Regular, Rp50.000, quota 100), submit | Event created with ticket type | ✅ PASS |
| 2.7 | Add ticket type with price = 0 | Ticket shows as "Free" in event detail | — (not automated) |
| 2.8 | Add ticket with sale_start in the future | Ticket visible on detail page but shows "Opens [date]" | — (not automated) |
| 2.9 | Upload banner image (JPG/PNG/WebP) | Preview shown before submit; banner appears on event detail after | — (not automated) |
| 2.10 | Upload banner > 5MB | Error message shown, submit blocked | — (not automated) |
| 2.11 | Select province → city dropdown populates | City list filtered by selected province | ✅ PASS |
| 2.12 | Check "Online event" checkbox | Venue/address/city/map fields hidden | ✅ PASS |
| 2.13 | Leave slug blank | Slug auto-generated from title by backend | — (not automated) |
| 2.14 | Enter custom slug | Custom slug used in event URL | — (not automated) |
| 2.15 | Submit with end_date before start_date | Validation error shown | — (not automated) |

### Bug fixed — 2.5 & 2.6
`is_online` and `show_status` booleans were sent as strings `"false"` / `"true"` via FormData. Backend rejected them with 422. Fixed in `resources/js/pages/user/EventForm.tsx` `buildFormData()` — booleans now sent as `"1"` / `"0"`.

---

## Flow 3: Super Admin Verifies Event

| # | Step | Expected Result | Status |
|---|---|---|---|
| 3.1 | Login as Super Admin → `/plest-admin/login` | Redirected to `/plest-admin/events` | ✅ PASS |
| 3.2 | Find the EO's event in the list (status: `pending`) | Event appears with "pending" badge | ✅ PASS |
| 3.3 | Click "View" → event detail page | Full event info shown including PIC data | ✅ PASS |
| 3.4 | Click "Verify" | Status changes to `verified`, Verify button replaced by Suspend | ✅ PASS |
| 3.5 | Alternatively click "Reject" with a reason | Status changes to `rejected`, reason stored and shown | ✅ PASS |
| 3.6 | Click "Suspend" on a verified event | Status changes to `suspended` | ✅ PASS |

---

## Flow 4: EO Edits Event (pending/rejected only)

| # | Step | Expected Result | Status |
|---|---|---|---|
| 4.1 | As EO, click edit on a `rejected` event | Edit form loads pre-filled with existing title | ✅ PASS |
| 4.2 | Change title and save | Title updated, redirect to events list, new title visible | ✅ PASS |
| 4.3 | Edit PIC identity number field | Placeholder says "Leave blank to keep current" | ✅ PASS |
| 4.4 | Try to edit a `suspended`/`verified` event | No "Edit Event" button on detail page | ✅ PASS |

---

## Flow 5: Audience Registration & Browsing

| # | Step | Expected Result | Status |
|---|---|---|---|
| 5.1 | Go to `/register` | Buyer register form shown | ✅ PASS |
| 5.2 | Register with valid data | Redirected to `/home` | ✅ PASS |
| 5.3 | Home page loads | Recommended + Nearest sections visible, verified event shown | ✅ PASS |
| 5.4 | Click "See all →" → `/events` | Event list with search + category chips | ✅ PASS |
| 5.5 | Search by event title | Results filtered correctly | ✅ PASS |
| 5.6 | Filter by Music category chip | Chip becomes active, filtered results shown | ✅ PASS |
| 5.7 | Click the event card → `/events/:slug` | Detail page: title, date 📅, location 📍 shown | ✅ PASS |
| 5.8 | Ticket card shows name, price, quota | Regular / Rp75.000 / 200 left | ✅ PASS |
| 5.9 | Click a ticket card | "✓ Selected" badge + bottom bar price updates | ✅ PASS |
| 5.10 | Click "Buy Now" | **BLOCKED — purchase flow not yet built** | ⛔ BLOCKED |

---

## Flow 6: Edge Cases

| # | Step | Expected Result | Status |
|---|---|---|---|
| 6.1 | Access `/admin/events` while not logged in | Redirect to `/admin/login` | ✅ PASS |
| 6.2 | Access `/plest-admin/events` as EO | Redirected to `/admin/events` | ✅ PASS |
| 6.3 | Navigate directly to `/events/:invalid-slug` | Redirect to `/events` | ✅ PASS |
| 6.4 | Pending event not visible in public `/events` listing | Correctly hidden | ✅ PASS |
| 6.5 | Refresh page while logged in | Auth persisted, stays on same page | ✅ PASS |

---

> **Known gap:** Flow 5.10 — ticket purchase flow (checkout, payment, order confirmation) not yet built.

---

## Bugs Found & Fixed

| # | File | Bug | Fix |
|---|---|---|---|
| B1 | `resources/js/pages/user/EventForm.tsx` | `is_online` / `show_status` booleans sent as `"false"`/`"true"` strings via FormData → API 422 | Booleans now sent as `"0"`/`"1"` |
| B2 | `resources/js/pages/EventList.tsx` | Category chip filter sent lowercase (`?category=music`) but API does case-sensitive match on stored `"Music"` → 0 results | Store original-case value in URL param; active check now case-insensitive |
| B3 | `resources/js/pages/Home.tsx` | Same as B2 — category click on home page also lowercased the value | Removed `.toLowerCase()` from navigation URL |
