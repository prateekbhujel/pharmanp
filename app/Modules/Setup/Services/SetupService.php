<?php

namespace App\Modules\Setup\Services;

use App\Core\Services\InstallationService;
use App\Core\Support\AssetUrl;
use App\Models\Setting;
use App\Models\User;
use App\Modules\Inventory\Models\Company;
use App\Modules\Inventory\Models\ProductCategory;
use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Models\Unit;
use App\Modules\MR\Models\Branch;
use App\Modules\Setup\Models\FiscalYear;
use App\Modules\Setup\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class SetupService
{
    public function __construct(
        private readonly InstallationService $installation,
        private readonly AccessControlService $accessControl,
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
            $storeData = $data['store'] ?? [];
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
                'name' => $storeData['name'] ?? 'Main Store',
                'code' => 'MAIN',
                'phone' => $storeData['phone'] ?? null,
                'address' => $storeData['address'] ?? null,
                'is_default' => true,
                'is_active' => true,
            ]);

            $branch = Branch::query()->create([
                'tenant_id' => $tenant->id,
                'company_id' => $company->id,
                'store_id' => $store->id,
                'name' => $store->name,
                'code' => $store->code ?: 'HQ',
                'type' => 'hq',
                'is_active' => true,
            ]);

            $admin = User::query()->create([
                'tenant_id' => $tenant->id,
                'company_id' => $company->id,
                'store_id' => $store->id,
                'branch_id' => $branch->id,
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
        $this->accessControl->syncPermissions();

        $role = Role::query()->firstOrCreate([
            'name' => 'Owner',
            'guard_name' => 'web',
        ]);

        $role->syncPermissions($this->accessControl->permissionNames());
        $admin->assignRole($role);

        Role::query()->firstOrCreate([
            'name' => 'MR',
            'guard_name' => 'web',
        ])->syncPermissions([
            'dashboard.view',
            'mr.view',
            'mr.visits.manage',
        ]);
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
        $branding = $data['branding'];
        $logoUrl = $this->storeBrandAsset(
            $branding['logo_file'] ?? null,
            $branding['logo_url'] ?? null,
        );
        $sidebarLogoUrl = $this->storeBrandAsset(
            $branding['sidebar_logo_file'] ?? null,
            $branding['sidebar_logo_url'] ?? null,
        ) ?? $logoUrl;
        $appIconUrl = $this->storeBrandAsset(
            $branding['app_icon_file'] ?? null,
            $branding['app_icon_url'] ?? null,
        );
        $faviconUrl = $this->storeBrandAsset(
            $branding['favicon_file'] ?? null,
            $branding['favicon_url'] ?? null,
        ) ?? $appIconUrl;

        Setting::putValue('app.branding', [
            'app_name' => $branding['app_name'],
            'logo_url' => $logoUrl,
            'sidebar_logo_url' => $sidebarLogoUrl,
            'app_icon_url' => $appIconUrl,
            'favicon_url' => $faviconUrl,
            'accent_color' => $branding['accent_color'] ?? '#0f766e',
            'layout' => 'vertical',
            'sidebar_default_collapsed' => (bool) ($branding['sidebar_default_collapsed'] ?? true),
            'show_breadcrumbs' => (bool) ($branding['show_breadcrumbs'] ?? true),
            'country_code' => $branding['country_code'] ?? 'NP',
            'currency_symbol' => $branding['currency_symbol'] ?? 'Rs.',
            'calendar_type' => $branding['calendar_type'] ?? 'bs',
            'tenant_id' => $tenantId,
            'company_id' => $companyId,
            'store_id' => $storeId,
        ]);
    }

    private function storeBrandAsset(mixed $file, ?string $path = null): ?string
    {
        if ($file instanceof UploadedFile) {
            return AssetUrl::publicStorage($file->store('settings/branding', 'public'));
        }

        return filled($path) ? $path : null;
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
