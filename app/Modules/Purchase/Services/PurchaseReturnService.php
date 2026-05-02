<?php

namespace App\Modules\Purchase\Services;

use App\Models\User;
use App\Modules\Accounting\Contracts\AccountTransactionPostingServiceInterface;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Inventory\Contracts\StockMovementServiceInterface;
use App\Modules\Party\Models\Supplier;
use App\Modules\Purchase\Contracts\PurchaseReturnServiceInterface;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Models\PurchaseItem;
use App\Modules\Purchase\Models\PurchaseReturn;
use App\Modules\Purchase\Models\PurchaseReturnItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseReturnService implements PurchaseReturnServiceInterface
{
    public function __construct(
        private readonly StockMovementServiceInterface $stock,
        private readonly AccountTransactionPostingServiceInterface $accounts,
    ) {}

    public function save(array $data, User $user, ?PurchaseReturn $purchaseReturn = null): PurchaseReturn
    {
        return DB::transaction(function () use ($data, $user, $purchaseReturn) {
            $purchase = ! empty($data['purchase_id'])
                ? Purchase::query()->findOrFail($data['purchase_id'])
                : null;

            if ($purchase && (int) $purchase->supplier_id !== (int) $data['supplier_id']) {
                throw ValidationException::withMessages(['purchase_id' => 'Selected purchase does not belong to this supplier.']);
            }

            if ($purchaseReturn) {
                $purchaseReturn->load('items');
                $this->restoreStock($purchaseReturn, $user, false);
                Supplier::query()
                    ->whereKey($purchaseReturn->supplier_id)
                    ->increment('current_balance', (float) $purchaseReturn->grand_total);
                PurchaseReturnItem::query()->where('purchase_return_id', $purchaseReturn->id)->delete();
            }

            $rows = collect($data['items'])
                ->filter(fn (array $row) => (float) ($row['return_qty'] ?? 0) > 0)
                ->values();

            if ($rows->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'Please enter return quantity for at least one line item.']);
            }

            $purchaseReturn ??= new PurchaseReturn();
            $purchaseReturn->fill([
                'tenant_id' => $user->tenant_id,
                'company_id' => $user->company_id,
                'store_id' => $user->store_id,
                'purchase_id' => $purchase?->id,
                'supplier_id' => $data['supplier_id'],
                'return_no' => $purchaseReturn->return_no ?: $this->nextNumber(),
                'return_date' => $data['return_date'],
                'status' => 'posted',
                'subtotal' => 0,
                'discount_total' => 0,
                'grand_total' => 0,
                'notes' => $data['notes'] ?? null,
                'returned_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            if (! $purchaseReturn->exists) {
                $purchaseReturn->created_by = $user->id;
            }

            $purchaseReturn->save();

            $subtotal = 0;
            $discountTotal = 0;
            $grandTotal = 0;
            foreach ($rows as $row) {
                [$lineSubtotal, $lineDiscount, $lineTotal] = $this->postItem($purchaseReturn, $purchase, $row, $user);
                $subtotal += $lineSubtotal;
                $discountTotal += $lineDiscount;
                $grandTotal += $lineTotal;
            }

            $purchaseReturn->forceFill([
                'subtotal' => round($subtotal, 2),
                'discount_total' => round($discountTotal, 2),
                'grand_total' => round($grandTotal, 2),
            ])->save();

            Supplier::query()
                ->whereKey($purchaseReturn->supplier_id)
                ->decrement('current_balance', (float) $purchaseReturn->grand_total);

            $this->accounts->replaceForSource(
                $user,
                'purchase_return',
                $purchaseReturn->id,
                $purchaseReturn->return_date->toDateString(),
                $this->journalEntries($purchaseReturn),
            );

            return $purchaseReturn->fresh(['supplier', 'purchase', 'items.product', 'items.batch']);
        });
    }

    public function delete(PurchaseReturn $purchaseReturn, User $user): void
    {
        DB::transaction(function () use ($purchaseReturn, $user) {
            $purchaseReturn->load('items');
            $this->restoreStock($purchaseReturn, $user, true);

            Supplier::query()
                ->whereKey($purchaseReturn->supplier_id)
                ->increment('current_balance', (float) $purchaseReturn->grand_total);

            $this->accounts->replaceForSource(
                $user,
                'purchase_return',
                $purchaseReturn->id,
                now()->toDateString(),
                [],
            );

            PurchaseReturnItem::query()->where('purchase_return_id', $purchaseReturn->id)->delete();
            $purchaseReturn->delete();
        });
    }

    private function postItem(PurchaseReturn $purchaseReturn, ?Purchase $purchase, array $row, User $user): array
    {
        $batch = Batch::query()->lockForUpdate()->findOrFail($row['batch_id']);
        $returnQty = (float) $row['return_qty'];

        if ((int) $batch->supplier_id !== (int) $purchaseReturn->supplier_id) {
            throw ValidationException::withMessages(['items' => 'Selected batch does not belong to the selected supplier.']);
        }

        if ((int) $batch->product_id !== (int) $row['product_id']) {
            throw ValidationException::withMessages(['items' => 'Selected batch does not belong to the selected product.']);
        }

        $purchaseItem = null;
        $rate = (float) ($row['rate'] ?? $batch->purchase_price);
        $discountPercent = (float) ($row['discount_percent'] ?? 0);

        if ($purchase && ! empty($row['purchase_item_id'])) {
            $purchaseItem = PurchaseItem::query()
                ->where('purchase_id', $purchase->id)
                ->findOrFail($row['purchase_item_id']);

            if ((int) $purchaseItem->product_id !== (int) $row['product_id']) {
                throw ValidationException::withMessages(['items' => 'Purchase line product does not match the return row.']);
            }

            $alreadyReturned = (float) PurchaseReturnItem::query()
                ->where('purchase_item_id', $purchaseItem->id)
                ->where('purchase_return_id', '<>', $purchaseReturn->id)
                ->sum('return_qty');

            $maxReturnable = max(0, (float) $purchaseItem->quantity + (float) $purchaseItem->free_quantity - $alreadyReturned);

            if ($returnQty > $maxReturnable) {
                throw ValidationException::withMessages(['items' => 'Return quantity cannot exceed remaining returnable quantity.']);
            }

            $rate = (float) $purchaseItem->purchase_price;
            $discountPercent = (float) ($row['discount_percent'] ?? $purchaseItem->discount_percent ?? 0);
        }

        if ((float) $batch->quantity_available < $returnQty) {
            throw ValidationException::withMessages(['items' => 'Selected batch does not have enough stock for this return.']);
        }

        [$discountAmount, $netRate, $returnAmount, $discountPercent] = $this->lineAmounts($returnQty, $rate, $discountPercent, $row);

        PurchaseReturnItem::query()->create([
            'purchase_return_id' => $purchaseReturn->id,
            'purchase_item_id' => $purchaseItem?->id,
            'batch_id' => $batch->id,
            'product_id' => (int) $row['product_id'],
            'return_qty' => $returnQty,
            'rate' => $rate,
            'discount_percent' => $discountPercent,
            'discount_amount' => $discountAmount,
            'net_rate' => $netRate,
            'return_amount' => $returnAmount,
        ]);

        $this->stock->record([
            'tenant_id' => $purchaseReturn->tenant_id,
            'company_id' => $purchaseReturn->company_id,
            'store_id' => $purchaseReturn->store_id,
            'movement_date' => $purchaseReturn->return_date->toDateString(),
            'product_id' => (int) $row['product_id'],
            'batch_id' => $batch->id,
            'movement_type' => 'purchase_return_out',
            'quantity_in' => 0,
            'quantity_out' => $returnQty,
            'source_type' => 'purchase_return',
            'source_id' => $purchaseReturn->id,
            'reference_type' => 'purchase_return',
            'reference_id' => $purchaseReturn->id,
            'notes' => 'Purchase return '.$purchaseReturn->return_no,
            'created_by' => $user->id,
        ]);

        return [round($returnQty * $rate, 2), $discountAmount, $returnAmount];
    }

    private function restoreStock(PurchaseReturn $purchaseReturn, User $user, bool $finalDelete): void
    {
        foreach ($purchaseReturn->items as $item) {
            $this->stock->record([
                'tenant_id' => $purchaseReturn->tenant_id,
                'company_id' => $purchaseReturn->company_id,
                'store_id' => $purchaseReturn->store_id,
                'movement_date' => now()->toDateString(),
                'product_id' => $item->product_id,
                'batch_id' => $item->batch_id,
                'movement_type' => 'purchase_return_reverse',
                'quantity_in' => $item->return_qty,
                'quantity_out' => 0,
                'source_type' => 'purchase_return',
                'source_id' => $purchaseReturn->id,
                'reference_type' => 'purchase_return',
                'reference_id' => $purchaseReturn->id,
                'notes' => $finalDelete ? 'Purchase return delete restored stock.' : 'Purchase return edit restored stock.',
                'created_by' => $user->id,
            ]);
        }
    }

    private function totals(array $items): array
    {
        $subtotal = 0;
        $discountTotal = 0;
        $grandTotal = 0;

        foreach ($items as $item) {
            $quantity = (float) $item['return_qty'];
            $rate = (float) ($item['rate'] ?? 0);
            $discountPercent = (float) ($item['discount_percent'] ?? 0);
            [$discountAmount, , $returnAmount] = $this->lineAmounts($quantity, $rate, $discountPercent, $item);

            $subtotal += $quantity * $rate;
            $discountTotal += $discountAmount;
            $grandTotal += $returnAmount;
        }

        return [round($subtotal, 2), round($discountTotal, 2), round($grandTotal, 2)];
    }

    private function lineAmounts(float $quantity, float $rate, float $discountPercent, array $row): array
    {
        $discountPercent = max(0, min(100, $discountPercent));
        $discountAmount = round($quantity * $rate * $discountPercent / 100, 2);
        $netRate = round(max(0, $rate - ($rate * $discountPercent / 100)), 2);

        if (isset($row['net_rate']) && $row['net_rate'] !== '' && $row['net_rate'] !== null) {
            $netRate = round(max(0, min($rate, (float) $row['net_rate'])), 2);
            $discountAmount = round(max(0, ($rate - $netRate) * $quantity), 2);
            $discountPercent = $rate > 0 ? round((($rate - $netRate) / $rate) * 100, 2) : 0;
        } elseif (isset($row['discount_amount']) && $row['discount_amount'] !== '' && $row['discount_amount'] !== null) {
            $discountAmount = round(max(0, (float) $row['discount_amount']), 2);
            $netRate = round(max(0, $rate - ($quantity > 0 ? $discountAmount / $quantity : 0)), 2);
            $discountPercent = $rate > 0 ? round((($rate - $netRate) / $rate) * 100, 2) : 0;
        }

        return [$discountAmount, $netRate, round($quantity * $netRate, 2), $discountPercent];
    }

    private function nextNumber(): string
    {
        $nextId = ((int) DB::table('purchase_returns')->lockForUpdate()->max('id')) + 1;

        return 'PRN-'.now()->format('Ymd').'-'.str_pad((string) $nextId, 5, '0', STR_PAD_LEFT);
    }

    private function journalEntries(PurchaseReturn $purchaseReturn): array
    {
        return [
            [
                'account_type' => 'payable',
                'party_type' => 'supplier',
                'party_id' => $purchaseReturn->supplier_id,
                'debit' => (float) $purchaseReturn->grand_total,
                'credit' => 0,
                'notes' => 'Supplier payable reduced '.$purchaseReturn->return_no,
            ],
            [
                'account_type' => 'inventory',
                'debit' => 0,
                'credit' => (float) $purchaseReturn->grand_total,
                'notes' => 'Inventory sent back '.$purchaseReturn->return_no,
            ],
        ];
    }
}
