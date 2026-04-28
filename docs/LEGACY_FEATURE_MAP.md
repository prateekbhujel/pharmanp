# PharmaNP Legacy Feature Map

This map tracks the `pharmacyfyp` screens that must be rebuilt in PharmaNP as React + Ant Design modules. The goal is not a Bootstrap clone. The workflow, fields, filters, quick-add behavior, print/export behavior and permissions should stay familiar, while the UI becomes cleaner and more production-ready.

## Parity Position

Legacy parity is not certified complete yet. PharmaNP has the production foundation and several core flows rebuilt, but each legacy screen still needs a final route, view, payload, permission, print and export audit before we can say it is exactly covered.

Status labels:

- Done: present in PharmaNP and tested enough for daily use
- Partial: present, but still needs export, print, edge-case, permission or polish work
- Pending: known legacy feature that still needs a clean React/API rebuild

## Current Parity Snapshot

| Legacy area | Status | Notes |
| --- | --- | --- |
| App shell, sidebar, header, notifications | Partial | Modern shell exists. Notification tray and legacy digest rules need final parity audit. |
| Dashboard | Partial | KPI, expiry, low stock and business signals exist. Legacy card/report coverage still needs comparison. |
| Company/manufacturer master | Partial | List, create/edit, status, soft delete and exports are represented. Final import/export behavior needs verification. |
| Unit master | Partial | CRUD exists. Legacy sale/purchase/both behavior needs final payload audit. |
| Product master | Partial | Core fields, image/barcode, server table, status and soft delete exist. Exact legacy field parity still needs screen review. |
| Batch, stock adjustment and movement ledger | Partial | Stock-facing flows exist. Posting behavior must be verified against every legacy purchase/sales/return path. |
| Suppliers and customers | Partial | Master records and quick-add patterns exist. Ledger/history/print parity still needs signoff. |
| Purchase entry | Partial | Full-page transaction flow and stock posting exist. Invoice/PDF/export and edit edge cases need audit. |
| Purchase order | Partial | Rebuilt as a transaction flow. Approval/receive/payment parity needs final audit. |
| Purchase return | Partial | Return workflow and stock reversal exist. Print/PDF/export parity needs verification. |
| Sales/POS invoice | Partial | Walk-in customer, barcode, batch-aware items and print/PDF foundation exist. Payment update and all invoice history paths need audit. |
| Sales return | Partial | Return list/create/edit and stock reversal exist. Legacy print/history behavior needs verification. |
| Payments, expenses and vouchers | Partial | Core tables/forms exist. Accounting books need deeper ERP validation against `midasedu` account patterns and `pharmacyfyp`. |
| Ledger, day book, cash book, bank book, trial balance | Partial | Pages/APIs exist, but accounting correctness needs transaction-level test cases before calling complete. |
| Reports and exports | Partial | Report pages exist under Reports. Every legacy Excel/PDF export still needs a route-by-route audit. |
| Import center and OCR purchase helper | Partial | Foundations exist. Legacy sample downloads, rejected rows and larger OCR cases need hardening. |
| Users, roles and permissions | Partial | Admin UI exists. Permission wording, role assignment and per-screen enforcement need final pass. |
| Settings, profile, dropdowns and fiscal years | Partial | Settings pages exist. All dropdown domains from legacy should be consolidated under Master and audited. |

## Main Shell

- Dashboard with business alerts, sales, purchases, stock, expiry and MR signals
- Left sidebar module grouping from `pharmacyfyp`
- Header with notification tray, user profile and logout
- Server-side tables for every list
- Modal or drawer for compact master data forms
- Full-page transaction forms for purchase, sales, returns, vouchers and payments

## Inventory

- Company/manufacturer master with import, export, restore and soft delete
- Unit master with sale/purchase/both type
- Product master with legacy fields: company, unit, product code, barcode, name, generic, composition, group, manufacturer, formulation, conversion, reorder, status, previous price, MRP, CC rate, discount, selling/display price, keywords, description and image
- Batch management with expiry and quantity tracking
- Stock adjustment
- Stock/case movement ledger
- Low stock and expiry alert surfaces

## Purchase

- Supplier master with quick add, import/export, restore and soft delete
- Purchase bills list with supplier/date/payment filters
- Purchase entry full page with quick product/supplier, batch creation and stock posting
- Purchase order create/list/show/approve/receive/payment
- Purchase return create/edit/show/print with stock reversal
- Purchase invoice print/PDF

## Sales / POS

- POS / sales invoice full page with walk-in customer, quick customer/product, barcode scan, batch and expiry aware item selection
- Sales invoice list/show/print/PDF
- Sales return create/edit/list with invoice item lookup and stock reversal
- Payment update from invoice

## Party And Accounts

- Customer master with ledger, invoice history, return history and print/PDF
- Supplier ledger/payables through purchase and payments
- Payment in/out with outstanding bill settlement and on-account balance
- Expenses
- Vouchers
- Ledger
- Day book
- Account tree
- Trial balance
- Cash book
- Bank book

## Reports And Exports

- Sales report
- Purchase history
- Supplier performance
- Low stock
- Expiry alert
- Inventory products and batches
- Customer ledger
- Finance exports: ledger, trial balance, cash book, bank book, account tree
- Excel/CSV and PDF exports where legacy has them

## Administration

- Users CRUD with role/status management
- Role access CRUD with permission groups and searchable permission assignment
- Profile management
- Settings: app/company info, email setup, dropdown options, party types, supplier types
- Notification/email digest patterns
- OCR purchase invoice upload/draft flow

## Build Order

1. Stabilize app shell, dashboard and notifications.
2. Finish Inventory masters, products, batches and stock movement.
3. Finish Purchase bills, orders, returns and supplier ledger.
4. Finish Sales POS, invoices, returns and customer ledger.
5. Finish Accounting: payments, expenses, vouchers and books.
6. Finish Reports/export coverage.
7. Finish Admin/settings/profile/notification polish.
