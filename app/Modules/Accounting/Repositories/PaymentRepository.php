<?php

namespace App\Modules\Accounting\Repositories;

use App\Core\DTOs\TableQueryData;
use App\Core\Query\TableQueryApplier;
use App\Core\Security\TenantRecordScope;
use App\Models\User;
use App\Modules\Accounting\Models\Payment;
use App\Modules\Accounting\Models\PaymentBillAllocation;
use App\Modules\Accounting\Repositories\Interfaces\PaymentRepositoryInterface;
use App\Modules\Party\Models\Customer;
use App\Modules\Party\Models\Supplier;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Setup\Models\DropdownOption;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PaymentRepository implements PaymentRepositoryInterface
{
    private const SORTS = [
        'payment_date' => 'payment_date',
        'payment_no' => 'payment_no',
        'direction' => 'direction',
        'party_type' => 'party_type',
        'amount' => 'amount',
        'created_at' => 'created_at',
        'updated_at' => 'updated_at',
    ];

    public function __construct(
        private readonly TableQueryApplier $tables,
        private readonly TenantRecordScope $records,
    ) {}

    public function paginate(TableQueryData $table, ?User $user = null): LengthAwarePaginator
    {
        $query = Payment::query()
            ->with(['customer', 'supplier', 'allocations', 'paymentModeOption:id,name,data']);

        $this->tables->tenant($query, $user, 'tenant_id');
        $this->tables->softDeletes($query, $table);

        $query
            ->when($table->filters['direction'] ?? null, fn (Builder $builder, mixed $direction) => $builder->where('direction', $direction))
            ->when($table->filters['party_type'] ?? null, fn (Builder $builder, mixed $partyType) => $builder->where('party_type', $partyType))
            ->when($table->filters['from'] ?? null, fn (Builder $builder, mixed $from) => $builder->where('payment_date', '>=', $from))
            ->when($table->filters['to'] ?? null, fn (Builder $builder, mixed $to) => $builder->where('payment_date', '<=', $to))
            ->when($table->search, function (Builder $builder, string $search): void {
                $builder->where(function (Builder $inner) use ($search): void {
                    $this->tables->search($inner, $search, ['payment_no', 'reference_no', 'notes', 'payment_mode']);
                });
            });

        return $this->tables->paginate(
            $query
                ->orderBy($this->tables->sortColumn($table, self::SORTS, 'payment_date'), $table->sortOrder)
                ->orderByDesc('id'),
            $table,
        );
    }

    public function lookups(): array
    {
        return [
            'payment_modes' => DropdownOption::query()
                ->forAlias('payment_mode')
                ->active()
                ->orderBy('name')
                ->get(['id', 'name', 'data']),
        ];
    }

    public function getForSettlement(?int $id = null, ?User $user = null): Payment
    {
        if (! $id) {
            return new Payment;
        }

        $query = Payment::query()->with('allocations')->lockForUpdate();

        if ($user) {
            $this->records->apply($query, $user);
        }

        return $query->findOrFail($id);
    }

    public function deleteAllocations(int $paymentId): void
    {
        PaymentBillAllocation::query()->where('payment_id', $paymentId)->delete();
    }

    public function createAllocation(array $data): PaymentBillAllocation
    {
        return PaymentBillAllocation::query()->create($data);
    }

    public function paymentMode(int $id, ?User $user = null): DropdownOption
    {
        return DropdownOption::query()
            ->forAlias('payment_mode')
            ->active()
            ->findOrFail($id);
    }

    public function outstandingCustomerBills(int $customerId, ?User $user = null): Collection
    {
        return SalesInvoice::query()
            ->where('customer_id', $customerId)
            ->when($user?->tenant_id, fn ($query, $tenantId) => $query->where('tenant_id', $tenantId))
            ->when($user?->company_id, fn ($query, $companyId) => $query->where('company_id', $companyId))
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->latest('invoice_date')
            ->get();
    }

    public function outstandingSupplierBills(int $supplierId, ?User $user = null): Collection
    {
        return Purchase::query()
            ->where('supplier_id', $supplierId)
            ->when($user?->tenant_id, fn ($query, $tenantId) => $query->where('tenant_id', $tenantId))
            ->when($user?->company_id, fn ($query, $companyId) => $query->where('company_id', $companyId))
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->latest('purchase_date')
            ->get();
    }

    public function resolveBill(string $billType, int $billId, int $partyId, string $partyType, ?User $user = null): Model
    {
        if ($billType === 'sales_invoice' && $partyType === 'customer') {
            $query = SalesInvoice::query()->lockForUpdate()->where('customer_id', $partyId);

            if ($user) {
                $this->records->apply($query, $user, ['store' => null]);
            }

            return $query->findOrFail($billId);
        }

        if ($billType === 'purchase' && $partyType === 'supplier') {
            $query = Purchase::query()->lockForUpdate()->where('supplier_id', $partyId);

            if ($user) {
                $this->records->apply($query, $user, ['store' => null]);
            }

            return $query->findOrFail($billId);
        }

        throw ValidationException::withMessages([
            'allocations' => 'Selected bill does not belong to the chosen party.',
        ]);
    }

    public function partyExists(string $partyType, int $partyId, User $user): bool
    {
        $query = $partyType === 'customer' ? Customer::query() : Supplier::query();

        $this->records->apply($query, $user, ['store' => null]);

        return $query->whereKey($partyId)->exists();
    }
}
