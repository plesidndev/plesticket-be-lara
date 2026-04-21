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
  /Enums            - PHP 8.1 backed enums (UserRole)
  /Http
    /Controllers/Api - API controllers (no web controllers)
    /Middleware      - RoleMiddleware
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
  app.php            - Application bootstrap (routes, middleware aliases registered here)
  providers.php      - Service providers list
/database
  /migrations        - Laravel migration files
  /seeders           - Province + City seeders
/docker              - nginx.conf, supervisord.conf
/routes
  api.php            - All API routes (no routes/web.php used for API)
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

### Platform roles (PHP enum `App\Enums\UserRole`)
```php
UserRole::SuperAdmin      // 'SUPER_ADMIN'
UserRole::EventOrganizer  // 'EVENT_ORGANIZER'
UserRole::RegisteredUser  // 'REGISTERED_USER'
```

Organizer-scoped roles (`EO_STAFF`, `GATE_OFFICER`, `MITRA_TICKET_BOX`, `BAND`, `MEDIA`, `SPONSOR`) — reserved in JWT, implemented later via `organizer_members` table.

### JWT Claims (`getJWTCustomClaims` on User model)
`uid`, `name`, `email`, `role`

### Token flow
- `POST /api/auth/register` → creates `REGISTERED_USER`, returns `{ token, user }`
- `POST /api/auth/login`    → returns `{ token, user }`
- `GET  /api/auth/me`       → requires `auth:api`
- `POST /api/auth/logout`   → requires `auth:api`

### Protecting routes
```php
Route::middleware('auth:api')->group(fn() => ...);
Route::middleware(['auth:api', 'role:SUPER_ADMIN'])->group(fn() => ...);
Route::middleware(['auth:api', 'role:SUPER_ADMIN,EVENT_ORGANIZER'])->group(fn() => ...);
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

Latest migration: `2026_04_21_000002_create_cities_table`

## Seeders
```bash
php artisan db:seed              # runs ProvinceSeeder + CitySeeder
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