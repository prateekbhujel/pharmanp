<?php

namespace App\Modules\Purchase\Services;

use App\Core\Services\DocumentNumberService;
use App\Models\User;
use App\Modules\Purchase\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
    ) {}

    public function create(array $data, User $user): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $user) {
            [$subtotal, $discountTotal, $grandTotal] = $this->totals($data['items']);

            $order = PurchaseOrder::query()->create([
                'tenant_id' => $user->tenant_id,
                'company_id' => $user->company_id,
                'store_id' => $user->store_id,
                'supplier_id' => $data['supplier_id'],
                'order_no' => $this->nextNumber(),
                'order_date' => $data['order_date'],
                'expected_date' => $data['expected_date'] ?? null,
                'status' => 'ordered',
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'grand_total' => $grandTotal,
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            foreach ($data['items'] as $item) {
                $quantity = (float) $item['quantity'];
                $unitPrice = (float) $item['unit_price'];
                $discountPercent = (float) ($item['discount_percent'] ?? 0);
                $gross = $quantity * $unitPrice;
                $discount = round($gross * $discountPercent / 100, 2);

                $order->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount_percent' => $discountPercent,
                    'discount_amount' => $discount,
                    'line_total' => round($gross - $discount, 2),
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            return $order->fresh(['supplier', 'items.product']);
        });
    }

    private function totals(array $items): array
    {
        $subtotal = 0;
        $discountTotal = 0;

        foreach ($items as $item) {
            $gross = (float) $item['quantity'] * (float) $item['unit_price'];
            $discount = round($gross * (float) ($item['discount_percent'] ?? 0) / 100, 2);
            $subtotal += $gross;
            $discountTotal += $discount;
        }

        return [round($subtotal, 2), round($discountTotal, 2), round($subtotal - $discountTotal, 2)];
    }

    private function nextNumber(): string
    {
        return $this->numbers->next('purchase_order', 'purchase_orders');
    }
}
