# Foundation Review

## Current Shape

- PharmaNP is a Laravel-served React SPA, which matches shared-hosting deployment and avoids a production Node server.
- Backend code is modular enough for growth without turning into enterprise-heavy boilerplate.
- Transaction workflows use services/actions and database transactions where stock or accounting state changes.
- The schema avoids database-level foreign keys while keeping indexed relationship columns for shared-hosting-friendly migration safety.
- Spatie permissions, setup, branding, fiscal years, users, roles and dropdown management are wired.

## Legacy Parity Position

- Inventory, purchase, sales/POS, returns, parties, accounting books, reports, import/export and OCR foundations are now represented in the React application.
- The application should preserve the legacy workflow sequence while upgrading UI structure, server-side tables and validation feedback.
- Remaining parity work is mostly depth and polish: exact print layouts, export templates, notification scheduling and stronger edge-case tests.

## Next Engineering Focus

Harden the operational flows that touch money and stock: purchase receive, sales payment update, returns, voucher posting, ledger history, report exports and import rollback behavior.
