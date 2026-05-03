<?php

namespace App\Modules\Purchase\Repositories\Interfaces;

use App\Core\DTOs\TableQueryData;
use App\Models\User;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Inventory\Models\Product;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Models\PurchaseItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PurchaseRepositoryInterface
{
    public function paginate(TableQueryData $table, ?User $user = null): LengthAwarePaginator;

    public function createPurchase(array $data): Purchase;

    public function productForUpdate(int $id): Product;

    public function batchForPurchase(int $companyId, int $productId, string $batchNo): ?Batch;

    public function createBatch(array $data): Batch;

    public function saveBatch(Batch $batch, array $data): Batch;

    public function createItem(Purchase $purchase, array $data): PurchaseItem;

    public function saveProduct(Product $product, array $data): Product;

    public function incrementSupplierBalance(int $supplierId, float $amount): void;

    public function fresh(Purchase $purchase): Purchase;
}
