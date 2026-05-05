<?php

namespace Tests\Feature\Security;

use App\Core\Services\InstallationService;
use App\Models\Setting;
use App\Models\User;
use App\Modules\Inventory\Models\Company;
use App\Modules\Inventory\Models\Product;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Setup\Models\FiscalYear;
use App\Modules\Setup\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Bypass installation check
        Setting::create([
            'key' => InstallationService::INSTALLED_KEY,
            'value' => ['installed' => true],
        ]);
    }

    public function test_tenant_a_cannot_view_tenant_b_product(): void
    {
        $tenantA = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a']);
        $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b']);

        $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
        $productB = Product::create([
            'tenant_id' => $tenantB->id,
            'name' => 'Secret Product B',
            'sku' => 'SKU-B',
        ]);

        $this->actingAs($userA);

        $response = $this->getJson("/api/v1/inventory/products/{$productB->id}");
        $response->assertStatus(404);
    }

    public function test_tenant_a_cannot_update_tenant_b_product(): void
    {
        $tenantA = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a']);
        $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b']);

        $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
        $productB = Product::create([
            'tenant_id' => $tenantB->id,
            'name' => 'Secret Product B',
            'sku' => 'SKU-B',
        ]);

        $this->actingAs($userA);

        $response = $this->putJson("/api/v1/inventory/products/{$productB->id}", [
            'name' => 'Hacked Name',
        ]);

        $response->assertStatus(404);
        $this->assertEquals('Secret Product B', $productB->fresh()->name);
    }

    public function test_tenant_a_cannot_view_tenant_b_sales_invoice(): void
    {
        $tenantA = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a']);
        $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b']);

        $userA = User::factory()->create(['tenant_id' => $tenantA->id]);

        $invoiceB = SalesInvoice::withoutGlobalScopes()->create([
            'tenant_id' => $tenantB->id,
            'invoice_no' => 'INV-B-001',
            'invoice_date' => now()->toDateString(),
            'grand_total' => 1000,
            'status' => 'confirmed',
        ]);

        $this->actingAs($userA);

        $response = $this->getJson("/api/v1/sales/invoices/{$invoiceB->id}");
        $response->assertStatus(404);
    }

    public function test_user_only_sees_invoices_from_active_fiscal_year(): void
    {
        $tenant = Tenant::create(['name' => 'My Tenant', 'slug' => 'my-tenant']);
        $company = Company::create(['tenant_id' => $tenant->id, 'name' => 'My Company']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'company_id' => $company->id]);

        $fy2080 = FiscalYear::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'name' => '2080/81',
            'starts_on' => '2023-07-17',
            'ends_on' => '2024-07-15',
            'is_current' => false,
        ]);

        $fy2081 = FiscalYear::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'name' => '2081/82',
            'starts_on' => '2024-07-16',
            'ends_on' => '2025-07-15',
            'is_current' => true,
        ]);

        // Invoice in old FY
        SalesInvoice::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'fiscal_year_id' => $fy2080->id,
            'invoice_no' => 'OLD-001',
            'invoice_date' => '2023-10-01',
            'grand_total' => 500,
            'status' => 'confirmed',
        ]);

        // Invoice in current FY
        SalesInvoice::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'fiscal_year_id' => $fy2081->id,
            'invoice_no' => 'NEW-001',
            'invoice_date' => '2024-10-01',
            'grand_total' => 700,
            'status' => 'confirmed',
        ]);

        $this->actingAs($user);

        $response = $this->getJson('/api/v1/sales/invoices');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertEquals('NEW-001', $data[0]['invoice_no']);
    }
}
