<?php

namespace App\Modules\Purchase\Repositories;

use App\Modules\Inventory\Models\Batch;
use App\Modules\Inventory\Models\Product;
use App\Modules\Party\Models\Supplier;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Models\PurchaseItem;
use App\Modules\Purchase\Repositories\Interfaces\PurchaseRepositoryInterface;

class PurchaseRepository implements PurchaseRepositoryInterface
{
    public function createPurchase(array $data): Purchase
    {
        return Purchase::query()->create($data);
    }

    public function productForUpdate(int $id): Product
    {
        return Product::query()->lockForUpdate()->findOrFail($id);
    }

    public function batchForPurchase(int $companyId, int $productId, string $batchNo): ?Batch
    {
        return Batch::query()
            ->where('company_id', $companyId)
            ->where('product_id', $productId)
            ->where('batch_no', $batchNo)
            ->lockForUpdate()
            ->first();
    }

    public function createBatch(array $data): Batch
    {
        return Batch::query()->create($data);
    }

    public function saveBatch(Batch $batch, array $data): Batch
    {
        $batch->forceFill($data)->save();

        return $batch;
    }

    public function createItem(Purchase $purchase, array $data): PurchaseItem
    {
        return $purchase->items()->create($data);
    }

    public function saveProduct(Product $product, array $data): Product
    {
        $product->forceFill($data)->save();

        return $product;
    }

    public function incrementSupplierBalance(int $supplierId, float $amount): void
    {
        Supplier::query()->whereKey($supplierId)->increment('current_balance', $amount);
    }

    public function fresh(Purchase $purchase): Purchase
    {
        return $purchase->fresh(['supplier', 'items.product', 'items.batch']);
    }
}
