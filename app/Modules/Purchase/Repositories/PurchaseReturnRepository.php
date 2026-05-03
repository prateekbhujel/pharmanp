<?php

namespace App\Modules\Purchase\Repositories;

use App\Modules\Inventory\Models\Batch;
use App\Modules\Party\Models\Supplier;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Models\PurchaseItem;
use App\Modules\Purchase\Models\PurchaseReturn;
use App\Modules\Purchase\Models\PurchaseReturnItem;
use App\Modules\Purchase\Repositories\Interfaces\PurchaseReturnRepositoryInterface;
use Illuminate\Support\Facades\DB;

class PurchaseReturnRepository implements PurchaseReturnRepositoryInterface
{
    public function purchase(int $id): Purchase
    {
        return Purchase::query()->findOrFail($id);
    }

    public function batchForUpdate(int $id): Batch
    {
        return Batch::query()->lockForUpdate()->findOrFail($id);
    }

    public function purchaseItem(int $purchaseId, int $purchaseItemId): PurchaseItem
    {
        return PurchaseItem::query()
            ->where('purchase_id', $purchaseId)
            ->findOrFail($purchaseItemId);
    }

    public function returnedQuantityForItem(int $purchaseItemId, int $excludingReturnId): float
    {
        return (float) PurchaseReturnItem::query()
            ->where('purchase_item_id', $purchaseItemId)
            ->where('purchase_return_id', '<>', $excludingReturnId)
            ->sum('return_qty');
    }

    public function createItem(array $data): PurchaseReturnItem
    {
        return PurchaseReturnItem::query()->create($data);
    }

    public function deleteItems(PurchaseReturn $purchaseReturn): void
    {
        PurchaseReturnItem::query()->where('purchase_return_id', $purchaseReturn->id)->delete();
    }

    public function delete(PurchaseReturn $purchaseReturn): void
    {
        $purchaseReturn->delete();
    }

    public function save(PurchaseReturn $purchaseReturn, array $data): PurchaseReturn
    {
        $purchaseReturn->forceFill($data)->save();

        return $purchaseReturn;
    }

    public function adjustSupplierBalance(int $supplierId, float $amount): void
    {
        Supplier::query()->whereKey($supplierId)->increment('current_balance', $amount);
    }

    public function nextReturnSequence(): int
    {
        return ((int) DB::table('purchase_returns')->lockForUpdate()->max('id')) + 1;
    }

    public function fresh(PurchaseReturn $purchaseReturn): PurchaseReturn
    {
        return $purchaseReturn->fresh(['supplier', 'purchase', 'items.product', 'items.batch']);
    }
}
