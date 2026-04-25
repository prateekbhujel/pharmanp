<?php

namespace App\Modules\Setup\Services;

use App\Core\Services\InstallationService;
use App\Models\User;
use App\Models\Setting;
use App\Modules\Inventory\Models\Company;
use App\Modules\Inventory\Models\ProductCategory;
use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Setup\Models\FiscalYear;
use App\Modules\Setup\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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
            $tenant = Tenant::query()->create([
                'name' => $data['company']['name'],
                'slug' => $this->uniqueTenantSlug($data['company']['name']),
                'status' => 'active',
                'plan_code' => 'starter',
            ]);

            $company = Company::query()->create([
                'tenant_id' => $tenant->id,
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
                'tenant_id' => $tenant->id,
                'company_id' => $company->id,
                'name' => $data['store']['name'],
                'code' => 'MAIN',
                'phone' => $data['store']['phone'] ?? null,
                'address' => $data['store']['address'] ?? null,
                'is_default' => true,
                'is_active' => true,
            ]);

            $admin = User::query()->create([
                'tenant_id' => $tenant->id,
                'company_id' => $company->id,
                'store_id' => $store->id,
                'name' => $data['admin']['name'],
                'email' => $data['admin']['email'],
                'password' => Hash::make($data['admin']['password']),
                'is_owner' => true,
                'is_active' => true,
            ]);

            $this->seedPermissions($admin);
            $this->seedOperatingDefaults($tenant->id, $company->id, $admin->id);
            $this->createFiscalYear($data, $tenant->id, $company->id, $admin->id);
            $this->storeBranding($data, $tenant->id, $company->id, $store->id);
            $this->installation->markInstalled([
                'tenant_id' => $tenant->id,
                'company_id' => $company->id,
                'store_id' => $store->id,
            ]);

            return [
                'company' => $company,
                'store' => $store,
                'admin' => $admin,
                'tenant' => $tenant,
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
            'sales.pos.use',
            'purchase.entries.view',
            'purchase.entries.create',
            'purchase.orders.manage',
            'purchase.returns.manage',
            'accounting.vouchers.view',
            'accounting.vouchers.create',
            'accounting.books.view',
            'reports.view',
            'settings.manage',
            'users.manage',
            'roles.manage',
            'imports.preview',
            'imports.commit',
            'exports.download',
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

    private function seedOperatingDefaults(int $tenantId, int $companyId, int $userId): void
    {
        Unit::query()->firstOrCreate(
            ['company_id' => $companyId, 'name' => 'Piece'],
            ['tenant_id' => $tenantId, 'code' => 'PCS', 'type' => 'both', 'factor' => 1, 'created_by' => $userId, 'updated_by' => $userId],
        );

        ProductCategory::query()->firstOrCreate(
            ['company_id' => $companyId, 'name' => 'Medicine'],
            ['tenant_id' => $tenantId, 'code' => 'MED', 'created_by' => $userId, 'updated_by' => $userId],
        );
    }

    private function createFiscalYear(array $data, int $tenantId, int $companyId, int $userId): void
    {
        FiscalYear::query()->create([
            'tenant_id' => $tenantId,
            'company_id' => $companyId,
            'name' => $data['fiscal_year']['name'],
            'starts_on' => $data['fiscal_year']['starts_on'],
            'ends_on' => $data['fiscal_year']['ends_on'],
            'is_current' => true,
            'status' => 'open',
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    private function storeBranding(array $data, int $tenantId, int $companyId, int $storeId): void
    {
        Setting::putValue('app.branding', [
            'app_name' => $data['branding']['app_name'],
            'logo_url' => $data['branding']['logo_url'] ?? null,
            'sidebar_logo_url' => $data['branding']['sidebar_logo_url'] ?? null,
            'app_icon_url' => $data['branding']['app_icon_url'] ?? null,
            'favicon_url' => $data['branding']['favicon_url'] ?? null,
            'accent_color' => $data['branding']['accent_color'] ?? '#0f766e',
            'layout' => $data['branding']['layout'],
            'sidebar_default_collapsed' => (bool) ($data['branding']['sidebar_default_collapsed'] ?? false),
            'tenant_id' => $tenantId,
            'company_id' => $companyId,
            'store_id' => $storeId,
        ]);
    }

    private function uniqueTenantSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'tenant';
        $slug = $base;
        $counter = 2;

        while (Tenant::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }
}
