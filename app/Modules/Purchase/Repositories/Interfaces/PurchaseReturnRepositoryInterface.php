<?php

namespace App\Modules\Purchase\Repositories\Interfaces;

use App\Modules\Inventory\Models\Batch;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Models\PurchaseItem;
use App\Modules\Purchase\Models\PurchaseReturn;
use App\Modules\Purchase\Models\PurchaseReturnItem;

interface PurchaseReturnRepositoryInterface
{
    public function purchase(int $id): Purchase;

    public function batchForUpdate(int $id): Batch;

    public function purchaseItem(int $purchaseId, int $purchaseItemId): PurchaseItem;

    public function returnedQuantityForItem(int $purchaseItemId, int $excludingReturnId): float;

    public function createItem(array $data): PurchaseReturnItem;

    public function deleteItems(PurchaseReturn $purchaseReturn): void;

    public function delete(PurchaseReturn $purchaseReturn): void;

    public function save(PurchaseReturn $purchaseReturn, array $data): PurchaseReturn;

    public function adjustSupplierBalance(int $supplierId, float $amount): void;

    public function nextReturnSequence(): int;

    public function fresh(PurchaseReturn $purchaseReturn): PurchaseReturn;
}
