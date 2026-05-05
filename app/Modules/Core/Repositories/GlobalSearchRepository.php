<?php

namespace App\Modules\Core\Repositories;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Models\User;
use App\Modules\Core\Repositories\Interfaces\GlobalSearchRepositoryInterface;
use App\Modules\Inventory\Models\Product;
use App\Modules\Party\Models\Customer;
use App\Modules\Party\Models\Supplier;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Sales\Models\SalesInvoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class GlobalSearchRepository implements GlobalSearchRepositoryInterface
{
    public function search(string $query, ?User $user = null, int $limit = 5): Collection
    {
        if ($query === '') {
            return collect();
        }

        $like = '%'.$query.'%';

        return collect()
            ->merge($this->products($like, $user, $limit))
            ->merge($this->sales($like, $user, $limit))
            ->merge($this->purchases($like, $user, $limit))
            ->merge($this->customers($like, $user, $limit))
            ->merge($this->suppliers($like, $user, $limit))
            ->values();
    }

    private function products(string $like, ?User $user, int $limit): Collection
    {
        return Product::query()
            ->withSum(['batches as stock_on_hand' => fn (Builder $builder) => $builder->where('is_active', true)], 'quantity_available')
            ->whereNull('deleted_at')
            ->when($user?->tenant_id, fn (Builder $builder, int $tenantId) => $builder->where('tenant_id', $tenantId))
            ->when($user?->company_id, fn (Builder $builder, int $companyId) => $builder->where('company_id', $companyId))
            ->where(function (Builder $builder) use ($like): void {
                $builder->where('name', 'like', $like)
                    ->orWhere('sku', 'like', $like)
                    ->orWhere('barcode', 'like', $like)
                    ->orWhere('product_code', 'like', $like)
                    ->orWhere('generic_name', 'like', $like);
            })
            ->limit($limit)
            ->get()
            ->map(fn (Product $product): array => [
                'key' => "product-{$product->id}",
                'label' => $product->name,
                'description' => "SKU: {$product->sku} | Stock: ".number_format((float) ($product->stock_on_hand ?? 0), 3),
                'type' => 'Product',
                'route' => "/app/inventory/products?id={$product->id}",
            ]);
    }

    private function sales(string $like, ?User $user, int $limit): Collection
    {
        return SalesInvoice::query()
            ->with('customer:id,name')
            ->whereNull('deleted_at')
            ->when($user?->tenant_id, fn (Builder $builder, int $tenantId) => $builder->where('tenant_id', $tenantId))
            ->when($user?->company_id, fn (Builder $builder, int $companyId) => $builder->where('company_id', $companyId))
            ->where(function (Builder $builder) use ($like): void {
                $builder->where('invoice_no', 'like', $like)
                    ->orWhereHas('customer', fn (Builder $customer) => $customer->where('name', 'like', $like));
            })
            ->latest('invoice_date')
            ->limit($limit)
            ->get()
            ->map(fn (SalesInvoice $invoice): array => [
                'key' => "sales-{$invoice->id}",
                'label' => $invoice->invoice_no,
                'description' => trim(($invoice->customer?->name ?: 'Walk-in customer').' | NPR '.number_format((float) $invoice->grand_total, 2)),
                'type' => 'Sales',
                'route' => "/app/sales/invoices?id={$invoice->id}",
            ]);
    }

    private function purchases(string $like, ?User $user, int $limit): Collection
    {
        return Purchase::query()
            ->with('supplier:id,name')
            ->whereNull('deleted_at')
            ->when($user?->tenant_id, fn (Builder $builder, int $tenantId) => $builder->where('tenant_id', $tenantId))
            ->when($user?->company_id, fn (Builder $builder, int $companyId) => $builder->where('company_id', $companyId))
            ->where(function (Builder $builder) use ($like): void {
                $builder->where('purchase_no', 'like', $like)
                    ->orWhere('supplier_invoice_no', 'like', $like)
                    ->orWhereHas('supplier', fn (Builder $supplier) => $supplier->where('name', 'like', $like));
            })
            ->latest('purchase_date')
            ->limit($limit)
            ->get()
            ->map(fn (Purchase $purchase): array => [
                'key' => "purchase-{$purchase->id}",
                'label' => $purchase->purchase_no,
                'description' => trim(($purchase->supplier?->name ?: 'Supplier').' | NPR '.number_format((float) $purchase->grand_total, 2)),
                'type' => 'Purchase',
                'route' => "/app/purchases/bills?id={$purchase->id}",
            ]);
    }

    private function customers(string $like, ?User $user, int $limit): Collection
    {
        return Customer::query()
            ->whereNull('deleted_at')
            ->when($user?->tenant_id, fn (Builder $builder, int $tenantId) => $builder->where('tenant_id', $tenantId))
            ->when($user?->company_id, fn (Builder $builder, int $companyId) => $builder->where('company_id', $companyId))
            ->where(function (Builder $builder) use ($like): void {
                $builder->where('name', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('pan_number', 'like', $like);
            })
            ->limit($limit)
            ->get()
            ->map(fn (Customer $customer): array => [
                'key' => "customer-{$customer->id}",
                'label' => $customer->name,
                'description' => "Phone: {$customer->phone}",
                'type' => 'Customer',
                'route' => "/app/party/customers?id={$customer->id}",
            ]);
    }

    private function suppliers(string $like, ?User $user, int $limit): Collection
    {
        return Supplier::query()
            ->whereNull('deleted_at')
            ->when($user?->tenant_id, fn (Builder $builder, int $tenantId) => $builder->where('tenant_id', $tenantId))
            ->when($user?->company_id, fn (Builder $builder, int $companyId) => $builder->where('company_id', $companyId))
            ->where(function (Builder $builder) use ($like): void {
                $builder->where('name', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('pan_number', 'like', $like);
            })
            ->limit($limit)
            ->get()
            ->map(fn (Supplier $supplier): array => [
                'key' => "supplier-{$supplier->id}",
                'label' => $supplier->name,
                'description' => "Phone: {$supplier->phone}",
                'type' => 'Supplier',
                'route' => "/app/party/suppliers?id={$supplier->id}",
            ]);
    }
}
