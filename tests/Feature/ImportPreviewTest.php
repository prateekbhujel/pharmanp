<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
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
}
