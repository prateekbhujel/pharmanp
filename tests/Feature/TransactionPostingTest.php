<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Inventory\Models\Company;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\ProductCategory;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Party\Models\Customer;
use App\Modules\Party\Models\Supplier;
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

    private function fixture(): array
    {
        Setting::putValue('app.installed', ['installed' => true]);
        $company = Company::query()->create(['name' => 'Fixture Pharma']);
        $unit = Unit::query()->create(['company_id' => $company->id, 'name' => 'Piece']);
        $category = ProductCategory::query()->create(['company_id' => $company->id, 'name' => 'Medicine']);
        $user = User::factory()->create(['company_id' => $company->id, 'is_owner' => true]);
        $product = Product::query()->create([
            'company_id' => $company->id,
            'unit_id' => $unit->id,
            'category_id' => $category->id,
            'name' => 'Paracetamol 500',
            'sku' => 'PCM-500',
            'formulation' => 'Tablet',
            'mrp' => 8,
            'purchase_price' => 5,
            'selling_price' => 8,
        ]);
        $supplier = Supplier::query()->create(['company_id' => $company->id, 'name' => 'Himal Supplier']);
        $customer = Customer::query()->create(['company_id' => $company->id, 'name' => 'Retail Customer']);

        return [$user, $product, $supplier, $customer];
    }
}
