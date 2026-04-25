<?php

namespace App\Modules\Setup\Services;

use App\Modules\Setup\Models\FeatureCatalogItem;

class FeatureCatalogService
{
    public function grouped(): array
    {
        $this->syncDefaults();

        return FeatureCatalogItem::query()
            ->orderBy('sort_order')
            ->get()
            ->groupBy('module')
            ->map(fn ($items) => $items->values()->map(fn (FeatureCatalogItem $item) => [
                'code' => $item->code,
                'name' => $item->name,
                'description' => $item->description,
                'status' => $item->status,
                'is_billable' => $item->is_billable,
            ]))
            ->toArray();
    }

    public function syncDefaults(): void
    {
        foreach ($this->defaults() as $index => $feature) {
            FeatureCatalogItem::query()->updateOrCreate(
                ['code' => $feature['code']],
                [...$feature, 'sort_order' => $index + 1],
            );
        }
    }

    public function defaults(): array
    {
        return [
            ['module' => 'Inventory', 'code' => 'inventory.products', 'name' => 'Product master', 'description' => 'Medicine/company/unit/category master, barcode, stock thresholds and batch tracking.', 'status' => 'foundation', 'is_billable' => false],
            ['module' => 'Inventory', 'code' => 'inventory.batches', 'name' => 'Batch and expiry', 'description' => 'Batch quantity, expiry alerts, low stock rules and stock movement ledger.', 'status' => 'foundation', 'is_billable' => false],
            ['module' => 'Purchase', 'code' => 'purchase.orders', 'name' => 'Purchase orders', 'description' => 'Supplier ordering, approval, receive flow and payment update.', 'status' => 'planned', 'is_billable' => false],
            ['module' => 'Purchase', 'code' => 'purchase.entries', 'name' => 'Purchase entry', 'description' => 'Purchase bill entry that creates batches and posts stock transactionally.', 'status' => 'planned', 'is_billable' => false],
            ['module' => 'Purchase', 'code' => 'purchase.returns', 'name' => 'Purchase returns', 'description' => 'Return goods to supplier with batch and outstanding balance adjustment.', 'status' => 'planned', 'is_billable' => false],
            ['module' => 'Sales/POS', 'code' => 'sales.pos', 'name' => 'POS invoice', 'description' => 'Barcode-assisted counter sale with batch/expiry aware stock deduction.', 'status' => 'foundation', 'is_billable' => false],
            ['module' => 'Sales/POS', 'code' => 'sales.returns', 'name' => 'Sales returns', 'description' => 'Customer return flow with stock restoration and ledger impact.', 'status' => 'planned', 'is_billable' => false],
            ['module' => 'Accounting', 'code' => 'accounting.vouchers', 'name' => 'Vouchers', 'description' => 'Payment in/out, day book, cash book, bank book and ledger structure.', 'status' => 'foundation', 'is_billable' => false],
            ['module' => 'Accounting', 'code' => 'accounting.trial_balance', 'name' => 'Trial balance', 'description' => 'Basic account tree and report structure ready for stricter double-entry.', 'status' => 'planned', 'is_billable' => false],
            ['module' => 'Reports', 'code' => 'reports.operations', 'name' => 'Operational reports', 'description' => 'Sales, purchase, stock, expiry, low stock, supplier and product movement reports.', 'status' => 'foundation', 'is_billable' => false],
            ['module' => 'MR', 'code' => 'mr.performance', 'name' => 'MR performance', 'description' => 'Targets, visits, invoice/order value and territory tracking.', 'status' => 'foundation', 'is_billable' => true],
            ['module' => 'Import/Export', 'code' => 'imports.mapping', 'name' => 'Import wizard', 'description' => 'Upload, map, preview, validate, reject rows and confirm import.', 'status' => 'foundation', 'is_billable' => false],
            ['module' => 'Setup', 'code' => 'setup.branding', 'name' => 'Branding and fiscal year', 'description' => 'Company logo/name, sidebar layout, fiscal year and owner setup.', 'status' => 'foundation', 'is_billable' => false],
            ['module' => 'SaaS', 'code' => 'tenant.provisioning', 'name' => 'Tenant/demo provisioning', 'description' => 'Invite links and tenant flags for demos or client setups in one shared-hosting database.', 'status' => 'foundation', 'is_billable' => true],
        ];
    }
}
