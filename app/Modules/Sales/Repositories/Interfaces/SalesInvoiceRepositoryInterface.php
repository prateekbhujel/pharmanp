<?php

namespace App\Modules\Sales\Repositories\Interfaces;

use App\Core\DTOs\TableQueryData;
use App\Models\User;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Inventory\Models\Product;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesInvoiceItem;
use App\Modules\Setup\Models\DropdownOption;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface SalesInvoiceRepositoryInterface
{
    public function paginate(TableQueryData $table, ?User $user = null): LengthAwarePaginator;

    public function paymentMode(?int $id): ?DropdownOption;

    public function createInvoice(array $data): SalesInvoice;

    public function invoiceForUpdate(int $id): SalesInvoice;

    public function product(int $id): Product;

    public function availableBatch(int $productId, float $quantity, ?int $batchId = null): ?Batch;

    public function createItem(SalesInvoice $invoice, array $data): SalesInvoiceItem;

    public function saveInvoice(SalesInvoice $invoice, array $data): SalesInvoice;

    public function incrementCustomerBalance(int $customerId, float $amount): void;

    public function fresh(SalesInvoice $invoice, bool $includeReturns = false): SalesInvoice;
}
