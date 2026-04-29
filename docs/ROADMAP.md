# PharmaNP Delivery Status

This document tracks production parity against the legacy `pharmacyfyp` workflow. It is not shown inside the application.

## Built In Current Foundation

- Inventory masters: products, companies/manufacturers, units, categories, batches, stock adjustment and case movement.
- Purchase workflow: purchase orders, purchase entry, receive support, batch creation, stock movement posting, invoice print/PDF path and purchase returns.
- Sales workflow: POS/sales invoice, walk-in customer support, barcode-assisted item selection, batch/expiry-aware deduction, payment update, print/PDF path and sales returns.
- Party workflow: supplier/customer CRUD, quick add support, server-side lists and ledger/report integration.
- Accounting workflow: vouchers, payments, expenses, day book, cash book, bank book, ledger, account tree and trial balance report structure.
- Reports: sales, purchase, stock, low stock, expiry, supplier performance, customer ledger, supplier ledger, product movement and MR performance.
- Import/export: upload, mapping, validation preview, confirmed import, rejected-row download and core exports.
- OCR purchase helper: document upload, extraction foundation and draft handoff path.
- Administration: users, roles, permissions, dropdown/data lookup, fiscal years, branding and operational settings.
- Calendar: AD/BS display support through shared date components.

## Remaining Production Hardening

- Broaden export/PDF formatting coverage so every report and invoice format matches final customer templates.
- Add background worker paths for very large imports while keeping the current shared-hosting sync fallback.
- Add notification delivery scheduling for expiry/low stock/email digest behavior.
- Expand automated tests around purchase receive, returns, voucher posting, report exports and OCR draft conversion.
- Add deployment-specific scripts for shared hosting release backup, asset publishing and rollback.

## Design Direction

- Keep the legacy workflow shape so existing users do not lose feature familiarity.
- Use React + Ant Design for modern tables, drawers, modals and full-page transaction forms.
- Keep date filters empty by default; reports should only filter by date when the user chooses a range.
- Keep settings split by task: branding, company details, operating defaults, numbering, SMTP and fiscal years.
