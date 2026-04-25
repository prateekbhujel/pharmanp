<?php

namespace App\Modules\Setup\Services;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class AccessControlService
{
    public function syncPermissions(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->permissionNames() as $permission) {
            Permission::query()->firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }
    }

    public function permissionNames(): array
    {
        return collect($this->permissionOptions())
            ->pluck('name')
            ->values()
            ->all();
    }

    public function permissionOptions(): array
    {
        return collect($this->permissionCatalog())
            ->flatMap(fn (array $group) => $group['permissions'])
            ->values()
            ->all();
    }

    public function permissionGroups(): array
    {
        return collect($this->permissionCatalog())
            ->map(fn (array $group) => collect($group['permissions'])->pluck('name')->values()->all())
            ->all();
    }

    public function permissionCatalog(): array
    {
        return [
            'Dashboard' => [
                'label' => 'Dashboard',
                'description' => 'Home screen metrics, low stock, expiry and operating summary.',
                'permissions' => [
                    $this->permission('dashboard.view', 'View dashboard', 'Open the operations dashboard with sales, stock, balance and alert widgets.'),
                ],
            ],
            'Inventory' => [
                'label' => 'Inventory',
                'description' => 'Product catalog, stock masters, batches and movement visibility.',
                'permissions' => [
                    $this->permission('inventory.products.view', 'View products', 'Open product list, search stock items and review product details.'),
                    $this->permission('inventory.products.create', 'Create products', 'Add new products and opening catalog records.'),
                    $this->permission('inventory.products.update', 'Edit products', 'Update product details, pricing, barcode and catalog fields.'),
                    $this->permission('inventory.products.delete', 'Delete products', 'Soft delete products that should no longer be used.'),
                    $this->permission('inventory.masters.manage', 'Manage companies, units and categories', 'Maintain company, unit and category master data used by products and transactions.'),
                    $this->permission('inventory.batches.view', 'View batches', 'Inspect received batches, expiry dates and available quantity.'),
                    $this->permission('inventory.movements.view', 'View stock movement ledger', 'Review stock in, stock out and adjustment history.'),
                ],
            ],
            'Purchase' => [
                'label' => 'Purchase',
                'description' => 'Purchase order, supplier bill and stock receipt operations.',
                'permissions' => [
                    $this->permission('purchase.entries.view', 'View purchase bills', 'Open purchase entries, supplier bills and received stock history.'),
                    $this->permission('purchase.entries.create', 'Create purchase bills', 'Post received bills, create batches and bring stock into inventory.'),
                    $this->permission('purchase.orders.manage', 'Manage purchase orders', 'Create, edit and track purchase orders before goods are received.'),
                    $this->permission('purchase.returns.manage', 'Manage purchase returns', 'Create purchase return entries and supplier adjustments.'),
                ],
            ],
            'Sales' => [
                'label' => 'Sales and POS',
                'description' => 'POS billing, invoice history, payments and customer-facing sales flow.',
                'permissions' => [
                    $this->permission('sales.invoices.view', 'View sales invoices', 'Open invoice history, payment status and printable sales bills.'),
                    $this->permission('sales.invoices.create', 'Create sales invoices', 'Post sales invoices and update customer balances.'),
                    $this->permission('sales.pos.use', 'Use POS screen', 'Use the billing screen with barcode scan and walk-in customer flow.'),
                    $this->permission('sales.returns.manage', 'Manage sales returns', 'Create return invoices and reverse stock and balance correctly.'),
                ],
            ],
            'Parties' => [
                'label' => 'Suppliers and Customers',
                'description' => 'Supplier/customer master data, drawer quick-add and balance history.',
                'permissions' => [
                    $this->permission('party.suppliers.view', 'View suppliers', 'Open supplier list, balances and transaction history.'),
                    $this->permission('party.suppliers.manage', 'Manage suppliers', 'Create, edit and disable supplier records.'),
                    $this->permission('party.customers.view', 'View customers', 'Open customer list, balances and ledger history.'),
                    $this->permission('party.customers.manage', 'Manage customers', 'Create, edit and disable customer records.'),
                ],
            ],
            'Accounting' => [
                'label' => 'Accounting',
                'description' => 'Voucher posting, books, ledgers and trial balance visibility.',
                'permissions' => [
                    $this->permission('accounting.vouchers.view', 'View vouchers', 'Open voucher list and posted accounting entries.'),
                    $this->permission('accounting.vouchers.create', 'Create vouchers', 'Post cash, bank, journal and adjustment vouchers.'),
                    $this->permission('accounting.books.view', 'View books and ledger', 'Open day book, cash book, bank book and ledger reports.'),
                    $this->permission('accounting.trial_balance.view', 'View trial balance', 'Review debit, credit and closing balance by account head.'),
                ],
            ],
            'Reports' => [
                'label' => 'Reports',
                'description' => 'Filtered reports for sales, purchase, stock, expiry, ledgers and MR performance.',
                'permissions' => [
                    $this->permission('reports.view', 'View reports', 'Open operational and accounting reports with server-side filters.'),
                ],
            ],
            'Imports' => [
                'label' => 'Import and Export',
                'description' => 'Structured data import preview, commit flow and report downloads.',
                'permissions' => [
                    $this->permission('imports.preview', 'Preview imports', 'Upload files, map columns and validate import rows before commit.'),
                    $this->permission('imports.commit', 'Confirm imports', 'Commit validated import jobs into live tables.'),
                    $this->permission('exports.download', 'Download exports', 'Download report exports and rejected import rows.'),
                ],
            ],
            'MR' => [
                'label' => 'Medical Representative',
                'description' => 'MR master, visit tracking, linked logins and performance monitoring.',
                'permissions' => [
                    $this->permission('mr.view', 'View MR dashboard', 'Open MR performance, targets, visits and sales contribution.'),
                    $this->permission('mr.manage', 'Manage MR records', 'Create and update medical representatives and their core details.'),
                    $this->permission('mr.visits.manage', 'Manage MR visits', 'Create and update visit logs, status and order value.'),
                ],
            ],
            'Setup' => [
                'label' => 'Setup and Administration',
                'description' => 'Branding, users, roles, setup control and update visibility.',
                'permissions' => [
                    $this->permission('settings.manage', 'Manage application setup', 'Update branding, logos, layout and setup-level settings.'),
                    $this->permission('users.manage', 'Manage users', 'Create users, update profile access and remove inactive accounts.'),
                    $this->permission('roles.manage', 'Manage roles and access', 'Create roles and decide which modules each role can use.'),
                    $this->permission('setup.manage', 'Access onboarding guide', 'Open setup and first-run guidance pages.'),
                    $this->permission('system.update.view', 'View system update page', 'Open version, backup and update readiness screens.'),
                ],
            ],
        ];
    }

    public function summarize(array $permissionNames): array
    {
        $assigned = collect($permissionNames);

        return collect($this->permissionCatalog())
            ->map(function (array $group) use ($assigned) {
                $selected = collect($group['permissions'])
                    ->whereIn('name', $assigned)
                    ->values();

                if ($selected->isEmpty()) {
                    return null;
                }

                return [
                    'group' => $group['label'],
                    'selected_count' => $selected->count(),
                    'total_count' => count($group['permissions']),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function permission(string $name, string $label, string $description): array
    {
        return [
            'name' => $name,
            'label' => $label,
            'description' => $description,
        ];
    }
}
