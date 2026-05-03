# PharmaNP Architecture

PharmaNP is one Laravel application serving a same-domain React SPA. Laravel owns routing, session auth, CSRF, validation, authorization, transactions, migrations, and deployment. React owns authenticated screens and data workflow.

## Backend Layout

- `app/Core`: shared DTOs, support classes, installation service, and cross-module utilities.
- `app/Modules/*`: domain modules with local models, DTOs, services, requests, resources, controllers, and policies.
- `config/pharmanp-modules.php`: the module manifest. It declares each module's backend namespace, frontend path, domain boundary, and service provider.
- `app/Providers/PharmaNpModuleServiceProvider.php`: registers the module registry and module service providers. A module should bind its contracts inside its module provider, not inside controllers.
- `app/Core/Modules/ModuleServiceProvider.php`: base provider for module-level contracts, concrete services, and module-owned API route loading.
- `app/Modules/<Module>/Routes/api.php`: authenticated JSON endpoints consumed by the React app. Each module owns its API route surface instead of pushing every route into one central file.
- `routes/web.php`: install/login/logout, printable documents, and the SPA fallback only.
- Controllers stay thin: validate, authorize, call a service, return a resource/JSON response.
- Services own transactions and integrity checks.
- Module services expose contracts across Inventory, Party, Purchase, Sales, Accounting, MR, Reports, ImportExport, Setup and Analytics. Repositories are not created by default. Add one only when a reusable query or persistence boundary becomes complex enough to justify it. Product listing is the reference pattern: controller -> FormRequest/DTO -> service -> repository contract -> resource.

Recommended module shape:

```text
app/Modules/<Module>/
  DTOs/
  Contracts/
  Http/Controllers/
  Http/Requests/
  Http/Resources/
  Models/
  Repositories/
    Contracts/
  Routes/
    api.php
  Services/
  <Module>ServiceProvider.php
```

DTOs normalize request payloads. Requests validate input and relationship existence. Resources own response shape. Services own business decisions and transactions. Repositories hide complex reusable query/persistence work behind contracts.

## Database

Relationship columns use `unsignedBigInteger` plus indexes, not DB-level foreign key constraints. Integrity is enforced in FormRequests and services. This keeps shared-hosting migrations and rollback/drop operations practical while preserving reliable application-level rules.

Company/store scoped columns are present where needed: `tenant_id`, `company_id`, `store_id`, `created_by`, `updated_by`, `deleted_by`. The internal `tenant_id` remains an installation scope identifier, not a hosted-service promise.

The default commercial deployment model is one standalone database per installation. This is easier to support on shared hosting, easier to hand over to a pharmacy, and simpler for backups and upgrades.

Migration filenames may retain early-development history after a live demo has been migrated. Do not rename or squash already-applied migration files on a shared-hosting database without a controlled reset, because Laravel will treat renamed files as new migrations and may try to recreate existing tables. There are no runtime `legacy_*` or `foundation_*` tables; those names are migration history only.

## Tenant And Branch Model

PharmaNP is built as a standalone product first. The current tenant scope represents one installed business/account, with company and branch/store columns prepared for isolation inside that installation. This supports pharmacies with multiple branches without forcing a hosted SaaS model too early.

Full multi-tenant SaaS is a separate operating model. Before selling a shared hosted instance to unrelated firms, every write/read path must be audited for tenant scope, background jobs must carry tenant context, file storage must be tenant isolated, and admin tools must prevent cross-tenant access.

Dashboard, report, inventory, party, MR and transaction APIs must scope by the authenticated user's `tenant_id` and `company_id` before applying filters. Tests cover dashboard isolation because summary widgets are one of the easiest places to accidentally leak cross-tenant totals.

## Frontend Layout

- `resources/js/core`: API client, auth provider, layout, shared components, hooks, utilities.
- `resources/js/modules`: feature modules. Each module owns screens and local composition.
- `resources/js/modules/<module>/routes.jsx`: frontend route ownership for that feature module. Each route file exports its lazy-loaded pages, module metadata, and route map.
- `resources/js/core/modules/routeRegistry.jsx`: the SPA module registry composed from module-owned route files, keeping AppShell and core code from becoming the owner of every screen path.
- `VITE_PHARMANP_API_BASE_URL` lets frontend developers run Vite against a backend host without editing source.
- Ant Design is used for serious data UI. Tailwind/CSS handles layout density, spacing, and polish.

## API Convention

Authenticated app APIs are under `/api/v1/*` and use Laravel session cookies plus CSRF because the React app is served from the same Laravel domain. This is intentional: Sanctum/JWT is not required for the browser SPA unless external/mobile API clients are added.

Lists use server-side pagination, search, filters, and sorting. Laravel resources control response shape. Transactional writes such as purchases, sales, returns, vouchers, payments, stock adjustments, and imports must run inside services/actions with database transactions.

The OpenAPI foundation is exposed at `GET /api/v1/openapi.json`. It starts from `docs/openapi/pharmanp.v1.json` and is augmented with discovered Laravel `/api/v1` routes loaded from module route files, so Swagger coverage stays broad as modules grow. Swagger UI is available at `/api-docs`.

## Large Data Guardrails

Laravel/PHP is not the limiting factor for pharmacy ERP workloads. The limiting factors are query shape, indexes, payload size, and background processing discipline. A 20-million-row installation must keep these rules:

- No React table may load all rows.
- Every index page must paginate, filter, and sort in SQL.
- Searchable/filterable fields need indexes or purpose-built search tables.
- Reports must stream/chunk/export through jobs where hosting allows it, with a synchronous fallback for shared hosting.
- Dashboard widgets must aggregate by date windows and indexed dimensions, not scan transaction detail tables on every request.
- File imports must validate in chunks and store rejected rows without holding the full workbook in memory.

A 100-million-row dataset is an infrastructure and data-lifecycle project, not a shared-hosting promise. That scale needs MySQL/MariaDB tuning, slow-query review, summary tables, archival/partition strategy for ledgers and stock movements, queue workers, backup/restore rehearsals, and realistic load tests before it is sold as supported.

Use `php artisan pharmanp:demo-load` to create repeatable chunked demo data. `tiny` is for local smoke tests, `showcase` is for a sales demo database, `stress` is for controlled infrastructure testing, and `scale10m`/`scale20m` are for serious MySQL/MariaDB load demonstrations only. The command uses direct chunked inserts so it can generate large datasets without going through slow browser workflows.

## Change Boundaries

Feature-specific behavior belongs in its module. Shared behavior belongs in `resources/js/core` or `app/Core` only when at least two modules need it. A feature request from one pharmacy should be introduced behind configuration, permissions, or a module-level option when it is not universally useful.

## Barcode Labels

Product barcode scanning is supported in POS and product search. PharmaNP also includes a local Code 128 renderer in `resources/js/core/utils/code128.js`, so product labels can be generated and printed without a third-party JavaScript package. If a product has no barcode, the print flow falls back to SKU/product code because POS lookup supports those identifiers too.

## Dependency Hygiene

Use `php artisan pharmanp:dependency-audit` before removing Composer or npm packages. The command is intentionally read-only: it reports direct usage signals and review candidates but never deletes `vendor`, `node_modules`, lock files, or dependencies. Production deployments should still install optimized dependencies with Composer and ship built Vite assets instead of uploading development caches.

## Deployment

Production does not need a Node server. Build assets with `npm run build`, upload Laravel files, run Composer, migrate after backup, and point the web root to `public`.
