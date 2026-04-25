# PharmaNP Legacy Feature Map

This map tracks the `pharmacyfyp` screens that must be rebuilt in PharmaNP as React + Ant Design modules. The goal is not a Bootstrap clone. The workflow, fields, filters, quick-add behavior, print/export behavior and permissions should stay familiar, while the UI becomes cleaner and more production-ready.

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
