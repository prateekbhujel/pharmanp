# PharmaNP

PharmaNP is a Laravel + React pharmacy ERP/POS application for Nepal-focused pharmacy and distributor operations.

## Stack

- Laravel 12
- React 19, Vite, Tailwind CSS
- Ant Design
- MySQL/MariaDB for local, shared-hosting, demo, and production installs
- JWT bearer auth for the Laravel-served SPA, frontend-only shell, Swagger, and future mobile clients

## Local Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build
php artisan serve
```

Open `/setup` first, create company/store/admin, then sign in. URLs do not need `/public`; the root entrypoint and `.htaccess` forward requests to Laravel safely.

For a one-command local install after cloning into `htdocs/pharmanp`:

```bash
bash scripts/install-local.sh
```

The setup wizard asks for the first admin account during installation. Do not ship a fixed admin password with a production handover.

## Frontend Development

Same-repo Laravel/Vite development:

```bash
npm install
npm run dev
```

Frontend-only development is supported through the `frontend/` Vite shell. A React developer does not need PHP, Composer, XAMPP, or a local Laravel server when they point the app at a shared/backend API URL.

```bash
git clone --filter=blob:none --sparse https://github.com/prateekbhujel/pharmanp.git pharmanp-frontend
cd pharmanp-frontend
git sparse-checkout set --skip-checks frontend resources/js resources/css package.json package-lock.json
npm install
cp frontend/.env.example frontend/.env
npm run frontend:dev
```

Set `VITE_PHARMANP_API_BASE_URL` in `frontend/.env` to the backend API host, for example `https://pharmanp.pratikbhujel.com.np` for the deployed demo. The Laravel-served app keeps `VITE_PHARMANP_API_BASE_URL` empty so API calls stay same-origin, but API authentication is still JWT bearer auth. Production still uses `npm run build`; no Node server is required on shared hosting.

For frontend-only production-build testing, run:

```bash
npm run frontend:build
npm run frontend:preview
```

Do not open `public/frontend-build/index.html` directly. Previewing through Vite avoids asset-path and browser-history reload issues.

## Product Position

PharmaNP is shipped as a standalone Laravel application. A pharmacy installs it, completes the first-run setup, configures branding/fiscal year/roles, and begins daily operation from the same codebase. It uses one database with tenant, company, branch and store scoped columns where the workflow needs them, which keeps shared-hosting deployment simple while still protecting data boundaries inside the app.

For realistic demos and load testing:

```bash
php artisan pharmanp:demo-load --profile=tiny --yes
php artisan pharmanp:demo-load --profile=showcase --yes
```

The loader writes chunked multi-tenant pharmacy data and is meant for demo/performance databases, not customer production databases.

For local XAMPP MySQL development, create a database such as `pharmanp_local`, set `.env` to `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_DATABASE=pharmanp_local`, `DB_USERNAME=root`, then run migrations and seeders.

Swagger/API testing:

```bash
php artisan pharmanp:jwt-token pratik@admin.com --days=1
```

Open `/api-docs` and use the generated JWT in the Authorize dialog as a bearer token.

## Module Development

Backend modules follow a provider-driven modular Laravel pattern:

```text
app/Modules/<Module>/
  DTOs/
  Http/Controllers/
  Http/Requests/
  Http/Resources/
  Models/
  Providers/
  Repositories/Interfaces/
  Repositories/
  Routes/
  Services/
```

Create a new module skeleton with:

```bash
php artisan module:make Targets
```

Controllers stay thin, services own business transactions, repositories hide reusable query details, and module providers bind repository interfaces and load module routes.

Use the architecture doctor before pushing module work:

```bash
php artisan pharmanp:module-doctor
```

The command fails when a configured module is missing its provider, route boundary, repository interface, or service-provider binding.

## Deployment

The live shared-hosting install is configured at:

```text
https://pharmanp.pratikbhujel.com.np
```

Deployment uses the same Laravel codebase and built Vite assets. Keep `.env` private, run migrations only after backup, and use the release tag shown in `VERSION`/GitHub tags as the operational version source.
