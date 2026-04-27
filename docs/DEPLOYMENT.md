# PharmaNP Shared-Hosting Deployment

PharmaNP is built as a normal Laravel application with a React/Vite build inside `public/build`. Production does not need a Node server.

## Shared Hosting Shape

Point the domain or subdomain to the project root if the host allows it. The root `.htaccess` rewrites requests through `public/` and blocks direct access to Laravel folders such as `app`, `config`, `database`, `storage`, `vendor`, and `.env`.

If the hosting panel can point the document root directly to `public`, that is still the cleanest option. If not, the included root `.htaccess` keeps URLs like this:

```text
https://pharmanp.pratikbhujel.com.np
```

No `/public` suffix is required.

## SQLite Production Env

For small single-company installs on shared hosting, SQLite is acceptable. Use an absolute path in production:

```dotenv
APP_NAME=PharmaNP
APP_ENV=production
APP_DEBUG=false
APP_URL=https://pharmanp.pratikbhujel.com.np
APP_TIMEZONE=Asia/Kathmandu

DB_CONNECTION=sqlite
DB_DATABASE=/home8/pratikb1/pharmanp.pratikbhujel.com.np/database/database.sqlite

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
```

Do not commit `.env` or `database/database.sqlite`.

## GitHub Actions Deployment

The workflow in `.github/workflows/deploy-shared-hosting.yml` builds Composer dependencies and frontend assets, uploads a tar release archive over SSH, then extracts and runs Laravel finalize commands remotely. This avoids requiring `rsync` on shared hosting.

Required repository secrets:

- `SSH_HOST` = `cpanel.pratikbhujel.com.np`
- `SSH_USER` = `pratikb1`
- `SSH_PORT` = `1980`
- `SSH_PRIVATE_KEY`
- `DEPLOY_PATH` = `/home8/pratikb1/pharmanp.pratikbhujel.com.np`

The local SSH alias `pratiknp` is useful from your machine, but GitHub Actions cannot read local SSH config. Store the real host, user and key in repository secrets.

## Remote First-Time Prep

Create the deploy folder once on the server, then put a production `.env` there:

```bash
mkdir -p /home8/pratikb1/pharmanp.pratikbhujel.com.np/database
cd /home8/pratikb1/pharmanp.pratikbhujel.com.np
touch database/database.sqlite
```

The workflow creates the required Laravel writable folders on each deploy.

## Deployment Safety

The release archive excludes:

- `.env`
- `.phpunit.result.cache`, `.vite`, `.vscode`
- SQLite database files
- local storage folders
- `public/hot`
- `public/storage`
- `.git`
- `node_modules`

It does run `php artisan migrate --force`, so review migrations before merging to `main`.
