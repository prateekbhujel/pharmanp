<?php

namespace App\Modules\Sales\Repositories;

use App\Modules\Inventory\Models\Batch;
use App\Modules\Inventory\Models\Product;
use App\Modules\Party\Models\Customer;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesInvoiceItem;
use App\Modules\Sales\Repositories\Interfaces\SalesInvoiceRepositoryInterface;

class SalesInvoiceRepository implements SalesInvoiceRepositoryInterface
{
    public function createInvoice(array $data): SalesInvoice
    {
        return SalesInvoice::query()->create($data);
    }

    public function invoiceForUpdate(int $id): SalesInvoice
    {
        return SalesInvoice::query()->lockForUpdate()->findOrFail($id);
    }

    public function product(int $id): Product
    {
        return Product::query()->findOrFail($id);
    }

    public function availableBatch(int $productId, float $quantity, ?int $batchId = null): ?Batch
    {
        $query = Batch::query()
            ->where('product_id', $productId)
            ->where('is_active', true)
            ->where('quantity_available', '>=', $quantity)
            ->whereNull('deleted_at')
            ->orderByRaw('expires_at IS NULL')
            ->orderBy('expires_at')
            ->orderBy('id');

        if ($batchId) {
            $query->whereKey($batchId);
        }

        return $query->lockForUpdate()->first();
    }

    public function createItem(SalesInvoice $invoice, array $data): SalesInvoiceItem
    {
        return $invoice->items()->create($data);
    }

    public function saveInvoice(SalesInvoice $invoice, array $data): SalesInvoice
    {
        $invoice->forceFill($data)->save();

        return $invoice;
    }

    public function incrementCustomerBalance(int $customerId, float $amount): void
    {
        Customer::query()->whereKey($customerId)->increment('current_balance', $amount);
    }

    public function fresh(SalesInvoice $invoice, bool $includeReturns = false): SalesInvoice
    {
        $relations = ['customer', 'medicalRepresentative', 'items.product', 'items.batch'];

        if ($includeReturns) {
            $relations[] = 'returns.items.product';
            $relations[] = 'returns.items.batch';
        }

        return $invoice->fresh($relations);
    }
}
