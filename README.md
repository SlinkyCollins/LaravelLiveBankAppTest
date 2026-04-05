# Vaultly Backend API (Laravel 12)

Laravel API powering the Vaultly banking demo. It provides token-authenticated endpoints for account access, wallet operations, transaction history, beneficiaries, security settings (PIN/password), and Cloudinary-backed profile pictures.

![Laravel](https://img.shields.io/badge/Laravel-12.x-ff2d20)
![PHP](https://img.shields.io/badge/PHP-8.2-777bb4)
![Sanctum](https://img.shields.io/badge/Auth-Sanctum-0ea5e9)
![Status](https://img.shields.io/badge/Deployment-Live-success)

## Table of Contents

- [Why This Project](#why-this-project)
- [Live Deployment](#live-deployment)
- [Tech Stack](#tech-stack)
- [Screenshots and GIFs](#screenshots-and-gifs)
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

This backend focuses on realistic banking workflows with safe defaults:

- Laravel Sanctum Bearer-token auth
- transactional balance updates (deposit, transfer, withdraw)
- recipient verification and beneficiary management
- transaction PIN setup/change and password change
- profile data updates and Cloudinary image lifecycle (upload/replace/delete)

## Live Deployment

- API Base URL: https://laravellivebankapptest.onrender.com
- API Prefix: `/api`
- Current frontend origin (CORS default): https://vaultlydemo.vercel.app

## Tech Stack

- Laravel 12
- PHP 8.2
- MySQL (Aiven in production)
- Laravel Sanctum
- Cloudinary Laravel SDK
- Docker (Render deployment target)

## Screenshots and GIFs

Suggested backend demo captures for docs and portfolio walkthrough:

![Login and Token Response](docs/media/backend/login-token.png)
![Deposit Request and Response](docs/media/backend/deposit.png)
![Transfer with PIN](docs/media/backend/transfer-pin.gif)
![Transactions History Pagination](docs/media/backend/transactions.png)
![Profile Picture Upload (Cloudinary)](docs/media/backend/profile-upload.gif)

Recommended short recordings:

- login -> authenticated request sequence
- transfer flow showing PIN validation and updated balance
- Cloudinary upload then replace/remove profile picture

## Project Structure

```text
laravelTest/
	app/
		Http/Controllers/
			UserController.php
			TransactionController.php
			BeneficiaryController.php
		Models/
			User.php
			Transaction.php
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

### Auth Model

- `POST /api/register` creates user with generated 12-digit account number.
- `POST /api/login` returns Sanctum access token (`Bearer`).
- Protected routes use `auth:sanctum` middleware.

### Money Movement and Data Integrity

- `TransactionController` wraps financial mutations in database transactions.
- Transfer locks sender/recipient rows in stable order to reduce deadlock risk.
- Transaction history stores before/after balances and transfer direction (`credit`/`debit`).

### Beneficiaries

- CRUD endpoints scoped to authenticated user.
- duplicate protection by account + bank code.
- self-account as beneficiary is blocked.

### Profile and Media

- Profile metadata update via `PUT /api/profile`.
- Profile picture upload/delete uses Cloudinary only.
- backend stores URL plus `profile_picture_public_id` for deterministic replace/delete.

### CORS and Frontend Integration

- `config/cors.php` reads comma-separated `CORS_ALLOWED_ORIGINS`.
- defaults include deployed frontend and local Vite origins.

## Prerequisites

- PHP 8.2+
- Composer 2+
- MySQL 8+ (or compatible)
- Node.js 18+ (only for local Vite assets/dev convenience)

## Quick Start

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

API becomes available at `http://127.0.0.1:8000/api`.

## Environment Variables

At minimum, configure:

```bash
APP_NAME=Vaultly
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=vaultly_db
DB_USERNAME=your_user
DB_PASSWORD=your_password

CORS_ALLOWED_ORIGINS=http://localhost:5173,http://127.0.0.1:5173

CLOUDINARY_URL=
CLOUDINARY_CLOUD_NAME=
CLOUDINARY_KEY=
CLOUDINARY_SECRET=
CLOUDINARY_SECURE=true
```

Notes:

- Cloudinary config supports either `CLOUDINARY_URL` or split key/secret/cloud name values.
- For production, set `APP_DEBUG=false` and secure all secrets.

## Commands

```bash
php artisan serve                 # local API server
php artisan migrate               # run migrations
php artisan migrate:fresh --seed  # rebuild local db
php artisan config:clear          # clear config cache
php artisan route:list            # inspect routes
php artisan test                  # run tests
```

Optional full local dev command from `composer.json`:

```bash
composer run dev
```

## Core API Endpoints

Auth and profile:

- `POST /api/register`
- `POST /api/login`
- `POST /api/logout` (auth)
- `GET /api/dashboard` (auth)
- `GET /api/profile` (auth)
- `PUT /api/profile` (auth)
- `POST /api/profile/picture` (auth, multipart)
- `DELETE /api/profile/picture` (auth)

Wallet and transactions:

- `GET /api/balance` (auth)
- `POST /api/deposit` (auth)
- `POST /api/verify-account` (auth)
- `POST /api/transfer` (auth)
- `POST /api/withdraw` (auth)
- `GET /api/transactions` (auth)

Security settings:

- `POST /api/set-pin` (auth)
- `PUT /api/change-pin` (auth)
- `PUT /api/change-password` (auth)

Beneficiaries:

- `GET /api/beneficiaries` (auth)
- `POST /api/beneficiaries` (auth)
- `PUT /api/beneficiaries/{id}` (auth)
- `DELETE /api/beneficiaries/{id}` (auth)

## Testing

PHPUnit is configured with separate Unit and Feature suites.

```bash
php artisan test
```

Current source include target is `app/`.

## Deployment Notes

- Deployed via Docker on Render using included `Dockerfile`.
- Container startup runs migrations automatically.
- Ensure Render env vars include database credentials, app key, and Cloudinary credentials.
- Set `CORS_ALLOWED_ORIGINS` to your deployed frontend domain(s).

## Known Constraints

- Financial endpoints enforce amount bounds (`min:100`, `max:10000000`).
- Transfer and PIN operations require a 4-digit numeric PIN.
- Account numbers are internal 12-digit values.
- Free-tier hosting/databases may introduce cold starts or lower throughput.
- No background queue workers are required for current feature set.

## FAQ and Troubleshooting

### API returns 401 for protected routes

- Confirm request has `Authorization: Bearer <token>` header.
- Ensure token belongs to active user and has not been revoked.

### Cloudinary upload fails

- Verify Cloudinary env vars are correct.
- Confirm image is `jpg/jpeg/png/webp` and <= 2MB.
- Check application logs for upload exception details.

### CORS blocked in browser

- Add frontend origin to `CORS_ALLOWED_ORIGINS`.
- Clear config cache after env changes.

## License

This project is for educational and portfolio/demo purposes.
