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

class WorkspaceIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_endpoints_are_scoped_to_the_authenticated_company(): void
    {
        Setting::putValue('app.installed', ['installed' => true]);

        [$companyA, $unitA, $categoryA] = $this->inventoryMasters('Alpha Pharma');
        [$companyB, $unitB, $categoryB] = $this->inventoryMasters('Beta Pharma');

        $userA = User::factory()->create(['company_id' => $companyA->id, 'is_owner' => true]);
        $userB = User::factory()->create(['company_id' => $companyB->id, 'is_owner' => true]);

        $productA = Product::query()->create([
            'company_id' => $companyA->id,
            'unit_id' => $unitA->id,
            'category_id' => $categoryA->id,
            'sku' => 'ALPHA-001',
            'barcode' => '111111',
            'name' => 'Alpha Cetirizine',
            'formulation' => 'Tablet',
            'mrp' => 10,
            'purchase_price' => 6,
            'selling_price' => 9,
        ]);

        $productB = Product::query()->create([
            'company_id' => $companyB->id,
            'unit_id' => $unitB->id,
            'category_id' => $categoryB->id,
            'sku' => 'BETA-001',
            'barcode' => '222222',
            'name' => 'Beta Cetirizine',
            'formulation' => 'Tablet',
            'mrp' => 12,
            'purchase_price' => 7,
            'selling_price' => 11,
        ]);

        $this->actingAs($userA)
            ->getJson('/api/v1/inventory/products')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $productA->id);

        $this->actingAs($userA)
            ->getJson('/api/v1/sales/product-lookup?q=Cetirizine')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $productA->id);

        $this->actingAs($userA)
            ->putJson('/api/v1/inventory/products/'.$productB->id, [
                'company_id' => $companyB->id,
                'unit_id' => $unitB->id,
                'category_id' => $categoryB->id,
                'sku' => 'BETA-001',
                'barcode' => '222222',
                'name' => 'Tampered',
                'formulation' => 'Tablet',
                'mrp' => 12,
                'purchase_price' => 7,
                'selling_price' => 11,
                'reorder_level' => 10,
                'is_active' => true,
            ])
            ->assertNotFound();

        $this->assertSame('Beta Cetirizine', $productB->fresh()->name);
        $this->actingAs($userB)
            ->getJson('/api/v1/inventory/products')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $productB->id);
    }

    private function inventoryMasters(string $name): array
    {
        $company = Company::query()->create(['name' => $name]);
        $unit = Unit::query()->create(['company_id' => $company->id, 'name' => $name.' Unit', 'type' => 'both']);
        $category = ProductCategory::query()->create(['company_id' => $company->id, 'name' => $name.' Category']);

        return [$company, $unit, $category];
    }
}
