<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Modules\Inventory\Models\Company;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\ProductCategory;
use App\Modules\Inventory\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_update_list_and_soft_delete_product(): void
    {
        Setting::putValue('app.installed', ['installed' => true]);
        $user = User::factory()->create(['is_owner' => true]);
        $company = Company::query()->create(['name' => 'Himal Pharma']);
        $unit = Unit::query()->create(['company_id' => $company->id, 'name' => 'Strip', 'type' => 'both']);
        $category = ProductCategory::query()->create(['company_id' => $company->id, 'name' => 'Medicine']);

        $payload = [
            'company_id' => $company->id,
            'unit_id' => $unit->id,
            'category_id' => $category->id,
            'sku' => 'PCM-500',
            'barcode' => '9900012345',
            'name' => 'Paracetamol 500',
            'generic_name' => 'Paracetamol',
            'mrp' => 20,
            'purchase_price' => 12,
            'selling_price' => 18,
            'reorder_level' => 25,
            'is_active' => true,
        ];

        $this->actingAs($user)->postJson('/api/v1/inventory/products', $payload)->assertSuccessful();
        $product = Product::query()->firstOrFail();

        $this->actingAs($user)->getJson('/api/v1/inventory/products?search=Para&sort_field=name&sort_order=asc')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Paracetamol 500');

        $this->actingAs($user)->putJson('/api/v1/inventory/products/'.$product->id, [
            ...$payload,
            'name' => 'Paracetamol 500mg',
        ])->assertOk();

        $this->assertSame('Paracetamol 500mg', $product->fresh()->name);

        $this->actingAs($user)->deleteJson('/api/v1/inventory/products/'.$product->id)->assertOk();
        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }
}
