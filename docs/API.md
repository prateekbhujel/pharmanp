# API

Base path: `/api/v1`

Authentication: APIs accept either Laravel session cookie authentication or a PharmaNP bearer token. All app APIs are protected by the installed check and authenticated user context.
Swagger UI: `/api-docs`

## API Tokens

For Swagger, mobile prototypes, Postman, Bruno, or a frontend running without a Laravel session, issue a short-lived bearer token from CLI:

```bash
php artisan pharmanp:api-token pratik@admin.com --name=Swagger --days=7
```

Copy the token once and paste the token value into Swagger UI through **Authorize**. For curl, Postman, Bruno, or custom clients, send:

```text
Bearer <token>
```

The plain token is never stored. The database stores only a SHA-256 hash, expiry, owner user, and last-used timestamp. Same-domain React still uses the normal Laravel session and CSRF flow.

## Endpoint Coverage

The route source of truth is each module's `app/Modules/<Module>/Routes/api.php` file. Module service providers load those routes under `/api/v1` with the installed/authenticated API middleware. Runtime API docs are generated from the Laravel route collection and merged with curated schemas in `docs/openapi/pharmanp.v1.json`.

Major groups:

- Core: current user, modules, dashboard, global search.
- Inventory: products, masters, batches, adjustments, movement ledger, exports.
- Party: suppliers, customers, ledgers.
- Purchase: orders, receive flow, purchase entry, purchase return.
- Sales: product lookup, POS/invoices, payment updates, sales returns.
- Accounting: vouchers, expenses, payments.
- MR/Field Force: representatives, visits, branches, performance.
- Reports: operational/accounting reports and exports.
- Import Export: target metadata, samples, preview, confirm, rejected rows, OCR handoff.
- Setup: branding, users, roles, fiscal years, dropdown masters, admin settings.

## Server Table Query

```text
page=1
per_page=15
search=para
sort_field=name
sort_order=asc
company_id=1
```

Laravel resources return `data`, `links`, and `meta` for paginated lists.

## OpenAPI

The OpenAPI document lives at:

```text
GET /api/v1/openapi.json
```

Swagger UI lives at:

```text
GET /api-docs
```

Curated source file:

```text
docs/openapi/pharmanp.v1.json
```

Frontend developers can use this document in Swagger UI, Insomnia, Postman, Bruno, or any OpenAPI-compatible client with either session auth or bearer tokens.
