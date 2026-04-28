<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Modules\Inventory\Models\Company;
use App\Modules\Inventory\Models\ProductCategory;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Party\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_preview_detects_columns_and_stages_rows(): void
    {
        Setting::putValue('app.installed', ['installed' => true]);
        Storage::fake('local');
        $user = User::factory()->create(['is_owner' => true]);

        $file = UploadedFile::fake()->createWithContent(
            'products.csv',
            "sku,name,mrp\nPCM-500,Paracetamol 500,20\n",
        );

        $this->actingAs($user)->postJson('/api/v1/imports/preview', [
            'target' => 'products',
            'file' => $file,
        ])
            ->assertOk()
            ->assertJsonPath('data.target', 'products')
            ->assertJsonPath('data.detected_columns.0', 'sku')
            ->assertJsonPath('data.rows.0.raw_data.name', 'Paracetamol 500');
    }

    public function test_import_confirm_inserts_products(): void
    {
        Setting::putValue('app.installed', ['installed' => true]);
        Storage::fake('local');
        $company = Company::query()->create(['name' => 'Import Pharma']);
        Unit::query()->create(['company_id' => $company->id, 'name' => 'Piece']);
        ProductCategory::query()->create(['company_id' => $company->id, 'name' => 'Medicine']);
        $user = User::factory()->create(['company_id' => $company->id, 'is_owner' => true]);

        $file = UploadedFile::fake()->createWithContent(
            'products.csv',
            "sku,name,formulation,mrp,purchase_price,selling_price\nCET-10,Cetirizine 10,Tablet,10,6,9\n",
        );

        $preview = $this->actingAs($user)->postJson('/api/v1/imports/preview', [
            'target' => 'products',
            'file' => $file,
        ])->json('data');

        $this->actingAs($user)->postJson('/api/v1/imports/confirm', [
            'import_job_id' => $preview['id'],
            'mapping' => [
                'sku' => 'sku',
                'name' => 'name',
                'formulation' => 'formulation',
                'mrp' => 'mrp',
                'purchase_price' => 'purchase_price',
                'selling_price' => 'selling_price',
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.valid_rows', 1);

        $this->assertDatabaseHas('products', ['sku' => 'CET-10', 'name' => 'Cetirizine 10']);
    }

    public function test_ocr_purchase_draft_prepares_purchase_entry_payload(): void
    {
        Setting::putValue('app.installed', ['installed' => true]);
        $company = Company::query()->create(['name' => 'OCR Pharma']);
        $supplier = Supplier::query()->create(['company_id' => $company->id, 'name' => 'Himal Supplier']);
        $user = User::factory()->create(['company_id' => $company->id, 'is_owner' => true]);

        $this->actingAs($user)->postJson('/api/v1/imports/ocr/draft-purchase', [
            'ocr_text' => "Himal Supplier\nInvoice No: HS-101\nDate: 2026-04-25",
            'analysis' => [
                'supplier_id' => $supplier->id,
                'supplier_name' => $supplier->name,
                'invoice_no' => 'HS-101',
                'invoice_date' => '2026-04-25',
                'confidence' => 85,
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.supplier_id', $supplier->id)
            ->assertJsonPath('data.supplier_invoice_no', 'HS-101')
            ->assertJsonPath('data.purchase_date', '2026-04-25');
    }
}
