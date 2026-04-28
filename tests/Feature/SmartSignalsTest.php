<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Inventory\Models\Company;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\ProductCategory;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesInvoiceItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmartSignalsTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_smart_signals_score_products_on_shared_hosting_safe_data(): void
    {
        Setting::putValue('app.installed', ['installed' => true]);

        $company = Company::query()->create(['name' => 'Signal Pharma']);
        $unit = Unit::query()->create(['company_id' => $company->id, 'name' => 'Piece']);
        $category = ProductCategory::query()->create(['company_id' => $company->id, 'name' => 'Medicine']);
        $user = User::factory()->create(['company_id' => $company->id, 'is_owner' => true]);

        foreach ([0, 4, 8, 20, 45, 90] as $index => $soldQuantity) {
            $product = Product::query()->create([
                'company_id' => $company->id,
                'unit_id' => $unit->id,
                'category_id' => $category->id,
                'name' => 'Signal Product '.$index,
                'sku' => 'SIG-'.$index,
                'formulation' => 'Tablet',
                'mrp' => 20,
                'purchase_price' => 10,
                'selling_price' => 18,
                'reorder_level' => 10,
                'is_active' => true,
            ]);

            Batch::query()->create([
                'company_id' => $company->id,
                'product_id' => $product->id,
                'batch_no' => 'SIG-B'.$index,
                'expires_at' => now()->addDays($index === 0 ? 20 : 180)->toDateString(),
                'quantity_received' => 100,
                'quantity_available' => $index === 5 ? 4 : 40,
                'purchase_price' => 10,
                'mrp' => 20,
                'is_active' => true,
            ]);

            if ($soldQuantity > 0) {
                $invoice = SalesInvoice::query()->create([
                    'company_id' => $company->id,
                    'invoice_no' => 'SIG-INV-'.$index,
                    'invoice_date' => now()->subDays(5)->toDateString(),
                    'sale_type' => 'invoice',
                    'status' => 'confirmed',
                    'payment_status' => 'paid',
                    'subtotal' => $soldQuantity * 18,
                    'grand_total' => $soldQuantity * 18,
                    'paid_amount' => $soldQuantity * 18,
                ]);

                SalesInvoiceItem::query()->create([
                    'sales_invoice_id' => $invoice->id,
                    'product_id' => $product->id,
                    'quantity' => $soldQuantity,
                    'mrp' => 20,
                    'unit_price' => 18,
                    'line_total' => $soldQuantity * 18,
                ]);
            }
        }

        $this->actingAs($user)->getJson('/api/v1/reports/smart-inventory')
            ->assertOk()
            ->assertJsonPath('summary.products_scored', 6)
            ->assertJsonPath('engine.shared_hosting_safe', true)
            ->assertJsonStructure([
                'data' => [[
                    'name',
                    'stock_on_hand',
                    'sold_90',
                    'days_cover',
                    'movement_group',
                    'reorder_signal',
                    'expiry_signal',
                    'risk_score',
                    'recommendation',
                ]],
                'summary' => ['products_scored', 'urgent_reorder', 'expiry_risk', 'rubix_groups'],
                'engine' => ['name', 'rubixml', 'shared_hosting_safe'],
            ]);
    }
}
