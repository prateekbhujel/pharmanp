# Setup And Deployment

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

Visit `/setup`, create the company, store, and owner user, then sign in.

## Shared Hosting

- Point document root to `public`.
- Build assets before upload if Node is unavailable on the host.
- Keep `.env` outside Git.
- Run migrations only after backup.
- Do not run demo seeders in production.

## Installed Lock

Setup is blocked after installation by the `settings` row and `storage/app/installed`. Reset should be done by a controlled CLI/admin action, not by browser.

## Updates

Use:

```bash
php artisan pharmanp:backup
php artisan pharmanp:update --dry-run
```

The browser update-check page is informational only. It must not run destructive update operations.
