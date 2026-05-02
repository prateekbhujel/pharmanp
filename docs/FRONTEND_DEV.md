# Frontend Developer Workflow

PharmaNP keeps React inside the Laravel repository. Frontend developers should work in `resources/js`, `resources/css`, and Vite/package files.

## Normal Workflow

```bash
npm install
npm run dev
```

The Laravel app still provides auth, CSRF, Blade entry points, and API routes. Run the backend separately with:

```bash
php artisan serve
```

## Sparse Checkout Workflow

Use this when a frontend developer only wants the frontend surface but must keep the same repository structure:

```bash
git clone --filter=blob:none --sparse https://github.com/prateekbhujel/pharmanp.git pharmanp-frontend
cd pharmanp-frontend
git sparse-checkout set resources/js resources/css resources/views package.json package-lock.json vite.config.js
npm install
npm run dev
```

Do not create a separate frontend repository unless deployment architecture changes. Shared hosting expects Laravel to serve the built assets from `public/build`.

## Module Boundary

- Page modules live in `resources/js/modules/<module>`.
- Shared UI, hooks, API client, auth and formatting live in `resources/js/core`.
- SPA route ownership lives in `resources/js/core/modules/routeRegistry.jsx`.
- Backend module metadata is available from `GET /api/v1/modules`.
- API contract testing starts from `GET /api/v1/openapi.json`.

When adding a module, create the screen under `resources/js/modules`, add the lazy route in `routeRegistry.jsx`, and add any endpoints to `resources/js/core/api/endpoints.js`.

## UI Rules

- Use Ant Design for data-heavy controls: tables, forms, drawers, modals, upload, dates, selects, notifications, tabs and statistics.
- Use Tailwind/CSS for layout, density, spacing and polish.
- Use drawers for medium master forms, modals for quick add, full pages for transaction documents.
- All large lists must use backend pagination, sorting and filters.
