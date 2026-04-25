<?php

namespace App\Modules\Setup\Services;

use App\Core\Services\InstallationService;
use App\Models\User;
use App\Modules\Inventory\Models\Company;
use App\Modules\Inventory\Models\ProductCategory;
use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Models\Unit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SetupService
{
    public function __construct(
        private readonly InstallationService $installation,
    ) {}

    public function complete(array $data): array
    {
        if ($this->installation->installed()) {
            throw ValidationException::withMessages([
                'setup' => 'PharmaNP is already installed.',
            ]);
        }

        if (! app()->environment(['local', 'testing']) && (bool) ($data['seed_demo'] ?? false)) {
            throw ValidationException::withMessages([
                'seed_demo' => 'Demo seed data is disabled outside local/testing environments.',
            ]);
        }

        return DB::transaction(function () use ($data) {
            $company = Company::query()->create([
                'name' => $data['company']['name'],
                'legal_name' => $data['company']['legal_name'] ?? $data['company']['name'],
                'pan_number' => $data['company']['pan_number'] ?? null,
                'phone' => $data['company']['phone'] ?? null,
                'email' => $data['company']['email'] ?? null,
                'address' => $data['company']['address'] ?? null,
                'company_type' => 'pharmacy',
                'is_active' => true,
            ]);

            $store = Store::query()->create([
                'company_id' => $company->id,
                'name' => $data['store']['name'],
                'code' => 'MAIN',
                'phone' => $data['store']['phone'] ?? null,
                'address' => $data['store']['address'] ?? null,
                'is_default' => true,
                'is_active' => true,
            ]);

            $admin = User::query()->create([
                'company_id' => $company->id,
                'store_id' => $store->id,
                'name' => $data['admin']['name'],
                'email' => $data['admin']['email'],
                'password' => Hash::make($data['admin']['password']),
                'is_owner' => true,
                'is_active' => true,
            ]);

            $this->seedPermissions($admin);
            $this->seedOperatingDefaults($company->id, $admin->id);
            $this->installation->markInstalled([
                'company_id' => $company->id,
                'store_id' => $store->id,
            ]);

            return [
                'company' => $company,
                'store' => $store,
                'admin' => $admin,
            ];
        });
    }

    private function seedPermissions(User $admin): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = collect([
            'dashboard.view',
            'inventory.products.view',
            'inventory.products.create',
            'inventory.products.update',
            'inventory.products.delete',
            'inventory.batches.view',
            'sales.invoices.view',
            'sales.invoices.create',
            'purchase.entries.view',
            'purchase.entries.create',
            'accounting.vouchers.view',
            'accounting.vouchers.create',
            'reports.view',
            'imports.preview',
            'imports.commit',
            'setup.manage',
            'system.update.view',
            'mr.view',
            'mr.manage',
        ]);

        $permissions->each(fn (string $name) => Permission::query()->firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));

        $role = Role::query()->firstOrCreate([
            'name' => 'Owner',
            'guard_name' => 'web',
        ]);

        $role->syncPermissions($permissions->all());
        $admin->assignRole($role);
    }

    private function seedOperatingDefaults(int $companyId, int $userId): void
    {
        Unit::query()->firstOrCreate(
            ['company_id' => $companyId, 'name' => 'Piece'],
            ['code' => 'PCS', 'type' => 'both', 'factor' => 1, 'created_by' => $userId, 'updated_by' => $userId],
        );

        ProductCategory::query()->firstOrCreate(
            ['company_id' => $companyId, 'name' => 'Medicine'],
            ['code' => 'MED', 'created_by' => $userId, 'updated_by' => $userId],
        );
    }
}
