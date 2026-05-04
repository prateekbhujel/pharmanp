<?php

namespace App\Modules\Sales\Repositories\Interfaces;

use App\Core\DTOs\TableQueryData;
use App\Models\User;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesReturn;
use App\Modules\Sales\Models\SalesReturnItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface SalesReturnRepositoryInterface
{
    public function paginate(TableQueryData $table, ?User $user = null): LengthAwarePaginator;

    public function invoice(?int $id): ?SalesInvoice;

    public function save(SalesReturn $salesReturn, array $payload): SalesReturn;

    public function createItem(SalesReturn $salesReturn, array $payload): SalesReturnItem;

    public function deleteItems(SalesReturn $salesReturn): void;

    public function delete(SalesReturn $salesReturn): void;

    public function nextReturnNo(): string;

    public function fresh(SalesReturn $salesReturn): SalesReturn;

    public function invoiceOptions(array $filters = [], ?User $user = null): Collection;
}
