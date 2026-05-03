# PharmaNP

PharmaNP is a Laravel + React pharmacy ERP/POS application for Nepal-focused pharmacy and distributor operations.

## Stack

- Laravel 12
- React 19, Vite, Tailwind CSS
- Ant Design
- SQLite for simple shared-hosting installs; MySQL/MariaDB for demos and larger installs
- Session/cookie/CSRF auth for same-domain SPA deployment, plus hashed bearer API tokens for Swagger/mobile/frontend integration testing

## Local Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
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

```bash
npm install
npm run dev
```

For a frontend-only sparse checkout:

```bash
git clone --filter=blob:none --sparse https://github.com/prateekbhujel/pharmanp.git pharmanp-frontend
cd pharmanp-frontend
git sparse-checkout set --skip-checks resources/js resources/css resources/views package.json package-lock.json vite.config.js
npm install
npm run dev
```

The frontend still expects a running Laravel backend from the same app URL. Production uses `npm run build`; no Node server is required on shared hosting.

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
php artisan pharmanp:api-token pratik@admin.com --name=Swagger --days=7
```

Open `/api-docs` and use the generated value in the Authorize dialog as a bearer token.

## Deployment

The live shared-hosting install is configured at:

```text
https://pharmanp.pratikbhujel.com.np
```

Shared-hosting deployment notes and the GitHub Actions secret list are in [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md).
