# PharmaNP Architecture

PharmaNP is one Laravel application serving a same-domain React SPA. Laravel owns routing, session auth, CSRF, validation, authorization, transactions, migrations, and deployment. React owns authenticated screens and data workflow.

## Backend Layout

- `app/Core`: shared DTOs, support classes, installation service, and cross-module utilities.
- `app/Modules/*`: domain modules with local models, DTOs, services, requests, resources, controllers, and policies.
- `routes/api_v1.php`: authenticated JSON endpoints consumed by the React app.
- `routes/web.php`: install/login/logout, printable documents, and the SPA fallback only.
- Controllers stay thin: validate, authorize, call a service, return a resource/JSON response.
- Services own transactions and integrity checks.
- Repositories are not created by default. Add one only when a reusable query or persistence boundary becomes complex enough to justify it.

## Database

Relationship columns use `unsignedBigInteger` plus indexes, not DB-level foreign key constraints. Integrity is enforced in FormRequests and services. This keeps shared-hosting migrations and rollback/drop operations practical while preserving reliable application-level rules.

Company/store scoped columns are present where needed: `tenant_id`, `company_id`, `store_id`, `created_by`, `updated_by`, `deleted_by`. The internal `tenant_id` remains an installation scope identifier, not a hosted-service promise.

The default commercial deployment model is one standalone database per installation. This is easier to support on shared hosting, easier to hand over to a pharmacy, and simpler for backups and upgrades.

## Tenant And Branch Model

PharmaNP is built as a standalone product first. The current tenant scope represents one installed business/account, with company and branch/store columns prepared for isolation inside that installation. This supports pharmacies with multiple branches without forcing a hosted SaaS model too early.

Full multi-tenant SaaS is a separate operating model. Before selling a shared hosted instance to unrelated firms, every write/read path must be audited for tenant scope, background jobs must carry tenant context, file storage must be tenant isolated, and admin tools must prevent cross-tenant access.

## Frontend Layout

- `resources/js/core`: API client, auth provider, layout, shared components, hooks, utilities.
- `resources/js/modules`: feature modules. Each module owns screens and local composition.
- Ant Design is used for serious data UI. Tailwind/CSS handles layout density, spacing, and polish.

## API Convention

Authenticated app APIs are under `/api/v1/*` and use Laravel session cookies plus CSRF because the React app is served from the same Laravel domain. This is intentional: Sanctum/JWT is not required for the browser SPA unless external/mobile API clients are added.

Lists use server-side pagination, search, filters, and sorting. Laravel resources control response shape. Transactional writes such as purchases, sales, returns, vouchers, payments, stock adjustments, and imports must run inside services/actions with database transactions.

## Large Data Guardrails

Laravel/PHP is not the limiting factor for pharmacy ERP workloads. The limiting factors are query shape, indexes, payload size, and background processing discipline. A 20-million-row installation must keep these rules:

- No React table may load all rows.
- Every index page must paginate, filter, and sort in SQL.
- Searchable/filterable fields need indexes or purpose-built search tables.
- Reports must stream/chunk/export through jobs where hosting allows it, with a synchronous fallback for shared hosting.
- Dashboard widgets must aggregate by date windows and indexed dimensions, not scan transaction detail tables on every request.
- File imports must validate in chunks and store rejected rows without holding the full workbook in memory.

## Change Boundaries

Feature-specific behavior belongs in its module. Shared behavior belongs in `resources/js/core` or `app/Core` only when at least two modules need it. A feature request from one pharmacy should be introduced behind configuration, permissions, or a module-level option when it is not universally useful.

## Deployment

Production does not need a Node server. Build assets with `npm run build`, upload Laravel files, run Composer, migrate after backup, and point the web root to `public`.
