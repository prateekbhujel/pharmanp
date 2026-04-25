# PharmaNP Architecture

PharmaNP is one Laravel application serving a same-domain React SPA. Laravel owns routing, session auth, CSRF, validation, authorization, transactions, migrations, and deployment. React owns authenticated screens and data workflow.

## Backend Layout

- `app/Core`: shared DTOs, support classes, installation service, and cross-module utilities.
- `app/Modules/*`: domain modules with local models, DTOs, services, requests, resources, controllers, and policies.
- Controllers stay thin: validate, authorize, call a service, return a resource/JSON response.
- Services own transactions and integrity checks.
- Repositories are not created by default. Add one only when a reusable query or persistence boundary becomes complex enough to justify it.

## Database

Relationship columns use `unsignedBigInteger` plus indexes, not DB-level foreign key constraints. Integrity is enforced in FormRequests and services. This keeps shared-hosting migrations and rollback/drop operations practical while preserving reliable application-level rules.

Early SaaS-aware columns are present where needed: `tenant_id`, `company_id`, `store_id`, `created_by`, `updated_by`, `deleted_by`.

## Frontend Layout

- `resources/js/core`: API client, auth provider, layout, shared components, hooks, utilities.
- `resources/js/modules`: feature modules. Each module owns screens and local composition.
- Ant Design is used for serious data UI. Tailwind/CSS handles layout density, spacing, and polish.

## API Convention

Authenticated app APIs are under `/api/v1/*` and use Laravel web middleware, session cookies, and CSRF. Lists use server-side pagination, search, filters, and sorting. Laravel resources control response shape.

## Deployment

Production does not need a Node server. Build assets with `npm run build`, upload Laravel files, run Composer, migrate after backup, and point the web root to `public`.
