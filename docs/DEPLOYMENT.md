# PharmaNP Shared-Hosting Deployment

PharmaNP is built as a normal Laravel application with a React/Vite build inside `public/build`. Production does not need a Node server.

## Shared Hosting Shape

Point the domain or subdomain to the project root if the host allows it. The root `.htaccess` rewrites requests through `public/` and blocks direct access to Laravel folders such as `app`, `config`, `database`, `storage`, `vendor`, and `.env`.

If the hosting panel can point the document root directly to `public`, that is still the cleanest option. If not, the included root `.htaccess` keeps URLs like this:

```text
https://pharma.pratikbhujel.com.np
```

No `/public` suffix is required.

## SQLite Production Env

For small single-company installs on shared hosting, SQLite is acceptable. Use an absolute path in production:

```dotenv
APP_NAME=PharmaNP
APP_ENV=production
APP_DEBUG=false
APP_URL=https://pharma.pratikbhujel.com.np

DB_CONNECTION=sqlite
DB_DATABASE=/home/account/pharmanp/database/database.sqlite

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
```

Do not commit `.env` or `database/database.sqlite`.

## GitHub Actions Deployment

The workflow in `.github/workflows/deploy-shared-hosting.yml` builds Composer dependencies and frontend assets, rsyncs the app, then runs Laravel finalize commands remotely.

Required repository secrets:

- `SSH_HOST`
- `SSH_USER`
- `SSH_PORT` optional, defaults to `22`
- `SSH_PRIVATE_KEY`
- `DEPLOY_PATH`

The local SSH alias `pratiknp` is useful from your machine, but GitHub Actions cannot read local SSH config. Store the real host, user and key in repository secrets.

## Remote First-Time Prep

Create the deploy folder once on the server, then put a production `.env` there:

```bash
mkdir -p /home/account/pharmanp
cd /home/account/pharmanp
touch database/database.sqlite
```

The workflow creates the required Laravel writable folders on each deploy.

## Deployment Safety

The workflow excludes:

- `.env`
- SQLite database files
- local storage folders
- `public/hot`
- `.git`
- `node_modules`

It does run `php artisan migrate --force`, so review migrations before merging to `main`.
