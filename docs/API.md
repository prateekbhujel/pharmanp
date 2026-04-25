# API

Base path: `/api/v1`

Authentication: Laravel session cookie with CSRF token. All app APIs are protected by `auth` and `installed` middleware.

## Current Endpoints

- `GET /me`
- `GET /dashboard/summary`
- `GET /system/update-check`
- `GET /inventory/products`
- `POST /inventory/products`
- `PUT /inventory/products/{product}`
- `DELETE /inventory/products/{product}`
- `GET /inventory/products/meta`
- `GET /sales/product-lookup`
- `GET /imports/targets`
- `POST /imports/preview`
- `POST /imports/confirm`

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
