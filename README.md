# Vaultly Backend API (Laravel 12)

Laravel API powering the Vaultly banking demo. Provides token-authenticated endpoints for
account management, wallet operations, transaction history, beneficiary management, security
settings, and Cloudinary-backed profile pictures.

![Laravel](https://img.shields.io/badge/Laravel-12.x-ff2d20)
![PHP](https://img.shields.io/badge/PHP-8.2-777bb4)
![Sanctum](https://img.shields.io/badge/Auth-Sanctum-0ea5e9)
![Status](https://img.shields.io/badge/Deployment-Live-success)

## Table of Contents

- [Why This Project](#why-this-project)
- [Live Deployment](#live-deployment)
- [Tech Stack](#tech-stack)
- [Project Structure](#project-structure)
- [Architecture Deep Dive](#architecture-deep-dive)
- [Prerequisites](#prerequisites)
- [Quick Start](#quick-start)
- [Environment Variables](#environment-variables)
- [Commands](#commands)
- [Core API Endpoints](#core-api-endpoints)
- [Testing](#testing)
- [Deployment Notes](#deployment-notes)
- [Known Constraints](#known-constraints)
- [FAQ and Troubleshooting](#faq-and-troubleshooting)
- [License](#license)

## Why This Project

Vaultly is a full-stack banking demo built to demonstrate real-world patterns — not just CRUD.

All money movement (deposit, transfer, withdraw) runs inside database transactions with
row-level locking. The transfer controller locks sender and recipient rows in sorted ID order
before any balance mutation to reduce deadlock risk, and retries the transaction up to five
times on transient failures. Balance checks happen on the locked row — not on the pre-lock
snapshot — so concurrent requests cannot overdraw an account.

Each transaction record stores sender name, sender account number, recipient name, recipient
account number, balance before, and balance after. Every record also carries a ULID-based
`transaction_reference` generated automatically at create time and used as the public-facing
identifier in the history response — internal database IDs are never exposed to the client.

Auth is handled via Laravel Sanctum bearer tokens. Profile media is managed through Cloudinary
with `profile_picture_public_id` tracking so pictures can be replaced or deleted without
orphaning assets. The frontend is a single-page Vue 3 app with Pinia, global 401 handling,
and route ownership guards — see the [frontend repo](https://github.com/SlinkyCollins/vaultly-frontend)
for details.

## Live Deployment

- API base URL: `https://laravellivebankapptest.onrender.com`
- API prefix: `/api`
- CORS default in `config/cors.php`: `https://vaultlydemo.vercel.app`

> The API is hosted on a free-tier Render service. The first request after idle may be slow
> due to a cold start. Subsequent requests are fast.

## Tech Stack

- Laravel 12
- PHP 8.2
- MySQL (Aiven in production)
- Laravel Sanctum
- Cloudinary Laravel SDK
- Docker (Render deployment target)

## Project Structure
```text
app/
  Http/Controllers/
    UserController.php          # auth, profile, PIN, password, balance
    TransactionController.php   # deposit, transfer, withdraw, history, verify-account
    BeneficiaryController.php   # beneficiary CRUD
  Models/
    User.php
    Transaction.php             # ULID transaction_reference generated on create
    Beneficiary.php
routes/
  api.php
database/
  migrations/
  factories/
  seeders/
config/
  cors.php
  cloudinary.php
tests/
  Feature/
  Unit/
Dockerfile
```

## Architecture Deep Dive

### Auth

- `POST /api/register` creates a user and generates a unique 12-digit account number.
- `POST /api/login` returns a Sanctum bearer token.
- All protected routes use `auth:sanctum` middleware.
- `POST /api/logout` deletes the current access token via `currentAccessToken()->delete()`.

### Money Movement and Data Integrity

All three financial operations (deposit, transfer, withdraw) use the same pattern:

1. Validate input before opening a transaction.
2. Open a `DB::transaction()` with up to 5 retry attempts (`TX_RETRY_ATTEMPTS = 5`).
3. Lock the target user row(s) with `lockForUpdate()`. For transfer, both sender and
   recipient are locked in ascending ID order to prevent deadlocks from concurrent
   transfers between the same two accounts.
4. Check balance against the locked row value — not the pre-lock snapshot.
5. Apply `increment()`/`decrement()` to update balance atomically.
6. Create transaction record(s) with full sender/recipient details, `balance_before`,
   `balance_after`, `direction` (`credit`/`debit`), and an auto-generated ULID reference.

Transfer also accepts an optional `save_beneficiary: true` flag. If set, the recipient is
saved as a beneficiary via `firstOrCreate` inside the same transaction — so the save either
succeeds with the transfer or is rolled back with it.

### Beneficiaries

- All four CRUD endpoints are scoped to the authenticated user (`where('user_id', ...)`).
- Duplicate protection is enforced by `account_number + bank_code` uniqueness, both at the
  application layer and via a unique index migration.
- Saving your own account as a beneficiary is blocked.
- The update endpoint resolves the new `account_name` from the live `account_number` lookup,
  so stored names stay accurate.

### Profile and Media

- `PUT /api/profile` updates `name`, `account_type`, `next_of_kin_name`, and
  `next_of_kin_phone`.
- `POST /api/profile/picture` deletes the existing Cloudinary asset (if any) before
  uploading the new one, then stores both `profile_picture` (secure URL) and
  `profile_picture_public_id` for future operations.
- `DELETE /api/profile/picture` calls the Cloudinary destroy API, then nulls both fields.
- `transaction_pin` and `password` are excluded from all user payload responses via
  `$hidden` on the User model.

### CORS and Frontend Integration

- `config/cors.php` reads a comma-separated `CORS_ALLOWED_ORIGINS` env variable.
- Defaults in the config cover the deployed Vercel frontend and common local Vite dev origins.
- After updating `CORS_ALLOWED_ORIGINS`, run `php artisan config:clear` for the change to
  take effect.

## Prerequisites

- PHP 8.2+
- Composer 2+
- MySQL 8+ (or compatible)
- Node.js 18+ (only needed to run `composer run dev` locally)

## Quick Start
```bash
composer install
cp .env.example .env
php artisan key:generate
# configure DB_* and CLOUDINARY_* in .env before this step
php artisan migrate
php artisan serve
```

The API is available at `http://127.0.0.1:8000/api`.

## Environment Variables
```bash
APP_NAME=Vaultly
APP_ENV=local
APP_KEY=                        # generated by php artisan key:generate
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=vaultly_db
DB_USERNAME=your_user
DB_PASSWORD=your_password

CORS_ALLOWED_ORIGINS=http://localhost:5173,http://127.0.0.1:5173

CLOUDINARY_URL=                 # or use the split keys below
CLOUDINARY_CLOUD_NAME=
CLOUDINARY_KEY=
CLOUDINARY_SECRET=
CLOUDINARY_SECURE=true

QUEUE_CONNECTION=sync           # required when running composer run dev
```

Cloudinary accepts either `CLOUDINARY_URL` (DSN format) or split key/cloud name values —
both are handled by the SDK. For production, set `APP_DEBUG=false` and ensure all secrets
are set as environment variables, not committed to `.env`.

## Commands
```bash
php artisan serve                 # start local API server
php artisan migrate               # run pending migrations
php artisan migrate:fresh --seed  # drop and rebuild the local database
php artisan config:clear          # clear config cache (required after .env changes)
php artisan route:list            # list all registered routes
php artisan test                  # run PHPUnit test suite
```

`composer run dev` starts four processes concurrently via `concurrently`:
- `php artisan serve` — local API server
- `php artisan queue:listen` — queue worker (set `QUEUE_CONNECTION=sync` if not needed)
- `php artisan pail` — log tailing
- `npm run dev` — Vite for any local asset builds

## Core API Endpoints

### Auth and Profile

| Method | Endpoint | Auth | Notes |
|--------|----------|------|-------|
| POST | `/api/register` | — | Body: `fullname`, `email`, `password`, `password_confirmation`, `accountType` (`savings\|current\|fixed`) |
| POST | `/api/login` | — | Body: `email`, `password`. Returns bearer token. |
| POST | `/api/logout` | ✓ | Deletes current access token. |
| GET | `/api/dashboard` | ✓ | Returns user payload. |
| GET | `/api/profile` | ✓ | Same user payload. |
| PUT | `/api/profile` | ✓ | Body: `name`, `account_type`, `next_of_kin_name`, `next_of_kin_phone` (all optional). |
| POST | `/api/profile/picture` | ✓ | Multipart. `profile_picture`: jpg/jpeg/png/webp, max 2MB. Replaces existing. |
| DELETE | `/api/profile/picture` | ✓ | Removes from Cloudinary and clears stored fields. |

### Wallet and Transactions

| Method | Endpoint | Auth | Notes |
|--------|----------|------|-------|
| GET | `/api/balance` | ✓ | Returns current balance. |
| POST | `/api/deposit` | ✓ | Body: `amount` (100–10,000,000). |
| POST | `/api/verify-account` | ✓ | Body: `account_number` (12 digits). Returns account holder name. Use before transfer. |
| POST | `/api/transfer` | ✓ | Body: `amount`, `pin`, `account_number` or `beneficiary_id`, optional `save_beneficiary` (boolean). |
| POST | `/api/withdraw` | ✓ | Body: `amount` (100–10,000,000), `pin`. |
| GET | `/api/transactions` | ✓ | Paginated (20/page), ordered newest first. Returns `transaction_reference` (ULID), direction, amount, sender/recipient details, balances. |

### Security Settings

| Method | Endpoint | Auth | Notes |
|--------|----------|------|-------|
| POST | `/api/set-pin` | ✓ | Body: `pin`, `pin_confirmation`. 4 digits. Blocked if PIN already set. |
| PUT | `/api/change-pin` | ✓ | Body: `current_pin`, `new_pin`, `new_pin_confirmation`. |
| PUT | `/api/change-password` | ✓ | Body: `current_password`, `new_password`, `new_password_confirmation`. New password must be 8+ characters and contain letters, numbers, and special characters (`@$!%*?&`). |

### Beneficiaries

| Method | Endpoint | Auth | Notes |
|--------|----------|------|-------|
| GET | `/api/beneficiaries` | ✓ | Returns all saved beneficiaries for the authenticated user. |
| POST | `/api/beneficiaries` | ✓ | Body: `account_number` (12 digits). Resolves name from account lookup. |
| PUT | `/api/beneficiaries/{id}` | ✓ | Body: `account_number`. Re-resolves name. Rejects own account and duplicates. |
| DELETE | `/api/beneficiaries/{id}` | ✓ | Scoped to authenticated user. |

## Testing

PHPUnit is configured with Unit and Feature suites targeting `app/`. The test suite currently
contains the Laravel scaffold placeholder tests only — no domain-specific test coverage exists
yet. Run with:
```bash
php artisan test
```

Writing feature tests for the transaction and auth flows is a tracked [future improvement](#future-improvements).

## Deployment Notes

The repository includes a `Dockerfile` using `php:8.2-cli-alpine`. It installs PHP extensions
(`pdo_mysql`, `mbstring`, `bcmath`, `intl`), runs `composer install --no-dev`, and on
container startup clears caches, links storage, runs migrations, and serves via
`php artisan serve` on port 10000.

Note: `php artisan serve` is a single-threaded PHP development server. The current setup is
functional for a hosted demo on Render but is not configured for production-grade traffic.

Required environment variables on Render: `APP_KEY`, `DB_*`, `CLOUDINARY_*`,
`CORS_ALLOWED_ORIGINS`.

## Known Constraints

- Financial endpoints enforce amount bounds of ₦100 minimum and ₦10,000,000 maximum.
- Transfer and withdraw both require a 4-digit numeric PIN. Users without a PIN receive a 403
  before any balance logic runs.
- Account numbers are internal 12-digit values generated at registration.
- Free-tier hosting and database services may introduce cold starts or connection limits.
- No background queue workers are required for the current feature set.

## FAQ and Troubleshooting

### API returns 401 for protected routes

Confirm the request includes `Authorization: Bearer <token>`. If the token was revoked (e.g.
via logout), log in again to get a new one.

### Transfer or withdraw returns 403 immediately

The authenticated user has not set a transaction PIN. Call `POST /api/set-pin` first.

### Cloudinary upload returns 500

Check that `CLOUDINARY_CLOUD_NAME`, `CLOUDINARY_KEY`, and `CLOUDINARY_SECRET` (or
`CLOUDINARY_URL`) are correctly set. Confirm the image is `jpg/jpeg/png/webp` and under 2MB.
Detailed exception info is written to `storage/logs/laravel.log`.

### CORS blocked in browser

Add the frontend origin to `CORS_ALLOWED_ORIGINS`, then run `php artisan config:clear`.

### Password change returns 422 with regex error

The new password must be at least 8 characters and contain at least one letter, one number,
and one special character (`@`, `$`, `!`, `%`, `*`, `?`, or `&`).

## License

MIT (declared in `composer.json`). This project is for educational and portfolio/demo
purposes.

## Future Improvements

- Add feature tests covering transaction flows, auth, and beneficiary CRUD.
- Replace `php artisan serve` with a production-capable server (Octane or nginx + php-fpm)
  for better concurrency.
- Add pagination parameters to the transactions endpoint (current page size is fixed at 20).
- Add account activity summary endpoint for dashboard analytics.