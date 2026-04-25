<?php

namespace App\Modules\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'generic_name' => $this->generic_name,
            'composition' => $this->composition,
            'formulation' => $this->formulation,
            'strength' => $this->strength,
            'rack_location' => $this->rack_location,
            'mrp' => (float) $this->mrp,
            'purchase_price' => (float) $this->purchase_price,
            'selling_price' => (float) $this->selling_price,
            'cc_rate' => (float) $this->cc_rate,
            'reorder_level' => (int) $this->reorder_level,
            'reorder_quantity' => (int) $this->reorder_quantity,
            'is_batch_tracked' => (bool) $this->is_batch_tracked,
            'is_active' => (bool) $this->is_active,
            'stock_on_hand' => (float) ($this->stock_on_hand ?? 0),
            'company' => $this->whenLoaded('company', fn () => [
                'id' => $this->company?->id,
                'name' => $this->company?->name,
            ]),
            'unit' => $this->whenLoaded('unit', fn () => [
                'id' => $this->unit?->id,
                'name' => $this->unit?->name,
            ]),
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category?->id,
                'name' => $this->category?->name,
            ]),
            'batches' => $this->whenLoaded('batches', fn () => $this->batches->map(fn ($batch) => [
                'id' => $batch->id,
                'batch_no' => $batch->batch_no,
                'barcode' => $batch->barcode,
                'expires_at' => $batch->expires_at?->toDateString(),
                'quantity_available' => (float) $batch->quantity_available,
                'purchase_price' => (float) $batch->purchase_price,
                'mrp' => (float) $batch->mrp,
            ])->values()),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
