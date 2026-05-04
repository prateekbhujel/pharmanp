<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Inventory\Models\Company;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\Unit;
use App\Modules\MR\Models\MedicalRepresentative;
use App\Modules\Party\Models\Customer;
use App\Modules\Party\Models\Supplier;
use App\Modules\Purchase\Models\PurchaseOrder;
use App\Modules\Setup\Models\DropdownOption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionPostingTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_entry_creates_batch_and_sales_invoice_deducts_stock(): void
    {
        [$user, $product, $supplier, $customer] = $this->fixture();

        $this->actingAs($user)->postJson('/api/v1/purchases', [
            'supplier_id' => $supplier->id,
            'purchase_date' => '2026-04-25',
            'paid_amount' => 50,
            'items' => [[
                'product_id' => $product->id,
                'batch_no' => 'B-001',
                'expires_at' => '2027-04-25',
                'quantity' => 10,
                'free_quantity' => 2,
                'purchase_price' => 5,
                'mrp' => 8,
            ]],
        ])->assertCreated();

        $batch = Batch::query()->where('batch_no', 'B-001')->firstOrFail();
        $this->assertSame('12.000', (string) $batch->quantity_available);
        $this->assertDatabaseHas('purchase_items', ['product_id' => $product->id, 'batch_id' => $batch->id]);
        $this->assertDatabaseHas('stock_movements', ['movement_type' => 'purchase_receive', 'quantity_in' => 12]);

        $this->actingAs($user)->postJson('/api/v1/sales/invoices', [
            'customer_id' => $customer->id,
            'invoice_date' => '2026-04-25',
            'sale_type' => 'pos',
            'paid_amount' => 8,
            'items' => [[
                'product_id' => $product->id,
                'batch_id' => $batch->id,
                'quantity' => 2,
                'unit_price' => 8,
            ]],
        ])->assertCreated();

        $this->assertSame('10.000', (string) $batch->fresh()->quantity_available);
        $this->assertDatabaseHas('sales_invoice_items', ['product_id' => $product->id, 'batch_id' => $batch->id]);
        $this->assertDatabaseHas('stock_movements', ['movement_type' => 'sales_issue', 'quantity_out' => 2]);
        $this->assertSame('8.00', (string) $customer->fresh()->current_balance);
    }

    public function test_purchase_order_receive_creates_purchase_batch_and_stock_movement(): void
    {
        [$user, $product, $supplier] = $this->fixture();

        $orderResponse = $this->actingAs($user)->postJson('/api/v1/purchase/orders', [
            'supplier_id' => $supplier->id,
            'order_date' => '2026-04-25',
            'expected_date' => '2026-04-27',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 8,
                'unit_price' => 5,
                'discount_percent' => 0,
            ]],
        ])->assertCreated();

        $orderId = $orderResponse->json('data.id');
        $orderItemId = $orderResponse->json('data.items.0.id');

        $receiveResponse = $this->actingAs($user)->postJson('/api/v1/purchase/orders/'.$orderId.'/receive', [
            'supplier_invoice_no' => 'SUP-PO-001',
            'purchase_date' => '2026-04-26',
            'paid_amount' => 10,
            'items' => [[
                'purchase_order_item_id' => $orderItemId,
                'product_id' => $product->id,
                'batch_no' => 'PO-RCV-001',
                'expires_at' => '2027-04-26',
                'quantity' => 8,
                'free_quantity' => 1,
                'purchase_price' => 5,
                'mrp' => 8,
            ]],
        ])->assertOk();

        $purchaseId = (int) PurchaseOrder::query()
            ->whereKey($orderId)
            ->value('received_purchase_id');
        $batch = Batch::query()->where('batch_no', 'PO-RCV-001')->firstOrFail();

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $orderId,
            'status' => 'received',
            'received_purchase_id' => $purchaseId,
        ]);
        $this->assertDatabaseHas('purchases', [
            'id' => $purchaseId,
            'supplier_invoice_no' => 'SUP-PO-001',
            'payment_status' => 'partial',
        ]);
        $this->assertSame('9.000', (string) $batch->quantity_available);
        $this->assertSame('30.00', (string) $supplier->fresh()->current_balance);
        $this->assertDatabaseHas('stock_movements', [
            'movement_type' => 'purchase_receive',
            'source_type' => 'purchase',
            'source_id' => $purchaseId,
            'quantity_in' => 9,
        ]);
    }

    public function test_sales_return_posts_stock_movement_and_delete_reverses_it(): void
    {
        [$user, $product, $supplier, $customer] = $this->fixture();

        $this->actingAs($user)->postJson('/api/v1/purchases', [
            'supplier_id' => $supplier->id,
            'purchase_date' => '2026-04-25',
            'paid_amount' => 0,
            'items' => [[
                'product_id' => $product->id,
                'batch_no' => 'SR-LEDGER-001',
                'expires_at' => '2027-04-25',
                'quantity' => 10,
                'free_quantity' => 2,
                'purchase_price' => 5,
                'mrp' => 8,
            ]],
        ])->assertCreated();

        $batch = Batch::query()->where('batch_no', 'SR-LEDGER-001')->firstOrFail();

        $invoiceResponse = $this->actingAs($user)->postJson('/api/v1/sales/invoices', [
            'customer_id' => $customer->id,
            'invoice_date' => '2026-04-25',
            'sale_type' => 'pos',
            'paid_amount' => 0,
            'items' => [[
                'product_id' => $product->id,
                'batch_id' => $batch->id,
                'quantity' => 2,
                'unit_price' => 8,
            ]],
        ])->assertCreated();

        $this->assertSame('10.000', (string) $batch->fresh()->quantity_available);
        $this->assertSame('16.00', (string) $customer->fresh()->current_balance);

        $returnResponse = $this->actingAs($user)->postJson('/api/v1/sales/returns', [
            'customer_id' => $customer->id,
            'sales_invoice_id' => $invoiceResponse->json('data.id'),
            'return_date' => '2026-04-26',
            'reason' => 'Customer returned item',
            'items' => [[
                'sales_invoice_item_id' => $invoiceResponse->json('data.items.0.id'),
                'product_id' => $product->id,
                'batch_id' => $batch->id,
                'quantity' => 1,
                'unit_price' => 8,
            ]],
        ])->assertOk();

        $this->assertSame('11.000', (string) $batch->fresh()->quantity_available);
        $this->assertSame('8.00', (string) $customer->fresh()->current_balance);
        $this->assertDatabaseHas('stock_movements', ['movement_type' => 'sales_return_in', 'quantity_in' => 1]);
        $this->assertDatabaseHas('account_transactions', [
            'source_type' => 'sales_return',
            'source_id' => $returnResponse->json('data.id'),
            'account_type' => 'receivable',
            'debit' => 0,
            'credit' => 8,
        ]);

        $this->actingAs($user)->deleteJson('/api/v1/sales/returns/'.$returnResponse->json('data.id'))->assertOk();

        $this->assertSame('10.000', (string) $batch->fresh()->quantity_available);
        $this->assertSame('16.00', (string) $customer->fresh()->current_balance);
        $this->assertDatabaseHas('stock_movements', ['movement_type' => 'sales_return_reverse', 'quantity_out' => 1]);
        $this->assertDatabaseMissing('account_transactions', [
            'source_type' => 'sales_return',
            'source_id' => $returnResponse->json('data.id'),
        ]);
    }

    public function test_sales_invoice_payment_update_refreshes_customer_balance_and_ledger(): void
    {
        [$user, $product, $supplier, $customer] = $this->fixture();

        $this->actingAs($user)->postJson('/api/v1/purchases', [
            'supplier_id' => $supplier->id,
            'purchase_date' => '2026-04-25',
            'paid_amount' => 0,
            'items' => [[
                'product_id' => $product->id,
                'batch_no' => 'SALE-PAY-001',
                'expires_at' => '2027-04-25',
                'quantity' => 10,
                'purchase_price' => 5,
                'mrp' => 8,
            ]],
        ])->assertCreated();

        $invoiceResponse = $this->actingAs($user)->postJson('/api/v1/sales/invoices', [
            'customer_id' => $customer->id,
            'invoice_date' => '2026-04-25',
            'sale_type' => 'pos',
            'paid_amount' => 0,
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 2,
                'unit_price' => 8,
            ]],
        ])->assertCreated();

        $invoiceId = $invoiceResponse->json('data.id');
        $this->assertSame('16.00', (string) $customer->fresh()->current_balance);

        $this->actingAs($user)->patchJson('/api/v1/sales/invoices/'.$invoiceId.'/payment', [
            'paid_amount' => 10,
        ])
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'partial')
            ->assertJsonPath('data.paid_amount', 10);

        $this->assertSame('6.00', (string) $customer->fresh()->current_balance);
        $this->assertDatabaseHas('account_transactions', [
            'source_type' => 'sales_invoice',
            'source_id' => $invoiceId,
            'account_type' => 'cash',
            'debit' => 10,
            'credit' => 0,
        ]);
        $this->assertDatabaseHas('account_transactions', [
            'source_type' => 'sales_invoice',
            'source_id' => $invoiceId,
            'account_type' => 'receivable',
            'debit' => 6,
            'credit' => 0,
        ]);

        $this->actingAs($user)->getJson('/api/v1/customers/'.$customer->id.'/ledger')
            ->assertOk()
            ->assertJsonPath('summary.total_paid', 10)
            ->assertJsonPath('summary.balance', 6);
    }

    public function test_unbalanced_voucher_is_rejected(): void
    {
        [$user] = $this->fixture();

        $this->actingAs($user)->postJson('/api/v1/accounting/vouchers', [
            'voucher_date' => '2026-04-25',
            'voucher_type' => 'journal',
            'entries' => [
                ['account_type' => 'cash', 'entry_type' => 'debit', 'amount' => 100],
                ['account_type' => 'sales', 'entry_type' => 'credit', 'amount' => 90],
            ],
        ])->assertUnprocessable();
    }

    public function test_purchase_return_deducts_stock_and_delete_restores_it(): void
    {
        [$user, $product, $supplier] = $this->fixture();

        $purchaseResponse = $this->actingAs($user)->postJson('/api/v1/purchases', [
            'supplier_id' => $supplier->id,
            'purchase_date' => '2026-04-25',
            'paid_amount' => 0,
            'items' => [[
                'product_id' => $product->id,
                'batch_no' => 'RET-001',
                'expires_at' => '2027-04-25',
                'quantity' => 10,
                'free_quantity' => 2,
                'purchase_price' => 5,
                'mrp' => 8,
            ]],
        ])->assertCreated();

        $purchaseId = $purchaseResponse->json('data.id');
        $batch = Batch::query()->where('batch_no', 'RET-001')->firstOrFail();
        $purchaseItemId = $purchaseResponse->json('data.items.0.id');

        $returnResponse = $this->actingAs($user)->postJson('/api/v1/purchase/returns', [
            'supplier_id' => $supplier->id,
            'purchase_id' => $purchaseId,
            'return_date' => '2026-04-26',
            'items' => [[
                'purchase_item_id' => $purchaseItemId,
                'product_id' => $product->id,
                'batch_id' => $batch->id,
                'return_qty' => 2,
                'rate' => 5,
                'discount_percent' => 0,
            ]],
        ])->assertCreated();

        $this->assertSame('10.000', (string) $batch->fresh()->quantity_available);
        $this->assertSame('40.00', (string) $supplier->fresh()->current_balance);
        $this->assertDatabaseHas('stock_movements', ['movement_type' => 'purchase_return_out', 'quantity_out' => 2]);
        $this->assertDatabaseHas('purchase_return_items', ['product_id' => $product->id, 'batch_id' => $batch->id]);

        $this->actingAs($user)->deleteJson('/api/v1/purchase/returns/'.$returnResponse->json('data.id'))->assertOk();

        $this->assertSame('12.000', (string) $batch->fresh()->quantity_available);
        $this->assertSame('50.00', (string) $supplier->fresh()->current_balance);
    }

    public function test_payment_delete_soft_deletes_and_reverses_allocated_bill_balance(): void
    {
        [$user, $product, $supplier] = $this->fixture();
        $paymentMode = DropdownOption::query()->firstOrCreate(
            ['alias' => 'payment_mode', 'name' => 'Cash'],
            ['data' => 'cash', 'status' => true],
        );

        $purchaseResponse = $this->actingAs($user)->postJson('/api/v1/purchases', [
            'supplier_id' => $supplier->id,
            'purchase_date' => '2026-04-25',
            'paid_amount' => 0,
            'items' => [[
                'product_id' => $product->id,
                'batch_no' => 'PAY-DEL-001',
                'expires_at' => '2027-04-25',
                'quantity' => 10,
                'purchase_price' => 5,
                'mrp' => 8,
            ]],
        ])->assertCreated();

        $purchaseId = $purchaseResponse->json('data.id');

        $paymentResponse = $this->actingAs($user)->postJson('/api/v1/accounting/payments', [
            'direction' => 'out',
            'party_type' => 'supplier',
            'party_id' => $supplier->id,
            'payment_date' => '2026-04-26',
            'amount' => 20,
            'payment_mode_id' => $paymentMode->id,
            'allocations' => [[
                'bill_id' => $purchaseId,
                'bill_type' => 'purchase',
                'allocated_amount' => 20,
            ]],
        ])->assertOk();

        $this->assertDatabaseHas('purchases', ['id' => $purchaseId, 'paid_amount' => 20, 'payment_status' => 'partial']);
        $this->assertSame('30.00', (string) $supplier->fresh()->current_balance);

        $paymentId = $paymentResponse->json('data.id');
        $this->actingAs($user)->deleteJson('/api/v1/accounting/payments/'.$paymentId)->assertOk();

        $this->assertSoftDeleted('payments', ['id' => $paymentId]);
        $this->assertDatabaseHas('purchases', ['id' => $purchaseId, 'paid_amount' => 0, 'payment_status' => 'unpaid']);
        $this->assertSame('50.00', (string) $supplier->fresh()->current_balance);

        $this->actingAs($user)
            ->getJson('/api/v1/accounting/payments?deleted=1')
            ->assertOk()
            ->assertJsonPath('data.0.id', $paymentId);
    }

    public function test_mr_visit_response_hides_raw_coordinates_and_supports_date_filters(): void
    {
        [$user, $product, $supplier, $customer] = $this->fixture();

        $representative = MedicalRepresentative::query()->create([
            'company_id' => $user->company_id,
            'name' => 'Pratik Bhujel',
            'employee_code' => 'MR-001',
            'is_active' => true,
        ]);

        $createResponse = $this->actingAs($user)->postJson('/api/v1/mr/visits', [
            'medical_representative_id' => $representative->id,
            'customer_id' => $customer->id,
            'visit_date' => '2026-04-26',
            'visit_time' => '10:30',
            'status' => 'visited',
            'location_name' => 'Maharajgunj, Kathmandu',
            'latitude' => 27.7395000,
            'longitude' => 85.3360000,
            'order_value' => 1200,
        ])->assertCreated();

        $visit = $createResponse->json('data');
        $this->assertArrayNotHasKey('latitude', $visit);
        $this->assertArrayNotHasKey('longitude', $visit);
        $this->assertTrue($visit['has_coordinates']);
        $this->assertStringContainsString('openstreetmap.org', $visit['map_view_url']);

        $this->actingAs($user)
            ->getJson('/api/v1/mr/visits?from=2026-04-01&to=2026-04-30')
            ->assertOk()
            ->assertJsonPath('data.0.location_name', 'Maharajgunj, Kathmandu');

        $this->actingAs($user)
            ->getJson('/api/v1/mr/visits?from=2026-05-01&to=2026-05-30')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    private function fixture(): array
    {
        Setting::putValue('app.installed', ['installed' => true]);
        $company = Company::query()->create(['name' => 'Fixture Pharma']);
        $unit = Unit::query()->create(['company_id' => $company->id, 'name' => 'Piece']);
        $user = User::factory()->create(['company_id' => $company->id, 'is_owner' => true]);
        $product = Product::query()->create([
            'company_id' => $company->id,
            'unit_id' => $unit->id,
            'name' => 'Paracetamol 500',
            'sku' => 'PCM-500',
            'mrp' => 8,
            'purchase_price' => 5,
            'selling_price' => 8,
        ]);
        $supplier = Supplier::query()->create(['company_id' => $company->id, 'name' => 'Himal Supplier']);
        $customer = Customer::query()->create(['company_id' => $company->id, 'name' => 'Retail Customer']);

        return [$user, $product, $supplier, $customer];
    }
}
