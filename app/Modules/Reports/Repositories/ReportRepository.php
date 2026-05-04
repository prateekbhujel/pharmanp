<?php

namespace App\Modules\Reports\Repositories;

use App\Core\Support\ApiResponse;
use App\Models\User;
use App\Modules\Reports\Repositories\Interfaces\ReportRepositoryInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportRepository implements ReportRepositoryInterface
{
    public function paginate(mixed $query, int $perPage): array
    {
        $page = $query->paginate($perPage);

        return [
            'data' => $page->items(),
            'meta' => ApiResponse::paginationMeta($page),
        ];
    }

    public function salesQuery(User $user, ?string $from = null, ?string $to = null, array $filters = []): Builder
    {
        $query = DB::table('sales_invoices')
            ->leftJoin('customers', 'customers.id', '=', 'sales_invoices.customer_id')
            ->leftJoin('medical_representatives', 'medical_representatives.id', '=', 'sales_invoices.medical_representative_id')
            ->whereNull('sales_invoices.deleted_at')
            ->when($user->tenant_id, fn (Builder $builder, int $tenantId) => $builder->where('sales_invoices.tenant_id', $tenantId))
            ->when($user->company_id, fn (Builder $builder, int $companyId) => $builder->where('sales_invoices.company_id', $companyId))
            ->when($filters['customer_id'] ?? null, fn (Builder $builder, mixed $id) => $builder->where('sales_invoices.customer_id', $id))
            ->when($filters['payment_status'] ?? null, fn (Builder $builder, mixed $status) => $builder->where('sales_invoices.payment_status', $status))
            ->when($filters['medical_representative_id'] ?? null, fn (Builder $builder, mixed $id) => $builder->where('sales_invoices.medical_representative_id', $id))
            ->orderByDesc('sales_invoices.invoice_date')
            ->selectRaw("sales_invoices.id, sales_invoices.invoice_no, sales_invoices.invoice_date, COALESCE(customers.name, 'Walk-in') as customer, COALESCE(medical_representatives.name, '-') as mr_name, sales_invoices.payment_status, sales_invoices.grand_total, sales_invoices.paid_amount");

        return $this->dateRange($query, 'sales_invoices.invoice_date', $from, $to);
    }

    public function purchaseQuery(User $user, ?string $from = null, ?string $to = null, array $filters = []): Builder
    {
        $query = DB::table('purchases')
            ->join('suppliers', 'suppliers.id', '=', 'purchases.supplier_id')
            ->whereNull('purchases.deleted_at')
            ->when($user->tenant_id, fn (Builder $builder, int $tenantId) => $builder->where('purchases.tenant_id', $tenantId))
            ->when($user->company_id, fn (Builder $builder, int $companyId) => $builder->where('purchases.company_id', $companyId))
            ->when($filters['supplier_id'] ?? null, fn (Builder $builder, mixed $id) => $builder->where('purchases.supplier_id', $id))
            ->when($filters['payment_status'] ?? null, fn (Builder $builder, mixed $status) => $builder->where('purchases.payment_status', $status))
            ->orderByDesc('purchases.purchase_date')
            ->selectRaw('purchases.id, purchases.purchase_no, purchases.purchase_date, purchases.supplier_invoice_no, suppliers.name as supplier, purchases.payment_status, purchases.grand_total, purchases.paid_amount');

        return $this->dateRange($query, 'purchases.purchase_date', $from, $to);
    }

    public function stockQuery(User $user, array $filters = []): Builder
    {
        return DB::table('products')
            ->leftJoin('batches', function ($join): void {
                $join->on('batches.product_id', '=', 'products.id')
                    ->whereNull('batches.deleted_at')
                    ->where('batches.is_active', true);
            })
            ->whereNull('products.deleted_at')
            ->when($user->tenant_id, fn (Builder $builder, int $tenantId) => $builder->where('products.tenant_id', $tenantId))
            ->when($user->company_id, fn (Builder $builder, int $companyId) => $builder->where('products.company_id', $companyId))
            ->when($filters['company_id'] ?? null, fn (Builder $builder, mixed $id) => $builder->where('products.company_id', $id))
            ->when($filters['division_id'] ?? null, fn (Builder $builder, mixed $id) => $builder->where('products.division_id', $id))
            ->leftJoin('divisions', 'divisions.id', '=', 'products.division_id')
            ->groupBy('products.id', 'products.name', 'products.product_code', 'products.sku', 'products.reorder_level', 'divisions.name')
            ->orderBy('products.name')
            ->selectRaw('products.id, products.name, products.product_code, products.sku, divisions.name as division, products.reorder_level, COALESCE(SUM(batches.quantity_available), 0) as stock_on_hand');
    }

    public function lowStockQuery(User $user, array $filters = []): Builder
    {
        return $this->stockQuery($user, $filters)
            ->where('products.is_active', true)
            ->havingRaw('COALESCE(SUM(batches.quantity_available), 0) <= products.reorder_level');
    }

    public function expiryQuery(User $user, ?string $from = null, ?string $to = null, array $filters = []): Builder
    {
        $query = DB::table('batches')
            ->join('products', 'products.id', '=', 'batches.product_id')
            ->whereNull('batches.deleted_at')
            ->where('batches.quantity_available', '>', 0)
            ->when($user->tenant_id, fn (Builder $builder, int $tenantId) => $builder->where('batches.tenant_id', $tenantId))
            ->when($user->company_id, fn (Builder $builder, int $companyId) => $builder->where('batches.company_id', $companyId))
            ->when($filters['product_id'] ?? null, fn (Builder $builder, mixed $id) => $builder->where('batches.product_id', $id))
            ->orderBy('batches.expires_at')
            ->selectRaw('products.id as product_id, products.name as product, batches.batch_no, batches.expires_at, batches.quantity_available, batches.mrp');

        return $this->dateRange($query, 'batches.expires_at', $from, $to);
    }

    public function supplierPerformanceQuery(User $user, ?string $from = null, ?string $to = null): Builder
    {
        return DB::table('suppliers')
            ->leftJoin('purchases', function ($join) use ($from, $to): void {
                $join->on('purchases.supplier_id', '=', 'suppliers.id')
                    ->whereNull('purchases.deleted_at')
                    ->when($from, fn (Builder $builder) => $builder->where('purchases.purchase_date', '>=', $from))
                    ->when($to, fn (Builder $builder) => $builder->where('purchases.purchase_date', '<=', $to));
            })
            ->whereNull('suppliers.deleted_at')
            ->when($user->tenant_id, fn (Builder $builder, int $tenantId) => $builder->where('suppliers.tenant_id', $tenantId))
            ->when($user->company_id, fn (Builder $builder, int $companyId) => $builder->where('suppliers.company_id', $companyId))
            ->groupBy('suppliers.id', 'suppliers.name', 'suppliers.current_balance')
            ->orderByDesc('purchase_total')
            ->selectRaw('suppliers.id, suppliers.name, suppliers.current_balance, COUNT(purchases.id) as purchase_count, COALESCE(SUM(purchases.grand_total), 0) as purchase_total');
    }

    public function accountBookQuery(User $user, ?string $from = null, ?string $to = null, ?string $accountType = null, array $filters = []): Builder
    {
        $query = DB::table('account_transactions')
            ->leftJoin('customers', function ($join): void {
                $join->on('customers.id', '=', 'account_transactions.party_id')
                    ->where('account_transactions.party_type', '=', 'customer');
            })
            ->leftJoin('suppliers', function ($join): void {
                $join->on('suppliers.id', '=', 'account_transactions.party_id')
                    ->where('account_transactions.party_type', '=', 'supplier');
            })
            ->when($user->tenant_id, fn (Builder $builder, int $tenantId) => $builder->where('account_transactions.tenant_id', $tenantId))
            ->when($user->company_id, fn (Builder $builder, int $companyId) => $builder->where('account_transactions.company_id', $companyId))
            ->when($accountType, fn (Builder $builder, string $type) => $builder->where('account_type', $type))
            ->when($filters['party_type'] ?? null, fn (Builder $builder, mixed $type) => $builder->where('party_type', $type))
            ->when($filters['party_id'] ?? null, fn (Builder $builder, mixed $id) => $builder->where('party_id', $id))
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->selectRaw("transaction_date as date, account_type, party_type, party_id, COALESCE(customers.name, suppliers.name, '-') as party_name, source_type, source_id, debit, credit, notes");

        return $this->dateRange($query, 'transaction_date', $from, $to);
    }

    public function supplierLedgerQuery(User $user, int $supplierId, ?string $from = null, ?string $to = null): Builder
    {
        $query = DB::table('purchases')
            ->where('supplier_id', $supplierId)
            ->whereNull('deleted_at')
            ->when($user->tenant_id, fn (Builder $builder, int $tenantId) => $builder->where('tenant_id', $tenantId))
            ->when($user->company_id, fn (Builder $builder, int $companyId) => $builder->where('company_id', $companyId))
            ->orderBy('purchase_date')
            ->orderBy('id')
            ->selectRaw('purchase_date as date, purchase_no as reference, grand_total as credit, paid_amount as debit, payment_status');

        return $this->dateRange($query, 'purchase_date', $from, $to);
    }

    public function customerLedgerQuery(User $user, int $customerId, ?string $from = null, ?string $to = null): Builder
    {
        $query = DB::table('sales_invoices')
            ->where('customer_id', $customerId)
            ->whereNull('deleted_at')
            ->when($user->tenant_id, fn (Builder $builder, int $tenantId) => $builder->where('tenant_id', $tenantId))
            ->when($user->company_id, fn (Builder $builder, int $companyId) => $builder->where('company_id', $companyId))
            ->orderBy('invoice_date')
            ->selectRaw('invoice_date as date, invoice_no as reference, grand_total as debit, paid_amount as credit, payment_status');

        return $this->dateRange($query, 'invoice_date', $from, $to);
    }

    public function productMovementQuery(User $user, int $productId, ?string $from = null, ?string $to = null): Builder
    {
        $query = DB::table('stock_movements')
            ->leftJoin('batches', 'batches.id', '=', 'stock_movements.batch_id')
            ->where('stock_movements.product_id', $productId)
            ->when($user->tenant_id, fn (Builder $builder, int $tenantId) => $builder->where('stock_movements.tenant_id', $tenantId))
            ->when($user->company_id, fn (Builder $builder, int $companyId) => $builder->where('stock_movements.company_id', $companyId))
            ->orderBy('stock_movements.movement_date')
            ->orderBy('stock_movements.id')
            ->selectRaw('stock_movements.movement_date, stock_movements.movement_type, batches.batch_no, stock_movements.quantity_in, stock_movements.quantity_out, stock_movements.notes');

        return $this->dateRange($query, 'stock_movements.movement_date', $from, $to);
    }

    public function accountTransactionTotals(?int $tenantId, ?int $companyId, ?string $from = null, ?string $to = null, ?string $accountType = null): object
    {
        return $this->scopedAccountTransactions($tenantId, $companyId, $from, $to, $accountType)
            ->selectRaw('COALESCE(SUM(debit), 0) as debit_total, COALESCE(SUM(credit), 0) as credit_total')
            ->first() ?: (object) ['debit_total' => 0, 'credit_total' => 0];
    }

    public function accountTypeTotals(?int $tenantId, ?int $companyId, ?string $from = null, ?string $to = null): Collection
    {
        return $this->scopedAccountTransactions($tenantId, $companyId, $from, $to)
            ->selectRaw('account_type, SUM(debit) as debit_total, SUM(credit) as credit_total')
            ->groupBy('account_type')
            ->get()
            ->keyBy('account_type');
    }

    private function scopedAccountTransactions(?int $tenantId, ?int $companyId, ?string $from = null, ?string $to = null, ?string $accountType = null)
    {
        $query = DB::table('account_transactions')
            ->when($tenantId, fn ($builder, int $id) => $builder->where('tenant_id', $id))
            ->when($companyId, fn ($builder, int $id) => $builder->where('company_id', $id))
            ->when($accountType, fn ($builder, string $type) => $builder->where('account_type', $type));

        if ($from) {
            $query->whereDate('transaction_date', '>=', $from);
        }

        if ($to) {
            $query->whereDate('transaction_date', '<=', $to);
        }

        return $query;
    }

    private function dateRange(Builder $query, string $column, ?string $from = null, ?string $to = null): Builder
    {
        if ($from) {
            $query->whereDate($column, '>=', $from);
        }

        if ($to) {
            $query->whereDate($column, '<=', $to);
        }

        return $query;
    }
}
