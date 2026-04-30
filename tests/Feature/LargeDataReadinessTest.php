<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LargeDataReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_summary_is_scoped_to_authenticated_tenant(): void
    {
        Setting::putValue('app.installed', ['installed' => true]);

        $tenantA = DB::table('tenants')->insertGetId(['name' => 'Tenant A', 'slug' => 'tenant-a', 'created_at' => now(), 'updated_at' => now()]);
        $tenantB = DB::table('tenants')->insertGetId(['name' => 'Tenant B', 'slug' => 'tenant-b', 'created_at' => now(), 'updated_at' => now()]);
        $companyA = DB::table('companies')->insertGetId(['tenant_id' => $tenantA, 'name' => 'Tenant A Pharmacy', 'created_at' => now(), 'updated_at' => now()]);
        $companyB = DB::table('companies')->insertGetId(['tenant_id' => $tenantB, 'name' => 'Tenant B Pharmacy', 'created_at' => now(), 'updated_at' => now()]);

        $userA = User::factory()->create(['tenant_id' => $tenantA, 'company_id' => $companyA, 'is_owner' => true]);
        $customerA = DB::table('customers')->insertGetId(['tenant_id' => $tenantA, 'company_id' => $companyA, 'name' => 'Tenant A Customer', 'current_balance' => 25, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('customers')->insert(['tenant_id' => $tenantB, 'company_id' => $companyB, 'name' => 'Tenant B Customer', 'current_balance' => 999, 'created_at' => now(), 'updated_at' => now()]);

        DB::table('sales_invoices')->insert([
            ['tenant_id' => $tenantA, 'company_id' => $companyA, 'customer_id' => $customerA, 'invoice_no' => 'TA-001', 'invoice_date' => today()->toDateString(), 'sale_type' => 'retail', 'status' => 'confirmed', 'payment_status' => 'paid', 'grand_total' => 150, 'paid_amount' => 150, 'created_at' => now(), 'updated_at' => now()],
            ['tenant_id' => $tenantB, 'company_id' => $companyB, 'customer_id' => null, 'invoice_no' => 'TB-001', 'invoice_date' => today()->toDateString(), 'sale_type' => 'retail', 'status' => 'confirmed', 'payment_status' => 'paid', 'grand_total' => 900, 'paid_amount' => 900, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->actingAs($userA)
            ->getJson('/api/v1/dashboard/summary?from='.today()->toDateString().'&to='.today()->toDateString())
            ->assertOk()
            ->assertJsonPath('data.stats.period_sales', 150)
            ->assertJsonPath('data.stats.receivables', 25);
    }

    public function test_demo_load_command_creates_chunked_multi_tenant_dataset(): void
    {
        $this->artisan('pharmanp:demo-load', [
            '--profile' => 'tiny',
            '--tenants' => 2,
            '--branches' => 2,
            '--users' => 4,
            '--products' => 8,
            '--customers' => 10,
            '--suppliers' => 4,
            '--batches' => 10,
            '--purchases' => 4,
            '--sales' => 6,
            '--chunk' => 50,
            '--yes' => true,
        ])->assertSuccessful();

        $this->assertSame(2, DB::table('tenants')->count());
        $this->assertSame(6, DB::table('sales_invoices')->count());
        $this->assertGreaterThanOrEqual(6, DB::table('stock_movements')->where('movement_type', 'sales_issue')->count());
        $this->assertTrue((bool) Setting::getValue('app.installed')['installed']);
    }
}
