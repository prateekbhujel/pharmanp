<?php

namespace App\Modules\Sales\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesInvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_no' => $this->invoice_no,
            'invoice_date' => $this->invoice_date?->toDateString(),
            'sale_type' => $this->sale_type,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer?->id,
                'name' => $this->customer?->name,
            ]),
            'subtotal' => (float) $this->subtotal,
            'discount_total' => (float) $this->discount_total,
            'grand_total' => (float) $this->grand_total,
            'paid_amount' => (float) $this->paid_amount,
            'notes' => $this->notes,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product?->name,
                'batch_id' => $item->batch_id,
                'batch_no' => $item->batch?->batch_no,
                'expires_at' => $item->batch?->expires_at?->toDateString(),
                'quantity' => (float) $item->quantity,
                'mrp' => (float) $item->mrp,
                'unit_price' => (float) $item->unit_price,
                'discount_percent' => (float) $item->discount_percent,
                'discount_amount' => (float) $item->discount_amount,
                'line_total' => (float) $item->line_total,
            ])->values()),
        ];
    }
}
