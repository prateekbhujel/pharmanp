<?php

namespace App\Modules\Setup\Services;

use App\Modules\Setup\Contracts\AccessControlServiceInterface;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class AccessControlService implements AccessControlServiceInterface
{
    public function syncPermissions(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->permissionGroups() as $permissions) {
            foreach ($permissions as $permission) {
                Permission::query()->firstOrCreate([
                    'name' => $permission,
                    'guard_name' => 'web',
                ]);
            }
        }
    }

    public function permissionNames(): array
    {
        return array_values(array_merge(...array_values($this->permissionGroups())));
    }

    public function permissionGroups(): array
    {
        return [
            'Dashboard' => [
                'dashboard.view',
            ],
            'Inventory' => [
                'inventory.products.view',
                'inventory.products.create',
                'inventory.products.update',
                'inventory.products.delete',
                'inventory.masters.manage',
                'inventory.batches.view',
                'inventory.movements.view',
            ],
            'Purchase' => [
                'purchase.entries.view',
                'purchase.entries.create',
                'purchase.orders.manage',
                'purchase.returns.manage',
            ],
            'Sales' => [
                'sales.invoices.view',
                'sales.invoices.create',
                'sales.pos.use',
                'sales.returns.manage',
            ],
            'Parties' => [
                'party.suppliers.view',
                'party.suppliers.manage',
                'party.customers.view',
                'party.customers.manage',
            ],
            'Accounting' => [
                'accounting.vouchers.view',
                'accounting.vouchers.create',
                'accounting.books.view',
                'accounting.trial_balance.view',
            ],
            'Reports' => [
                'reports.view',
            ],
            'Imports' => [
                'imports.preview',
                'imports.commit',
                'exports.download',
            ],
            'MR' => [
                'mr.view',
                'mr.manage',
                'mr.visits.manage',
            ],
            'Setup' => [
                'settings.manage',
                'users.manage',
                'roles.manage',
                'setup.manage',
                'system.update.view',
            ],
        ];
    }
}
