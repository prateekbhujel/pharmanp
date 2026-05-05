<?php

namespace Tests\Feature;

use App\Modules\Inventory\Models\Batch;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Services\StockMovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StockMovementTransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_movement_rolls_back_when_batch_would_go_negative(): void
    {
        $product = Product::query()->create(['name' => 'Cetirizine', 'sku' => 'CET-10']);
        $batch = Batch::query()->create([
            'product_id' => $product->id,
            'batch_no' => 'B-1',
            'quantity_received' => 5,
            'quantity_available' => 5,
        ]);

        $service = app(StockMovementService::class);

        $this->expectException(ValidationException::class);

        try {
            $service->record([
                'product_id' => $product->id,
                'batch_id' => $batch->id,
                'movement_type' => 'sales_out',
                'quantity_out' => 6,
            ]);
        } finally {
            $this->assertSame('5.000', $batch->fresh()->quantity_available);
        }
    }

    public function test_stock_movement_updates_batch_inside_transaction(): void
    {
        $product = Product::query()->create(['name' => 'Cetirizine', 'sku' => 'CET-10']);
        $batch = Batch::query()->create([
            'product_id' => $product->id,
            'batch_no' => 'B-1',
            'quantity_received' => 5,
            'quantity_available' => 5,
        ]);

        app(StockMovementService::class)->record([
            'product_id' => $product->id,
            'batch_id' => $batch->id,
            'movement_type' => 'sales_out',
            'quantity_out' => 2,
        ]);

        $this->assertSame('3.000', $batch->fresh()->quantity_available);
    }

    public function test_stock_movement_rejects_mismatched_operating_context(): void
    {
        $product = Product::query()->create(['tenant_id' => 200, 'company_id' => 20, 'name' => 'Cetirizine', 'sku' => 'CET-CTX']);
        $batch = Batch::query()->create([
            'tenant_id' => 200,
            'company_id' => 20,
            'product_id' => $product->id,
            'batch_no' => 'B-CTX',
            'quantity_received' => 5,
            'quantity_available' => 5,
        ]);

        $this->expectException(ValidationException::class);

        try {
            app(StockMovementService::class)->record([
                'tenant_id' => 201,
                'company_id' => 20,
                'product_id' => $product->id,
                'batch_id' => $batch->id,
                'movement_type' => 'sales_out',
                'quantity_out' => 1,
            ]);
        } finally {
            $this->assertSame('5.000', $batch->fresh()->quantity_available);
            $this->assertDatabaseMissing('stock_movements', ['batch_id' => $batch->id]);
        }
    }
}
