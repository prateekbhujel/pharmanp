<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Inventory\Models\Company;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Party\Models\Customer;
use App\Modules\Party\Models\Supplier;
use App\Modules\Purchase\Models\PurchaseReturn;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesReturn;
use App\Modules\Setup\Models\DropdownOption;
use App\Modules\Setup\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_from_one_tenant_cannot_update_or_delete_another_tenants_product(): void
    {
        [$tenantA, $companyA] = $this->tenantCompany('tenant-a');
        [$tenantB, $companyB] = $this->tenantCompany('tenant-b');
        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'company_id' => $companyA->id, 'is_owner' => true]);
        $productB = $this->productFor($tenantB->id, $companyB->id);

        $payload = [
            'company_id' => $companyB->id,
            'unit_id' => $productB->unit_id,
            'name' => 'Cross Tenant Product',
            'mrp' => 20,
            'purchase_price' => 12,
            'selling_price' => 18,
            'reorder_level' => 10,
            'is_active' => true,
        ];

        $this->actingAs($userA)
            ->putJson('/api/v1/inventory/products/'.$productB->id, $payload)
            ->assertNotFound();

        $this->actingAs($userA)
            ->deleteJson('/api/v1/inventory/products/'.$productB->id)
            ->assertNotFound();

        $this->assertDatabaseHas('products', [
            'id' => $productB->id,
            'tenant_id' => $tenantB->id,
            'deleted_at' => null,
        ]);
    }

    public function test_user_from_one_tenant_cannot_sell_another_tenants_product_or_batch(): void
    {
        [$tenantA, $companyA] = $this->tenantCompany('tenant-a');
        [$tenantB, $companyB] = $this->tenantCompany('tenant-b');
        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'company_id' => $companyA->id, 'is_owner' => true]);
        $customerA = Customer::query()->create(['tenant_id' => $tenantA->id, 'company_id' => $companyA->id, 'name' => 'Tenant A Customer']);
        $productB = $this->productFor($tenantB->id, $companyB->id);
        $batchB = Batch::query()->create([
            'tenant_id' => $tenantB->id,
            'company_id' => $companyB->id,
            'product_id' => $productB->id,
            'batch_no' => 'TB-BATCH-001',
            'expires_at' => '2027-05-05',
            'quantity_received' => 10,
            'quantity_available' => 10,
            'purchase_price' => 10,
            'mrp' => 20,
            'is_active' => true,
        ]);

        $this->actingAs($userA)
            ->postJson('/api/v1/sales/invoices', [
                'customer_id' => $customerA->id,
                'invoice_date' => '2026-05-05',
                'sale_type' => 'pos',
                'paid_amount' => 0,
                'items' => [[
                    'product_id' => $productB->id,
                    'batch_id' => $batchB->id,
                    'quantity' => 1,
                    'unit_price' => 20,
                ]],
            ])
            ->assertNotFound();

        $this->assertSame('10.000', (string) $batchB->fresh()->quantity_available);
        $this->assertDatabaseMissing('sales_invoice_items', ['product_id' => $productB->id]);
    }

    public function test_user_from_one_tenant_cannot_purchase_against_another_tenants_product_or_supplier(): void
    {
        [$tenantA, $companyA] = $this->tenantCompany('tenant-a');
        [$tenantB, $companyB] = $this->tenantCompany('tenant-b');
        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'company_id' => $companyA->id, 'is_owner' => true]);
        $supplierB = Supplier::query()->create(['tenant_id' => $tenantB->id, 'company_id' => $companyB->id, 'name' => 'Tenant B Supplier']);
        $productB = $this->productFor($tenantB->id, $companyB->id);

        $this->actingAs($userA)
            ->postJson('/api/v1/purchases', [
                'supplier_id' => $supplierB->id,
                'purchase_date' => '2026-05-05',
                'paid_amount' => 0,
                'items' => [[
                    'product_id' => $productB->id,
                    'batch_no' => 'TB-PUR-001',
                    'expires_at' => '2027-05-05',
                    'quantity' => 5,
                    'purchase_price' => 10,
                    'mrp' => 20,
                ]],
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('purchase_items', ['product_id' => $productB->id]);
        $this->assertSame('0.00', (string) $supplierB->fresh()->current_balance);
    }

    public function test_user_from_one_tenant_cannot_print_another_tenants_invoice(): void
    {
        [$tenantA, $companyA] = $this->tenantCompany('tenant-a');
        [$tenantB, $companyB] = $this->tenantCompany('tenant-b');
        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'company_id' => $companyA->id, 'is_owner' => true]);
        $invoiceB = SalesInvoice::query()->create([
            'tenant_id' => $tenantB->id,
            'company_id' => $companyB->id,
            'invoice_no' => 'TB-SI-001',
            'invoice_date' => '2026-05-05',
            'sale_type' => 'retail',
            'status' => 'confirmed',
            'payment_status' => 'unpaid',
            'grand_total' => 100,
            'paid_amount' => 0,
        ]);

        $this->actingAs($userA)
            ->get('/sales/invoices/'.$invoiceB->id.'/print')
            ->assertNotFound();
    }

    public function test_user_from_one_tenant_cannot_allocate_payment_to_another_tenants_bill(): void
    {
        [$tenantA, $companyA] = $this->tenantCompany('tenant-a');
        [$tenantB, $companyB] = $this->tenantCompany('tenant-b');
        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'company_id' => $companyA->id, 'is_owner' => true]);
        $customerA = Customer::query()->create(['tenant_id' => $tenantA->id, 'company_id' => $companyA->id, 'name' => 'Tenant A Customer']);
        $paymentMode = DropdownOption::query()->create(['alias' => 'payment_mode', 'name' => 'Cash', 'data' => 'cash', 'status' => true]);
        $invoiceB = SalesInvoice::query()->create([
            'tenant_id' => $tenantB->id,
            'company_id' => $companyB->id,
            'customer_id' => $customerA->id,
            'invoice_no' => 'TB-SI-ALLOC',
            'invoice_date' => '2026-05-05',
            'sale_type' => 'retail',
            'status' => 'confirmed',
            'payment_status' => 'unpaid',
            'grand_total' => 100,
            'paid_amount' => 0,
        ]);

        $this->actingAs($userA)
            ->postJson('/api/v1/accounting/payments', [
                'direction' => 'in',
                'party_type' => 'customer',
                'party_id' => $customerA->id,
                'payment_date' => '2026-05-05',
                'amount' => 50,
                'payment_mode_id' => $paymentMode->id,
                'allocations' => [[
                    'bill_id' => $invoiceB->id,
                    'bill_type' => 'sales_invoice',
                    'allocated_amount' => 50,
                ]],
            ])
            ->assertNotFound();

        $this->assertSame('0.00', (string) $invoiceB->fresh()->paid_amount);
        $this->assertDatabaseMissing('payment_bill_allocations', ['bill_id' => $invoiceB->id]);
    }

    public function test_user_from_one_tenant_cannot_print_another_tenants_returns(): void
    {
        [$tenantA, $companyA] = $this->tenantCompany('tenant-a');
        [$tenantB, $companyB] = $this->tenantCompany('tenant-b');
        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'company_id' => $companyA->id, 'is_owner' => true]);
        $supplierB = Supplier::query()->create(['tenant_id' => $tenantB->id, 'company_id' => $companyB->id, 'name' => 'Tenant B Supplier']);
        $customerB = Customer::query()->create(['tenant_id' => $tenantB->id, 'company_id' => $companyB->id, 'name' => 'Tenant B Customer']);

        $purchaseReturnB = PurchaseReturn::query()->create([
            'tenant_id' => $tenantB->id,
            'company_id' => $companyB->id,
            'supplier_id' => $supplierB->id,
            'return_no' => 'TB-PRN-001',
            'return_date' => '2026-05-05',
            'return_type' => 'regular',
            'status' => 'posted',
            'grand_total' => 100,
        ]);

        $salesReturnB = SalesReturn::query()->create([
            'tenant_id' => $tenantB->id,
            'company_id' => $companyB->id,
            'customer_id' => $customerB->id,
            'return_no' => 'TB-SR-001',
            'return_date' => '2026-05-05',
            'return_type' => 'regular',
            'status' => 'confirmed',
            'total_amount' => 100,
        ]);

        $this->actingAs($userA)
            ->get('/purchase-returns/'.$purchaseReturnB->id.'/print')
            ->assertNotFound();

        $this->actingAs($userA)
            ->get('/sales/returns/'.$salesReturnB->id.'/print')
            ->assertNotFound();
    }

    public function test_user_from_one_tenant_cannot_read_another_tenants_customer_ledger(): void
    {
        [$tenantA, $companyA] = $this->tenantCompany('tenant-a');
        [$tenantB, $companyB] = $this->tenantCompany('tenant-b');
        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'company_id' => $companyA->id, 'is_owner' => true]);
        $customerB = Customer::query()->create(['tenant_id' => $tenantB->id, 'company_id' => $companyB->id, 'name' => 'Tenant B Customer']);

        $this->actingAs($userA)
            ->getJson('/api/v1/customers/'.$customerB->id.'/ledger')
            ->assertNotFound();

        $this->actingAs($userA)
            ->get('/customers/'.$customerB->id.'/ledger/print')
            ->assertNotFound();
    }

    public function test_user_from_one_tenant_cannot_create_returns_with_another_tenants_stock_records(): void
    {
        [$tenantA, $companyA] = $this->tenantCompany('tenant-a');
        [$tenantB, $companyB] = $this->tenantCompany('tenant-b');
        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'company_id' => $companyA->id, 'is_owner' => true]);
        $customerA = Customer::query()->create(['tenant_id' => $tenantA->id, 'company_id' => $companyA->id, 'name' => 'Tenant A Customer']);
        $supplierB = Supplier::query()->create(['tenant_id' => $tenantB->id, 'company_id' => $companyB->id, 'name' => 'Tenant B Supplier']);
        $productB = $this->productFor($tenantB->id, $companyB->id);
        $batchB = Batch::query()->create([
            'tenant_id' => $tenantB->id,
            'company_id' => $companyB->id,
            'supplier_id' => $supplierB->id,
            'product_id' => $productB->id,
            'batch_no' => 'TB-RET-BATCH-001',
            'expires_at' => '2027-05-05',
            'quantity_received' => 10,
            'quantity_available' => 10,
            'purchase_price' => 10,
            'mrp' => 20,
            'is_active' => true,
        ]);

        $this->actingAs($userA)
            ->postJson('/api/v1/purchase/returns', [
                'supplier_id' => $supplierB->id,
                'return_date' => '2026-05-05',
                'return_type' => 'regular',
                'items' => [[
                    'product_id' => $productB->id,
                    'batch_id' => $batchB->id,
                    'return_qty' => 1,
                    'rate' => 10,
                ]],
            ])
            ->assertNotFound();

        $this->actingAs($userA)
            ->postJson('/api/v1/sales/returns', [
                'customer_id' => $customerA->id,
                'return_date' => '2026-05-05',
                'return_type' => 'regular',
                'items' => [[
                    'product_id' => $productB->id,
                    'batch_id' => $batchB->id,
                    'quantity' => 1,
                    'unit_price' => 20,
                ]],
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('purchase_return_items', ['product_id' => $productB->id]);
        $this->assertDatabaseMissing('sales_return_items', ['product_id' => $productB->id]);
        $this->assertSame('10.000', (string) $batchB->fresh()->quantity_available);
    }

    private function tenantCompany(string $slug): array
    {
        Setting::putValue('app.installed', ['installed' => true]);

        $tenant = Tenant::query()->create([
            'name' => str($slug)->replace('-', ' ')->title()->toString(),
            'slug' => $slug,
        ]);
        $company = Company::query()->create([
            'tenant_id' => $tenant->id,
            'name' => str($slug)->replace('-', ' ')->title()->append(' Pharmacy')->toString(),
        ]);

        return [$tenant, $company];
    }

    private function productFor(int $tenantId, int $companyId): Product
    {
        $unit = Unit::query()->create([
            'tenant_id' => $tenantId,
            'company_id' => $companyId,
            'name' => 'Piece',
        ]);

        return Product::query()->create([
            'tenant_id' => $tenantId,
            'company_id' => $companyId,
            'unit_id' => $unit->id,
            'name' => 'Tenant Product',
            'sku' => 'TP-'.$tenantId.'-'.$companyId,
            'mrp' => 20,
            'purchase_price' => 10,
            'selling_price' => 20,
            'is_active' => true,
        ]);
    }
}
