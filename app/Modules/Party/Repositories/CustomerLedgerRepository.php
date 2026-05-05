<?php

namespace App\Modules\Party\Repositories;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Core\Security\TenantRecordScope;
use App\Models\User;
use App\Modules\Accounting\Models\Payment;
use App\Modules\Party\Models\Customer;
use App\Modules\Party\Repositories\Interfaces\CustomerLedgerRepositoryInterface;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesReturn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CustomerLedgerRepository implements CustomerLedgerRepositoryInterface
{
    public function __construct(private readonly TenantRecordScope $scope) {}

    public function invoices(Customer $customer, ?string $from = null, ?string $to = null, ?User $user = null): Collection
    {
        return $this->scope->apply(SalesInvoice::query(), $user)
            ->where('customer_id', $customer->id)
            ->when($customer->tenant_id, fn (Builder $query, int $tenantId) => $query->where('tenant_id', $tenantId))
            ->when($customer->company_id, fn (Builder $query, int $companyId) => $query->where('company_id', $companyId))
            ->when($customer->store_id, fn (Builder $query, int $storeId) => $query->where('store_id', $storeId))
            ->whereNull('deleted_at')
            ->when($from, fn (Builder $query, string $date) => $query->whereDate('invoice_date', '>=', $date))
            ->when($to, fn (Builder $query, string $date) => $query->whereDate('invoice_date', '<=', $date))
            ->latest('invoice_date')
            ->get();
    }

    public function returns(Customer $customer, ?string $from = null, ?string $to = null, ?User $user = null): Collection
    {
        return $this->scope->apply(SalesReturn::query(), $user)
            ->with('invoice:id,invoice_no')
            ->where('customer_id', $customer->id)
            ->when($customer->tenant_id, fn (Builder $query, int $tenantId) => $query->where('tenant_id', $tenantId))
            ->when($customer->company_id, fn (Builder $query, int $companyId) => $query->where('company_id', $companyId))
            ->when($customer->store_id, fn (Builder $query, int $storeId) => $query->where('store_id', $storeId))
            ->whereNull('deleted_at')
            ->when($from, fn (Builder $query, string $date) => $query->whereDate('return_date', '>=', $date))
            ->when($to, fn (Builder $query, string $date) => $query->whereDate('return_date', '<=', $date))
            ->latest('return_date')
            ->get();
    }

    public function payments(Customer $customer, ?string $from = null, ?string $to = null, ?User $user = null): Collection
    {
        return $this->scope->apply(Payment::query(), $user)
            ->where('party_type', 'customer')
            ->where('party_id', $customer->id)
            ->when($customer->tenant_id, fn (Builder $query, int $tenantId) => $query->where('tenant_id', $tenantId))
            ->when($customer->company_id, fn (Builder $query, int $companyId) => $query->where('company_id', $companyId))
            ->when($customer->store_id, fn (Builder $query, int $storeId) => $query->where('store_id', $storeId))
            ->whereNull('deleted_at')
            ->when($from, fn (Builder $query, string $date) => $query->whereDate('payment_date', '>=', $date))
            ->when($to, fn (Builder $query, string $date) => $query->whereDate('payment_date', '<=', $date))
            ->latest('payment_date')
            ->get();
    }

    public function totals(Customer $customer, ?User $user = null): object
    {
        return (object) [
            'total_invoiced' => $this->scope->apply(SalesInvoice::query(), $user)
                ->where('customer_id', $customer->id)
                ->when($customer->tenant_id, fn (Builder $query, int $tenantId) => $query->where('tenant_id', $tenantId))
                ->when($customer->company_id, fn (Builder $query, int $companyId) => $query->where('company_id', $companyId))
                ->when($customer->store_id, fn (Builder $query, int $storeId) => $query->where('store_id', $storeId))
                ->whereNull('deleted_at')
                ->sum('grand_total'),
            'total_returned' => $this->scope->apply(SalesReturn::query(), $user)
                ->where('customer_id', $customer->id)
                ->when($customer->tenant_id, fn (Builder $query, int $tenantId) => $query->where('tenant_id', $tenantId))
                ->when($customer->company_id, fn (Builder $query, int $companyId) => $query->where('company_id', $companyId))
                ->when($customer->store_id, fn (Builder $query, int $storeId) => $query->where('store_id', $storeId))
                ->whereNull('deleted_at')
                ->sum('total_amount'),
            'total_paid' => $this->scope->apply(SalesInvoice::query(), $user)
                ->where('customer_id', $customer->id)
                ->when($customer->tenant_id, fn (Builder $query, int $tenantId) => $query->where('tenant_id', $tenantId))
                ->when($customer->company_id, fn (Builder $query, int $companyId) => $query->where('company_id', $companyId))
                ->when($customer->store_id, fn (Builder $query, int $storeId) => $query->where('store_id', $storeId))
                ->whereNull('deleted_at')
                ->sum('paid_amount'),
            'total_payments' => $this->scope->apply(Payment::query(), $user)
                ->where('party_type', 'customer')
                ->where('party_id', $customer->id)
                ->when($customer->tenant_id, fn (Builder $query, int $tenantId) => $query->where('tenant_id', $tenantId))
                ->when($customer->company_id, fn (Builder $query, int $companyId) => $query->where('company_id', $companyId))
                ->when($customer->store_id, fn (Builder $query, int $storeId) => $query->where('store_id', $storeId))
                ->where('direction', 'in')
                ->whereNull('deleted_at')
                ->sum('amount'),
        ];
    }
}
