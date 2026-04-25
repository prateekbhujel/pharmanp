# PharmaNP

PharmaNP is a Laravel + React pharmacy ERP/POS foundation for Nepal-focused pharmacy and distributor operations.

## Stack

- Laravel 12
- React 19, Vite, Tailwind CSS
- Ant Design
- MySQL/MariaDB for production
- Session/cookie/CSRF auth for same-domain SPA deployment

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

Open `/setup` first, create company/store/admin, then sign in.

Default setup form values are filled for local speed:

- Admin email: `pratik@admin.com`
- Admin password: `done`

Change them before production handover.

## Frontend Development

```bash
npm install
npm run dev
```

For a frontend-only sparse checkout:

```bash
git clone --filter=blob:none --sparse https://github.com/prateekbhujel/pharmanp.git pharmanp-frontend
cd pharmanp-frontend
git sparse-checkout set resources/js resources/css resources/views package.json package-lock.json vite.config.js
npm install
npm run dev
```

The frontend still expects a running Laravel backend from the same app URL. Production uses `npm run build`; no Node server is required on shared hosting.

## Product Position

PharmaNP is shipped as a standalone Laravel application. A pharmacy installs it, completes the first-run setup, configures branding/fiscal year/roles, and begins daily operation from the same codebase. It uses one MySQL/MariaDB database with company and store scoped columns, which keeps shared-hosting deployment simple while still protecting data boundaries inside the app.
