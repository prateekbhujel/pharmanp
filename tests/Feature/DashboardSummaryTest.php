<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Inventory\Models\Company;
use App\Modules\Inventory\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_summary_returns_expiry_rows_as_plain_array(): void
    {
        Setting::putValue('app.installed', ['installed' => true]);

        $company = Company::query()->create(['name' => 'Dashboard Pharma']);
        $owner = User::factory()->create([
            'company_id' => $company->id,
            'is_owner' => true,
        ]);

        $product = Product::query()->create([
            'company_id' => $company->id,
            'name' => 'Paracetamol 500',
            'formulation' => 'Tablet',
            'purchase_price' => 10,
            'selling_price' => 12,
            'mrp' => 15,
            'reorder_level' => 5,
            'is_batch_tracked' => true,
            'is_active' => true,
        ]);

        Batch::query()->create([
            'company_id' => $company->id,
            'product_id' => $product->id,
            'batch_no' => 'PCM-001',
            'expires_at' => today()->addDays(20)->toDateString(),
            'quantity_received' => 50,
            'quantity_available' => 50,
            'purchase_price' => 10,
            'mrp' => 15,
            'is_active' => true,
        ]);

        $response = $this->actingAs($owner)->getJson('/api/v1/dashboard/summary');

        $response->assertOk()
            ->assertJsonPath('data.expiry_rows.0.name', 'Paracetamol 500')
            ->assertJsonPath('data.expiry_rows.0.batch_no', 'PCM-001')
            ->assertJsonPath('data.expiry_rows.0.days_to_expiry', 20);

        $this->assertIsArray($response->json('data.expiry_rows'));
    }
}
