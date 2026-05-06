<?php

namespace Tests\Feature;

use App\Core\Services\DocumentNumberService;
use App\Models\Setting;
use App\Models\User;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Inventory\Models\Company;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesInvoiceItem;
use App\Modules\Setup\Models\FiscalYear;
use App\Modules\Setup\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::putValue('app.installed', ['installed' => true]);
    }

    protected function setupFiscalYear($tenantId, $companyId): void
    {
        FiscalYear::query()->create([
            'tenant_id' => $tenantId,
            'company_id' => $companyId,
            'name' => 'FY 2026/27',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
            'is_current' => true,
            'status' => 'active',
        ]);
    }

    public function test_batch_quantity_adjustment_creates_stock_movement(): void
    {
        $tenant = Tenant::query()->create(['name' => 'T1', 'slug' => 't1']);
        $company = Company::query()->create(['tenant_id' => $tenant->id, 'name' => 'C1']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'is_owner' => true]);

        $this->setupFiscalYear($tenant->id, $company->id);

        $unit = Unit::query()->create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'name' => 'Pcs']);
        $product = Product::query()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'unit_id' => $unit->id,
            'name' => 'P1',
            'mrp' => 10,
            'purchase_price' => 5,
            'selling_price' => 10,
        ]);

        $batch = Batch::query()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'product_id' => $product->id,
            'batch_no' => 'B1',
            'expires_at' => '2027-01-01',
            'quantity_received' => 10,
            'quantity_available' => 10,
            'purchase_price' => 5,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->putJson('/api/v1/inventory/batches/'.$batch->id, [
                'product_id' => $product->id,
                'batch_no' => 'B1',
                'expires_at' => '2027-01-01',
                'quantity_received' => 10,
                'quantity_available' => 15,
                'adjustment_reason' => 'Physical count correction',
                'purchase_price' => 5,
            ])
            ->assertOk();

        $this->assertDatabaseHas('stock_movements', [
            'batch_id' => $batch->id,
            'quantity_in' => 5,
            'movement_type' => 'manual_batch_in',
            'notes' => 'Physical count correction',
        ]);
    }

    public function test_batch_quantity_adjustment_requires_reason_and_does_not_mutate_stock_on_failure(): void
    {
        $tenant = Tenant::query()->create(['name' => 'T1', 'slug' => 't1']);
        $company = Company::query()->create(['tenant_id' => $tenant->id, 'name' => 'C1']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'is_owner' => true]);

        $this->setupFiscalYear($tenant->id, $company->id);

        $unit = Unit::query()->create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'name' => 'Pcs']);
        $product = Product::query()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'unit_id' => $unit->id,
            'name' => 'P1',
            'mrp' => 10,
            'purchase_price' => 5,
            'selling_price' => 10,
        ]);

        $batch = Batch::query()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'product_id' => $product->id,
            'batch_no' => 'B1',
            'expires_at' => '2027-01-01',
            'quantity_received' => 10,
            'quantity_available' => 10,
            'purchase_price' => 5,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->putJson('/api/v1/inventory/batches/'.$batch->id, [
                'product_id' => $product->id,
                'batch_no' => 'B1',
                'expires_at' => '2027-01-01',
                'quantity_received' => 10,
                'quantity_available' => 8,
                'purchase_price' => 5,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('adjustment_reason');

        $this->assertSame('10.000', (string) $batch->fresh()->quantity_available);
        $this->assertDatabaseMissing('stock_movements', [
            'batch_id' => $batch->id,
            'movement_type' => 'manual_batch_out',
        ]);
    }

    public function test_referenced_batch_cannot_be_deleted_only_deactivated(): void
    {
        $tenant = Tenant::query()->create(['name' => 'T1', 'slug' => 't1']);
        $company = Company::query()->create(['tenant_id' => $tenant->id, 'name' => 'C1']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'is_owner' => true]);

        $unit = Unit::query()->create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'name' => 'Pcs']);
        $product = Product::query()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'unit_id' => $unit->id,
            'name' => 'P1',
            'mrp' => 10,
            'purchase_price' => 5,
            'selling_price' => 10,
        ]);

        $batch = Batch::query()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'product_id' => $product->id,
            'batch_no' => 'B1',
            'expires_at' => '2027-01-01',
            'quantity_received' => 10,
            'quantity_available' => 10,
            'purchase_price' => 5,
            'is_active' => true,
        ]);

        $invoice = SalesInvoice::query()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'invoice_no' => 'SI-1',
            'invoice_date' => '2026-05-05',
            'sale_type' => 'retail',
            'status' => 'confirmed',
            'payment_status' => 'unpaid',
            'grand_total' => 10,
            'paid_amount' => 0,
        ]);

        SalesInvoiceItem::query()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'sales_invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'batch_id' => $batch->id,
            'quantity' => 1,
            'unit_price' => 10,
            'line_total' => 10,
        ]);

        $this->actingAs($user)
            ->deleteJson('/api/v1/inventory/batches/'.$batch->id)
            ->assertOk();

        $this->assertDatabaseHas('batches', [
            'id' => $batch->id,
            'deleted_at' => null,
            'is_active' => false,
        ]);
    }

    public function test_document_number_sequence_creates_unique_numbers(): void
    {
        $tenant = Tenant::query()->create(['name' => 'T1', 'slug' => 't1']);
        $company = Company::query()->create(['tenant_id' => $tenant->id, 'name' => 'C1']);
        $userA = User::factory()->create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'is_owner' => true]);

        $service = app(DocumentNumberService::class);

        $num1 = $service->next('sales_invoice', 'sales_invoices', now(), $userA);
        $num2 = $service->next('sales_invoice', 'sales_invoices', now(), $userA);

        $this->assertNotEquals($num1, $num2);
        $this->assertStringContainsString('SI', $num1);
        $this->assertStringContainsString(now()->format('Ymd'), $num1);
    }

    public function test_document_number_sequence_is_scoped_by_tenant_company_and_type(): void
    {
        $tenantA = Tenant::query()->create(['name' => 'T1', 'slug' => 't1']);
        $tenantB = Tenant::query()->create(['name' => 'T2', 'slug' => 't2']);
        $companyA = Company::query()->create(['tenant_id' => $tenantA->id, 'name' => 'C1']);
        $companyB = Company::query()->create(['tenant_id' => $tenantB->id, 'name' => 'C2']);
        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'company_id' => $companyA->id, 'is_owner' => true]);
        $userB = User::factory()->create(['tenant_id' => $tenantB->id, 'company_id' => $companyB->id, 'is_owner' => true]);
        $service = app(DocumentNumberService::class);

        $salesA = $service->next('sales_invoice', 'sales_invoices', now(), $userA);
        $paymentA = $service->next('payment', 'payments', now(), $userA);
        $salesB = $service->next('sales_invoice', 'sales_invoices', now(), $userB);

        $this->assertStringEndsWith('00001', $salesA);
        $this->assertStringEndsWith('00001', $paymentA);
        $this->assertStringEndsWith('00001', $salesB);
        $this->assertDatabaseHas('document_sequences', [
            'scope_key' => 'T'.$tenantA->id.'C'.$companyA->id,
            'type' => 'sales_invoice',
            'last_sequence' => 1,
        ]);
        $this->assertDatabaseHas('document_sequences', [
            'scope_key' => 'T'.$tenantB->id.'C'.$companyB->id,
            'type' => 'sales_invoice',
            'last_sequence' => 1,
        ]);
    }
}
