# Plesticket Backend

REST API backend for the Plesticket ticketing platform, built with Laravel 13 and PHP 8.3.

## Tech Stack

- **Language:** PHP 8.3
- **Framework:** Laravel 13
- **Database:** PostgreSQL
- **Auth:** JWT via `tymon/jwt-auth` v2.3
- **Container:** Docker (nginx + php-fpm + supervisord)

## Requirements

- Docker & Docker Compose
- PHP 8.3 + Composer (for local development without Docker)

## Getting Started

### With Docker

```bash
# Build and start containers (API available at http://localhost:8081)
docker-compose up --build

# Run migrations and seeders
docker exec plesticket-lara-app php artisan migrate --seed
```

### Local Development

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
php artisan migrate --seed
php artisan serve
```

## Environment Variables

```env
APP_NAME=Plesticket
APP_ENV=local
APP_KEY=
APP_DEBUG=true

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=plesticket
DB_USERNAME=postgres
DB_PASSWORD=

JWT_SECRET=
JWT_TTL=60
```

## API Overview

### Auth

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/api/auth/register` | — | Register as `REGISTERED_USER` |
| POST | `/api/auth/login` | — | Login, returns JWT |
| GET | `/api/auth/me` | JWT | Get current user |
| POST | `/api/auth/logout` | JWT | Invalidate token |

### Roles

| Role | Value |
|------|-------|
| Super Admin | `SUPER_ADMIN` |
| Event Organizer | `EVENT_ORGANIZER` |
| Registered User | `REGISTERED_USER` |

## Response Format

```json
{ "status": "success", "message": "...", "data": {} }
{ "status": "success", "message": "...", "data": [], "meta": { "total": 0, "page": 1, "limit": 10, "pages": 0 } }
{ "status": "error",   "message": "...", "errors": {} }
```

## Project Structure

```
app/
  Enums/                  - PHP backed enums (UserRole)
  Http/
    Controllers/Api/      - API controllers
    Middleware/           - RoleMiddleware
    Requests/             - Form request validation
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
  seeders/                - Province + City seeders
docker/                   - nginx.conf, supervisord.conf
routes/
  api.php
```

## License

Proprietary — All rights reserved.