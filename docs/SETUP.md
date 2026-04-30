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

For cPanel/MySQL deployments, create a database and user in cPanel, grant that user access to the database, then switch the database section of `.env` from SQLite to MySQL:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cpaneluser_pharmanp
DB_USERNAME=cpaneluser_pharmanp
DB_PASSWORD=your-db-password
```

If the database is remote rather than on the same cPanel account, the MySQL server must allow the hosting account IP in its remote MySQL allowlist. Do not commit these credentials.

## Dependency Audit

Before trimming dependencies, run:

```bash
php artisan pharmanp:dependency-audit
```

The audit is read-only. Remove a package only after checking its usage manually and rebuilding/testing the app.

## Installed Lock

Setup is blocked after installation by the `settings` row and `storage/app/installed`. Reset should be done by a controlled CLI/admin action, not by browser.

## Updates

Use:

```bash
php artisan pharmanp:backup
php artisan pharmanp:update --dry-run
```

The browser update-check page is informational only. It must not run destructive update operations.
