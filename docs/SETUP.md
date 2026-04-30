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

For the current cPanel server reachable through the local SSH alias:

```bash
ssh pratiknp
cat ~/.pharmanp-db.env
```

That private file contains the generated MySQL settings for the demo database. Keep it out of Git. To use the same remote database locally, open an SSH tunnel and point local `.env` to the forwarded port:

```bash
ssh -N -L 3307:127.0.0.1:3306 pratiknp
```

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3307
DB_DATABASE=pratikb1_phnpdemo
DB_USERNAME=pratikb1_phnpdemo
DB_PASSWORD=from-private-env-file
```

## Demo And Load Data

Use a dedicated demo database, never a customer production database:

```bash
php artisan migrate --force
php artisan pharmanp:demo-load --profile=tiny --yes
php artisan pharmanp:demo-load --profile=showcase --yes
```

`tiny` proves the wiring quickly. `showcase` creates enough tenants, users, products, batches, purchases, sales, stock movements and accounting rows to demonstrate real pagination and scoped dashboards. `stress` is intentionally large and should only be used on infrastructure prepared for load testing.

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
