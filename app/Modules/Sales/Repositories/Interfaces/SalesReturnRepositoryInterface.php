<?php

namespace App\Modules\Sales\Repositories\Interfaces;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Core\DTOs\TableQueryData;
use App\Models\User;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Inventory\Models\Product;
use App\Modules\Party\Models\Customer;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesReturn;
use App\Modules\Sales\Models\SalesReturnItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface SalesReturnRepositoryInterface
{
    public function paginate(TableQueryData $table, ?User $user = null): LengthAwarePaginator;

    public function invoice(?int $id, ?User $user = null): ?SalesInvoice;

    public function customer(int $id, ?User $user = null): Customer;

    public function product(int $id, ?User $user = null): Product;

    public function batch(?int $id, int $productId, ?User $user = null, ?SalesReturn $salesReturn = null): ?Batch;

    public function save(SalesReturn $salesReturn, array $payload): SalesReturn;

    public function createItem(SalesReturn $salesReturn, array $payload): SalesReturnItem;

    public function deleteItems(SalesReturn $salesReturn): void;

    public function delete(SalesReturn $salesReturn): void;

    public function fresh(SalesReturn $salesReturn): SalesReturn;

    public function invoiceOptions(array $filters = [], ?User $user = null): Collection;
}
