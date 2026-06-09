# Plesticket Backend

REST API backend for the Plesticket ticketing platform, built with Laravel 13 and PHP 8.3.

## Tech Stack

- **Language:** PHP 8.3
- **Framework:** Laravel 13
- **Database:** PostgreSQL (local) / MySQL (production)
- **Auth:** JWT via `tymon/jwt-auth` v2.3
- **Container:** Docker (nginx + php-fpm + supervisord)

## Requirements

- PHP 8.3 + Composer
- PostgreSQL or MySQL
- Docker & Docker Compose (optional)

## Getting Started

### Local Development

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
php artisan migrate --seed
php artisan serve
```

### With Docker

```bash
# Build and start containers (API at http://localhost:8081)
docker-compose up --build

# Run migrations and seeders
docker exec plesticket-lara-app php artisan migrate --seed
```

## Environment Variables

```env
APP_NAME=Plesticket
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=plesticket
DB_USERNAME=postgres
DB_PASSWORD=

JWT_SECRET=
JWT_TTL=60
```

> For MySQL (production), set `DB_CONNECTION=mysql` and `DB_PORT=3306`.

## Seeders

```bash
php artisan db:seed                           # all seeders
php artisan db:seed --class=SuperAdminSeeder  # superadmin@plesticket.com / adminpass
php artisan db:seed --class=ProvinceSeeder
php artisan db:seed --class=CitySeeder
```

## Roles

| Role | Value | Description |
|------|-------|-------------|
| Super Admin | `SUPER_ADMIN` | Full platform access |
| Event Organizer | `REGISTERED_USER` | Creates events, manages members |
| Buyer | `BUYER` | Purchases tickets |

## API Overview

### Platform Auth (`/api/auth`)

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/api/auth/register` | — | Register as `REGISTERED_USER` |
| POST | `/api/auth/buyer-register` | — | Register as `BUYER` |
| POST | `/api/auth/login` | — | Login, returns JWT |
| GET | `/api/auth/me` | JWT | Get current user |
| POST | `/api/auth/logout` | JWT | Invalidate token |

### Organizer Auth (`/api/organizer-auth`)

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/api/organizer-auth/login` | — | Login with `uid` + `password` |
| GET | `/api/organizer-auth/me` | Organizer JWT | Get current member |
| POST | `/api/organizer-auth/logout` | Organizer JWT | Invalidate token |

### Profile (`/api/profile`)

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/api/profile/photo` | JWT | Upload profile photo |

### Events — Public

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/api/events` | — | List verified events (paginated) |
| GET | `/api/events/{slug}` | — | Get event detail by slug |

### Events — Organizer (REGISTERED_USER)

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/api/events/my` | JWT | List own events |
| POST | `/api/events` | JWT | Create event |
| PUT | `/api/events/{event_id}` | JWT | Update event |
| POST | `/api/events/{event_id}/banner` | JWT | Upload banner |
| PATCH | `/api/events/{event_id}/toggle` | JWT | Toggle active/inactive |

### Events — Super Admin

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/api/admin/events` | JWT + `SUPER_ADMIN` | List all events |
| GET | `/api/admin/events/{event_id}` | JWT + `SUPER_ADMIN` | Get event detail |
| POST | `/api/admin/events/{event_id}/verify` | JWT + `SUPER_ADMIN` | Verify event |
| POST | `/api/admin/events/{event_id}/reject` | JWT + `SUPER_ADMIN` | Reject event |
| POST | `/api/admin/events/{event_id}/suspend` | JWT + `SUPER_ADMIN` | Suspend event |

### Organizer Members

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/api/events/{event_id}/members` | JWT | List members |
| POST | `/api/events/{event_id}/members` | JWT | Add member |
| PUT | `/api/events/{event_id}/members/{id}` | JWT | Update member |
| DELETE | `/api/events/{event_id}/members/{id}` | JWT | Remove member |

### Users — Super Admin

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/api/admin/users` | JWT + `SUPER_ADMIN` | List users |
| PUT | `/api/admin/users/{uid}` | JWT + `SUPER_ADMIN` | Update user |
| DELETE | `/api/admin/users/{uid}` | JWT + `SUPER_ADMIN` | Delete user |

### Locations

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/api/provinces` | — | List provinces |
| GET | `/api/cities` | — | List cities (filter by `province_code`) |

## Response Format

```json
{ "status": "success", "message": "...", "data": {} }
{ "status": "success", "message": "...", "data": [], "meta": { "total": 0, "page": 1, "limit": 10, "pages": 0 } }
{ "status": "error",   "message": "...", "errors": {} }
```

## Project Structure

```
app/
  Enums/                  - UserRole, OrganizerRole, VerificationStatus
  Http/
    Controllers/Api/      - API controllers
    Middleware/           - RoleMiddleware, Authenticate
    Requests/             - Form request validation (per domain/action)
    Resources/            - API resource transformers
  Models/                 - Eloquent models
  Providers/              - AppServiceProvider, RepositoryServiceProvider
  Repositories/
    Contracts/            - Repository interfaces
    *.php                 - Eloquent implementations
  Services/               - Business logic
  Traits/                 - ApiResponse
database/
  migrations/
  seeders/
config/
  cors.php                - CORS allowed origins
docker/                   - nginx.conf, supervisord.conf
routes/
  api.php
```

## Storage (File Uploads)

After deployment, create the public storage symlink:

```bash
php artisan storage:link
```

Uploaded files (banners, photos) are stored in `storage/app/public/` and served at `/storage/`.

## License

Proprietary — All rights reserved.
