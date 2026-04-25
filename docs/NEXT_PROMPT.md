# Next Implementation Prompt

Continue PharmaNP as a production-minded Laravel + React pharmacy ERP/POS. Treat `pharmacyfyp` main as the legacy feature reference, but do not copy Blade/controllers directly. Rebuild cleanly in the current modular API + React architecture.

Priorities:

1. Implement purchase order and purchase entry as full-page React transaction forms.
2. Purchase entry must create batches and post stock movements inside one DB transaction.
3. Implement Sales/POS with barcode scan, batch/expiry-aware product selection, stock deduction, payment status and printable invoice/PDF.
4. Implement suppliers/customers with drawer forms, quick add, server-side tables and ledgers.
5. Implement accounting vouchers, day book, cash book, bank book and ledger using service/action classes.
6. Implement roles/permissions UI on top of Spatie permissions already installed.
7. Convert import wizard preview into confirmed chunked import with rejected-row download.
8. Add report APIs and React pages for sales, purchase, stock, low stock, expiry, supplier performance, customer ledger, product movement and MR performance.
9. Keep one shared database with tenant/company/store scoping. Do not add database-level foreign keys.
10. Every list must be server-side paginated. Every stock/accounting write must use DB transactions.

Verification before merge:

```bash
composer install
npm install
php artisan migrate:fresh --force
php artisan test
npm run build
```
