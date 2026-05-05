<?php

namespace App\Modules\Purchase\Http\Resources;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="PurchaseResource",
 *     title="Purchase Resource",
 *     description="PharmaNP Purchase Resource response contract",
 *
 *     @OA\Property(property="id", type="integer", nullable=true, example=1),
 *     @OA\Property(property="created_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="updated_at", type="string", format="date-time", nullable=true)
 * )
 */
class PurchaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'purchase_no' => $this->purchase_no,
            'order_no' => $this->order_no,
            'supplier_invoice_no' => $this->supplier_invoice_no,
            'supplier' => $this->whenLoaded('supplier', fn () => [
                'id' => $this->supplier?->id,
                'name' => $this->supplier?->name,
            ]),
            'purchase_date' => $this->purchase_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'order_date' => $this->order_date?->toDateString(),
            'expected_date' => $this->expected_date?->toDateString(),
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'payment_mode_id' => $this->payment_mode_id,
            'payment_mode' => $this->whenLoaded('paymentMode', fn () => [
                'id' => $this->paymentMode?->id,
                'name' => $this->paymentMode?->name,
                'data' => $this->paymentMode?->data,
            ]),
            'payment_type' => $this->payment_type,
            'subtotal' => (float) $this->subtotal,
            'discount_total' => (float) $this->discount_total,
            'grand_total' => (float) $this->grand_total,
            'paid_amount' => (float) ($this->paid_amount ?? 0),
            'notes' => $this->notes,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product?->name,
                'batch_id' => $item->batch_id ?? null,
                'batch_no' => $item->batch_no ?? null,
                'expires_at' => $item->expires_at?->toDateString(),
                'quantity' => (float) $item->quantity,
                'free_quantity' => (float) ($item->free_quantity ?? 0),
                'unit_price' => (float) ($item->unit_price ?? $item->purchase_price ?? 0),
                'purchase_price' => (float) ($item->purchase_price ?? 0),
                'mrp' => (float) ($item->mrp ?? 0),
                'cc_rate' => (float) ($item->cc_rate ?? 0),
                'discount_percent' => (float) $item->discount_percent,
                'discount_amount' => (float) $item->discount_amount,
                'free_goods_value' => (float) ($item->free_goods_value ?? 0),
                'line_total' => (float) $item->line_total,
            ])->values()),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
