<?php

namespace App\Modules\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockAdjustmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'adjustment_date' => $this->adjustment_date?->toDateString(),
            'product_id' => $this->product_id,
            'batch_id' => $this->batch_id,
            'adjustment_type' => $this->adjustment_type,
            'quantity' => (float) $this->quantity,
            'reason' => $this->reason,
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product?->id,
                'name' => $this->product?->name,
            ]),
            'batch' => $this->whenLoaded('batch', fn () => [
                'id' => $this->batch?->id,
                'batch_no' => $this->batch?->batch_no,
                'quantity_available' => (float) ($this->batch?->quantity_available ?? 0),
            ]),
            'adjusted_by' => $this->whenLoaded('adjustedBy', fn () => [
                'id' => $this->adjustedBy?->id,
                'name' => $this->adjustedBy?->name,
            ]),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
