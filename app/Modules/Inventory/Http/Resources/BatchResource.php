<?php

namespace App\Modules\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="BatchResource",
 *     title="Batch Resource",
 *     description="PharmaNP Batch Resource response contract",
 *
 *     @OA\Property(property="id", type="integer", nullable=true, example=1),
 *     @OA\Property(property="created_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="updated_at", type="string", format="date-time", nullable=true)
 * )
 */
class BatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'supplier_id' => $this->supplier_id,
            'batch_no' => $this->batch_no,
            'barcode' => $this->barcode,
            'storage_location' => $this->storage_location,
            'manufactured_at' => $this->manufactured_at?->toDateString(),
            'expires_at' => $this->expires_at?->toDateString(),
            'quantity_received' => (float) $this->quantity_received,
            'quantity_available' => (float) $this->quantity_available,
            'purchase_price' => (float) $this->purchase_price,
            'mrp' => (float) $this->mrp,
            'is_active' => (bool) $this->is_active,
            'expiry_status' => $this->expiryStatus(),
            'has_history' => (bool) (
                $this->stock_movements_exists ||
                $this->purchase_items_exists ||
                $this->purchase_return_items_exists ||
                $this->sales_items_exists ||
                $this->sales_return_items_exists
            ),
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product?->id,
                'name' => $this->product?->name,
                'sku' => $this->product?->sku,
                'company' => $this->product?->company?->name,
            ]),
            'supplier' => $this->whenLoaded('supplier', fn () => [
                'id' => $this->supplier?->id,
                'name' => $this->supplier?->name,
            ]),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    private function expiryStatus(): string
    {
        if (! $this->expires_at) {
            return 'no_expiry';
        }

        if ($this->expires_at->isPast()) {
            return 'expired';
        }

        if ($this->expires_at->lte(now()->addDays(30))) {
            return 'expiring_30';
        }

        if ($this->expires_at->lte(now()->addDays(60))) {
            return 'expiring_60';
        }

        return 'valid';
    }
}
