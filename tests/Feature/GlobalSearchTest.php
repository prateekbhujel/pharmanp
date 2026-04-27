<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Modules\Inventory\Models\Product;
use App\Modules\Party\Models\Customer;
use App\Modules\Party\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_search_finds_core_records_and_uses_spa_routes(): void
    {
        Setting::putValue('app.installed', ['installed' => true]);
        $user = User::factory()->create(['is_owner' => true]);

        Product::query()->create([
            'name' => 'Paracetamol 500',
            'generic_name' => 'Paracetamol',
            'sku' => 'PCM-500',
            'barcode' => '9900012345',
        ]);
        Customer::query()->create(['name' => 'Walk In Customer', 'phone' => '9800000000']);
        Supplier::query()->create(['name' => 'Himal Supplier', 'phone' => '9811111111']);

        $this->actingAs($user)
            ->getJson('/api/v1/search?query=para')
            ->assertOk()
            ->assertJsonFragment([
                'label' => 'Paracetamol 500',
                'type' => 'Product',
                'route' => '/app/inventory/products?id=1',
            ]);

        $this->actingAs($user)
            ->getJson('/api/v1/search?query=9811111111')
            ->assertOk()
            ->assertJsonFragment([
                'label' => 'Himal Supplier',
                'type' => 'Supplier',
                'route' => '/app/party/suppliers?id=1',
            ]);
    }
}
