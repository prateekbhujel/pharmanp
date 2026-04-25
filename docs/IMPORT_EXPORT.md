# Import / Export

The import flow is intentionally staged.

1. Upload CSV/XLSX.
2. Choose target: products, suppliers, customers, units, companies, opening stock, batches.
3. Preview detected columns and sample rows.
4. Map uploaded columns to system fields.
5. Validate required mappings and row structure.
6. Review valid/invalid counts.
7. Commit in chunks in a later action.
8. Export rejected rows once commit validation is implemented.

The foundation stores `import_jobs` and `import_staged_rows`. The first pass validates mapping quality only; it does not blindly insert business data.

Exports should use FastExcel for large tabular exports and DomPDF only where printable report layout matters.
