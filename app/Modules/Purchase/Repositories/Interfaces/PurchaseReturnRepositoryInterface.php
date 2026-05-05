<?php

namespace App\Modules\Purchase\Repositories\Interfaces;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Core\DTOs\TableQueryData;
use App\Models\User;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Models\PurchaseItem;
use App\Modules\Purchase\Models\PurchaseReturn;
use App\Modules\Purchase\Models\PurchaseReturnItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PurchaseReturnRepositoryInterface
{
    public function paginate(TableQueryData $table, ?User $user = null): LengthAwarePaginator;

    public function purchase(int $id, ?User $user = null): Purchase;

    public function batchForUpdate(int $id, ?User $user = null, ?PurchaseReturn $purchaseReturn = null): Batch;

    public function purchaseItem(int $purchaseId, int $purchaseItemId, ?User $user = null): PurchaseItem;

    public function returnedQuantityForItem(int $purchaseItemId, int $excludingReturnId): float;

    public function createItem(array $data): PurchaseReturnItem;

    public function deleteItems(PurchaseReturn $purchaseReturn): void;

    public function delete(PurchaseReturn $purchaseReturn): void;

    public function save(PurchaseReturn $purchaseReturn, array $data): PurchaseReturn;

    public function adjustSupplierBalance(int $supplierId, float $amount, ?User $user = null, ?PurchaseReturn $purchaseReturn = null): void;

    public function fresh(PurchaseReturn $purchaseReturn): PurchaseReturn;
}
