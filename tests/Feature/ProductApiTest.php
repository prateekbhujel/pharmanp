<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Modules\Inventory\Models\Company;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Setup\Models\Division;
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
        $division = Division::query()->create(['company_id' => $company->id, 'name' => 'Cardio', 'code' => 'CARD']);

        $payload = [
            'company_id' => $company->id,
            'unit_id' => $unit->id,
            'sku' => 'PCM-500',
            'barcode' => '9900012345',
            'product_code' => 'ITEM-0001',
            'hs_code' => '3004.90',
            'division_id' => $division->id,
            'name' => 'Paracetamol 500',
            'generic_name' => 'Paracetamol',
            'group_name' => 'Analgesic',
            'manufacturer_name' => 'Himal Pharma',
            'packaging_type' => 'Strip',
            'case_movement' => 'Fast moving',
            'mrp' => 20,
            'purchase_price' => 12,
            'selling_price' => 18,
            'reorder_level' => 25,
            'is_active' => true,
        ];

        $this->actingAs($user)->postJson('/api/v1/inventory/products', $payload)->assertSuccessful();
        $product = Product::query()->firstOrFail();
        $this->assertSame('ITEM-0001', $product->product_code);
        $this->assertSame('3004.90', $product->hs_code);
        $this->assertSame($division->id, $product->division_id);
        $this->assertNull($product->category_id);

        $this->actingAs($user)->getJson('/api/v1/inventory/products?search=Para&sort_field=name&sort_order=asc')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('code', 200)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('data.0.name', 'Paracetamol 500');

        $this->actingAs($user)->putJson('/api/v1/inventory/products/'.$product->id, [
            ...$payload,
            'name' => 'Paracetamol 500mg',
        ])->assertOk();

        $this->assertSame('Paracetamol 500mg', $product->fresh()->name);

        $this->actingAs($user)->deleteJson('/api/v1/inventory/products/'.$product->id)->assertOk();
        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_owner_can_manage_inventory_master_records(): void
    {
        Setting::putValue('app.installed', ['installed' => true]);
        $user = User::factory()->create(['is_owner' => true]);

        $response = $this->actingAs($user)->postJson('/api/v1/inventory/masters/companies', [
            'name' => 'Nepal Pharma Manufacturer',
            'company_type' => 'manufacturer',
            'default_cc_rate' => 5,
            'is_active' => true,
        ])->assertCreated();

        $companyId = $response->json('data.id');

        $this->actingAs($user)->getJson('/api/v1/inventory/masters/companies?search=Nepal')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('code', 200)
            ->assertJsonStructure(['data', 'meta'])
            ->assertJsonPath('data.0.name', 'Nepal Pharma Manufacturer');

        $this->actingAs($user)->putJson('/api/v1/inventory/masters/companies/'.$companyId, [
            'name' => 'Nepal Pharma Labs',
            'company_type' => 'manufacturer',
            'default_cc_rate' => 6,
            'is_active' => true,
        ])->assertOk();

        $this->assertDatabaseHas('companies', ['id' => $companyId, 'name' => 'Nepal Pharma Labs']);

        $this->actingAs($user)->deleteJson('/api/v1/inventory/masters/companies/'.$companyId)->assertOk();
        $this->assertSoftDeleted('companies', ['id' => $companyId]);
    }
}
