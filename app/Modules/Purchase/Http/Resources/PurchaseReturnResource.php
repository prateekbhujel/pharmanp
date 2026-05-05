<?php

namespace App\Modules\Purchase\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="PurchaseReturnResource",
 *     title="Purchase Return Resource",
 *     description="PharmaNP Purchase Return Resource response contract",
 *
 *     @OA\Property(property="id", type="integer", nullable=true, example=1),
 *     @OA\Property(property="created_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="updated_at", type="string", format="date-time", nullable=true)
 * )
 */
class PurchaseReturnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'return_no' => $this->return_no,
            'return_type' => $this->return_type,
            'purchase_id' => $this->purchase_id,
            'supplier_id' => $this->supplier_id,
            'return_date' => $this->return_date?->toDateString(),
            'status' => $this->status,
            'subtotal' => (float) $this->subtotal,
            'discount_total' => (float) $this->discount_total,
            'grand_total' => (float) $this->grand_total,
            'notes' => $this->notes,
            'deleted_at' => $this->deleted_at?->toISOString(),
            'supplier' => $this->whenLoaded('supplier', fn () => [
                'id' => $this->supplier?->id,
                'name' => $this->supplier?->name,
            ]),
            'purchase' => $this->whenLoaded('purchase', fn () => [
                'id' => $this->purchase?->id,
                'purchase_no' => $this->purchase?->purchase_no,
                'supplier_invoice_no' => $this->purchase?->supplier_invoice_no,
            ]),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'purchase_item_id' => $item->purchase_item_id,
                'product_id' => $item->product_id,
                'batch_id' => $item->batch_id,
                'return_qty' => (float) $item->return_qty,
                'rate' => (float) $item->rate,
                'discount_percent' => (float) $item->discount_percent,
                'discount_amount' => (float) $item->discount_amount,
                'net_rate' => (float) $item->net_rate,
                'return_amount' => (float) $item->return_amount,
                'product' => [
                    'id' => $item->product?->id,
                    'name' => $item->product?->name,
                ],
                'batch' => [
                    'id' => $item->batch?->id,
                    'batch_no' => $item->batch?->batch_no,
                    'quantity_available' => (float) ($item->batch?->quantity_available ?? 0),
                    'expires_at' => $item->batch?->expires_at?->toDateString(),
                ],
            ])->values()),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
