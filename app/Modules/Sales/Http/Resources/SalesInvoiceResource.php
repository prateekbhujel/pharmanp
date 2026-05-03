<?php

namespace App\Modules\Sales\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="SalesInvoiceResource",
 *     title="Sales Invoice Resource",
 *     description="PharmaNP Sales Invoice Resource response contract",
 *
 *     @OA\Property(property="id", type="integer", nullable=true, example=1),
 *     @OA\Property(property="created_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="updated_at", type="string", format="date-time", nullable=true)
 * )
 */
class SalesInvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'company_id' => $this->company_id,
            'store_id' => $this->store_id,
            'branch_id' => $this->branch_id,
            'invoice_no' => $this->invoice_no,
            'invoice_date' => $this->invoice_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'sale_type' => $this->sale_type,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'payment_mode_id' => $this->payment_mode_id,
            'payment_type' => $this->payment_type,
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer?->id,
                'name' => $this->customer?->name,
            ]),
            'medical_representative' => $this->whenLoaded('medicalRepresentative', fn () => [
                'id' => $this->medicalRepresentative?->id,
                'name' => $this->medicalRepresentative?->name,
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
                'free_quantity' => (float) ($item->free_quantity ?? 0),
                'mrp' => (float) $item->mrp,
                'unit_price' => (float) $item->unit_price,
                'cc_rate' => (float) ($item->cc_rate ?? 0),
                'discount_percent' => (float) $item->discount_percent,
                'discount_amount' => (float) $item->discount_amount,
                'free_goods_value' => (float) ($item->free_goods_value ?? 0),
                'line_total' => (float) $item->line_total,
            ])->values()),
            'returns' => $this->whenLoaded('returns', fn () => $this->returns->map(fn ($return) => [
                'id' => $return->id,
                'return_no' => $return->return_no,
                'return_type' => $return->return_type,
                'return_date' => $return->return_date?->toDateString(),
                'total_amount' => (float) $return->total_amount,
                'status' => $return->status,
                'reason' => $return->reason,
                'items' => $return->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_name' => $item->product?->name,
                    'batch_no' => $item->batch?->batch_no,
                    'quantity' => (float) $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'line_total' => (float) $item->line_total,
                ])->values(),
            ])->values()),
        ];
    }
}
