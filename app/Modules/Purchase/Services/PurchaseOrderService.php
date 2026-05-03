<?php

namespace App\Modules\Purchase\Services;

use App\Core\DTOs\TableQueryData;
use App\Core\Services\DocumentNumberService;
use App\Models\User;
use App\Modules\Purchase\DTOs\PurchaseOrderData;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Models\PurchaseOrder;
use App\Modules\Purchase\Repositories\Interfaces\PurchaseOrderRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseOrderService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly PurchaseOrderRepositoryInterface $orders,
    ) {}

    public function table(TableQueryData $table, ?User $user = null)
    {
        return $this->orders->paginate($table, $user);
    }

    public function approve(PurchaseOrder $order, User $user): PurchaseOrder
    {
        return $this->orders->save($order, ['status' => 'approved', 'updated_by' => $user->id])->fresh();
    }

    public function markPaid(PurchaseOrder $order, User $user): PurchaseOrder
    {
        return $this->orders->save($order, ['status' => 'paid', 'updated_by' => $user->id])->fresh();
    }

    public function create(array $data, User $user): PurchaseOrder
    {
        $dto = PurchaseOrderData::fromArray($data);

        return DB::transaction(function () use ($dto, $user) {
            $data = $dto->toArray();
            [$subtotal, $discountTotal, $grandTotal] = $this->totals($data['items']);

            $order = $this->orders->create([
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

                $this->orders->createItem($order, [
                    'product_id' => $item['product_id'],
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount_percent' => $discountPercent,
                    'discount_amount' => $discount,
                    'line_total' => round($gross - $discount, 2),
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            return $this->orders->fresh($order);
        });
    }

    public function receive(PurchaseOrder $order, array $data, User $user, PurchaseEntryService $purchases): Purchase
    {
        return DB::transaction(function () use ($order, $data, $user, $purchases) {
            $dto = PurchaseOrderData::fromArray($data);
            $data = $dto->toArray();
            $order = $this->orders->orderForReceive((int) $order->id);

            if (in_array($order->status, ['received', 'paid'], true) || $order->received_purchase_id) {
                throw ValidationException::withMessages([
                    'order' => 'This purchase order has already been received.',
                ]);
            }

            if ($order->status === 'ordered') {
                $order = $this->orders->save($order, ['status' => 'approved', 'updated_by' => $user->id]);
            }

            $orderItems = $order->items->keyBy('id');
            $purchaseItems = collect($data['items'])
                ->map(function (array $item) use ($orderItems) {
                    $orderItem = $orderItems->get((int) $item['purchase_order_item_id']);

                    if (! $orderItem || (int) $orderItem->product_id !== (int) $item['product_id']) {
                        throw ValidationException::withMessages([
                            'items' => 'One received item does not belong to this purchase order.',
                        ]);
                    }

                    return [
                        'product_id' => $orderItem->product_id,
                        'batch_no' => $item['batch_no'],
                        'barcode' => $item['barcode'] ?? null,
                        'manufactured_at' => $item['manufactured_at'] ?? null,
                        'expires_at' => $item['expires_at'],
                        'quantity' => $item['quantity'],
                        'free_quantity' => $item['free_quantity'] ?? 0,
                        'purchase_price' => $item['purchase_price'],
                        'mrp' => $item['mrp'],
                        'cc_rate' => $item['cc_rate'] ?? 0,
                        'discount_percent' => $item['discount_percent'] ?? $orderItem->discount_percent,
                    ];
                })
                ->values()
                ->all();

            $purchase = $purchases->create([
                'supplier_id' => $order->supplier_id,
                'supplier_invoice_no' => $data['supplier_invoice_no'] ?? $order->order_no,
                'purchase_date' => $data['purchase_date'],
                'paid_amount' => $data['paid_amount'] ?? 0,
                'notes' => trim(($data['notes'] ?? '')."\nReceived against PO ".$order->order_no),
                'items' => $purchaseItems,
            ], $user);

            $this->orders->save($order, [
                'status' => 'received',
                'received_purchase_id' => $purchase->id,
                'updated_by' => $user->id,
            ]);

            return $purchase->fresh(['supplier', 'items.product', 'items.batch']);
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
