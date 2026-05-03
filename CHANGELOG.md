# Changelog

## 1.0.2 - 2026-05-03

- Aligned distributor ERP menus with purchase expiry returns, sales expiry returns, setup masters, targets, areas, divisions and employee hierarchy.
- Updated product master workflow for product code, HS code, division, group, manufacturer, packaging type and case movement while keeping old data compatible.
- Added setup structure screens for employees, areas, divisions and targets with server-side tables, modal forms, soft delete visibility and restore actions.
- Expanded reports navigation for aging, expiry buckets, dumping, MR comparisons, company/customer analysis and target achievement.
- Preserved accounting, voucher and payment flows as the financial backbone while routing sales/purchase payment entry points into accounting.

## 1.0.1 - 2026-04-30

- Promoted the React pharmacy ERP foundation into the first production release line.
- Added keyboard-first transaction flow for sales, purchases, returns, vouchers, payments and expenses.
- Added keyboard-editable AD/BS date inputs, improved Nepali range picker navigation and cleaned duplicated page headings now covered by breadcrumbs.
- Polished dashboard cards, notification persistence, table styling, report views and product batch/history visibility.
- Added product metadata/version support for stable footer and system version display.

## 0.1.0-foundation - 2026-04-25

- Initialized Laravel 12 + React + Vite + Tailwind + Ant Design application.
- Added setup wizard, session auth, protected same-domain API, modular inventory product CRUD, import preview/mapping skeleton, dashboard summary API, POS barcode lookup skeleton, and update-check skeleton.
- Added shared-hosting friendly migrations with indexed relationship columns and no database-level foreign key constraints.
