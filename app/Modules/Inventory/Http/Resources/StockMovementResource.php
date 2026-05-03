<?php

namespace App\Modules\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="StockMovementResource",
 *     title="Stock Movement Resource",
 *     description="PharmaNP Stock Movement Resource response contract",
 *
 *     @OA\Property(property="id", type="integer", nullable=true, example=1),
 *     @OA\Property(property="created_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="updated_at", type="string", format="date-time", nullable=true)
 * )
 */
class StockMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'movement_date' => $this->movement_date?->toDateString(),
            'movement_type' => $this->movement_type,
            'quantity_in' => (float) $this->quantity_in,
            'quantity_out' => (float) $this->quantity_out,
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'notes' => $this->notes,
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product?->id,
                'name' => $this->product?->name,
                'sku' => $this->product?->sku,
            ]),
            'batch' => $this->whenLoaded('batch', fn () => [
                'id' => $this->batch?->id,
                'batch_no' => $this->batch?->batch_no,
                'expires_at' => $this->batch?->expires_at?->toDateString(),
            ]),
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator?->id,
                'name' => $this->creator?->name,
            ]),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
