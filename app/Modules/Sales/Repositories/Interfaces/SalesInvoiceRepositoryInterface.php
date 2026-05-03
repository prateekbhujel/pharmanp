<?php

namespace App\Modules\Sales\Repositories\Interfaces;

use App\Modules\Inventory\Models\Batch;
use App\Modules\Inventory\Models\Product;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesInvoiceItem;

interface SalesInvoiceRepositoryInterface
{
    public function createInvoice(array $data): SalesInvoice;

    public function invoiceForUpdate(int $id): SalesInvoice;

    public function product(int $id): Product;

    public function availableBatch(int $productId, float $quantity, ?int $batchId = null): ?Batch;

    public function createItem(SalesInvoice $invoice, array $data): SalesInvoiceItem;

    public function saveInvoice(SalesInvoice $invoice, array $data): SalesInvoice;

    public function incrementCustomerBalance(int $customerId, float $amount): void;

    public function fresh(SalesInvoice $invoice, bool $includeReturns = false): SalesInvoice;
}
