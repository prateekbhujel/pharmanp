# API

Base path: `/api/v1`

Authentication: Laravel session cookie with CSRF token. All app APIs are protected by `auth` and `installed` middleware.
Swagger UI: `/api-docs`

## Endpoint Coverage

The route source of truth is `routes/api_v1.php`. Runtime API docs are generated from the Laravel route collection and merged with curated schemas in `docs/openapi/pharmanp.v1.json`.

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

Frontend developers can use this document in Swagger UI, Insomnia, Postman, Bruno, or any OpenAPI-compatible client while still using the same Laravel session and CSRF flow as the React app.
