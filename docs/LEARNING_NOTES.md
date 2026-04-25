# PharmaNP Engineering Notes

## React Module Structure

Screens live under `resources/js/modules`. Shared concerns stay in `resources/js/core`: API client, auth provider, layout, table hook, formatting utilities, and reusable components. A feature module should expose screens and keep module-specific composition local.

## Ant Design Usage

Use Ant Design for tables, forms, drawers, modals, uploads, date pickers, selects, cards, tabs, statistics, notifications, and tags. Keep transaction-heavy screens full page. Use drawers for medium forms and modals only for small confirmations or compact forms.

## Tailwind And CSS Usage

Use Tailwind/CSS for page layout, spacing, density, and responsive behavior. Avoid decorative marketing layout inside the ERP. Operational screens should prioritize scanning, filtering, comparison, and repeated action.

## API Conventions

Same-domain APIs use Laravel session, cookie, and CSRF protection. Controllers validate through FormRequest, authorize through policies/permissions, call services, and return resources or JSON. Validation errors should remain field-shaped for Ant Design forms.

## Server Table Conventions

Never load all rows into React tables. Every list screen should accept `page`, `per_page`, `search`, `sort_field`, `sort_order`, and module-specific filters. Backend queries must whitelist sort fields and add indexes for searchable/filterable columns.

## SQL And Indexing Notes

Shared-hosting migrations avoid DB-level foreign keys. Use unsigned relationship columns and indexes. Enforce relationship existence in FormRequests and services. Use transactions for stock, sales, purchase, returns, payments, vouchers, imports, and adjustments.

## Laravel Service / DTO / Resource Pattern

DTOs normalize validated request data. Services hold business rules and transactions. Resources define response shape. Repositories are optional and should only hide complex reusable query logic.

## Import / Export Flow

Imports are staged: upload, preview, map, validate, review rejected rows, then commit in chunks. Exports should use streaming/chunk-friendly libraries for large datasets.

## Setup And Deployment Notes

Setup creates company, store, owner user, roles, permissions, and default operating records. Production does not seed demo data automatically. Production updates must be backup-first and CLI-driven.
