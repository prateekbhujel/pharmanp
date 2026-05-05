<?php

namespace App\Modules\Sales\Repositories;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;
use App\Core\DTOs\TableQueryData;
use App\Core\Query\TableQueryApplier;
use App\Core\Security\TenantRecordScope;
use App\Models\User;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Inventory\Models\Product;
use App\Modules\Party\Models\Customer;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesInvoiceItem;
use App\Modules\Sales\Repositories\Interfaces\SalesInvoiceRepositoryInterface;
use App\Modules\Setup\Models\DropdownOption;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class SalesInvoiceRepository implements SalesInvoiceRepositoryInterface
{
    private const SORTS = [
        'invoice_no' => 'invoice_no',
        'invoice_date' => 'invoice_date',
        'grand_total' => 'grand_total',
        'created_at' => 'created_at',
        'updated_at' => 'updated_at',
    ];

    public function __construct(
        private readonly TableQueryApplier $tables,
        private readonly TenantRecordScope $records,
    ) {}

    public function paginate(TableQueryData $table, ?User $user = null): LengthAwarePaginator
    {
        $query = SalesInvoice::query()
            ->with(['customer:id,name', 'medicalRepresentative:id,name', 'paymentMode:id,name,data']);

        $this->tables->tenant($query, $user, 'tenant_id');

        $query
            ->when($table->search, function (Builder $builder, string $search): void {
                $builder->where(function (Builder $inner) use ($search): void {
                    $this->tables->search($inner, $search, ['invoice_no']);
                    $inner->orWhereHas('customer', fn (Builder $customer) => $customer->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->when($table->filters['customer_id'] ?? null, fn (Builder $builder, mixed $customerId) => $builder->where('customer_id', $customerId))
            ->when($table->filters['payment_status'] ?? null, fn (Builder $builder, mixed $status) => $builder->where('payment_status', $status))
            ->when($table->filters['medical_representative_id'] ?? null, fn (Builder $builder, mixed $id) => $builder->where('medical_representative_id', $id))
            ->when($table->filters['from'] ?? null, fn (Builder $builder, mixed $from) => $builder->whereDate('invoice_date', '>=', $from))
            ->when($table->filters['to'] ?? null, fn (Builder $builder, mixed $to) => $builder->whereDate('invoice_date', '<=', $to));

        return $this->tables->paginate(
            $query
                ->orderBy($this->tables->sortColumn($table, self::SORTS, 'invoice_date'), $table->sortOrder)
                ->orderByDesc('id'),
            $table,
        );
    }

    public function paymentMode(?int $id): ?DropdownOption
    {
        return $id ? DropdownOption::query()->forAlias('payment_mode')->find($id) : null;
    }

    public function createInvoice(array $data): SalesInvoice
    {
        return SalesInvoice::query()->create($data);
    }

    public function invoiceForUpdate(int $id, ?User $user = null): SalesInvoice
    {
        $query = SalesInvoice::query()->lockForUpdate();

        if ($user) {
            $this->records->apply($query, $user);
        }

        return $query->findOrFail($id);
    }

    public function product(int $id, ?User $user = null): Product
    {
        $query = Product::query();

        if ($user) {
            $this->records->apply($query, $user, ['store' => null]);
        }

        return $query->findOrFail($id);
    }

    public function availableBatch(int $productId, float $quantity, ?int $batchId = null, ?User $user = null, ?SalesInvoice $invoice = null): ?Batch
    {
        $query = Batch::query()
            ->where('product_id', $productId)
            ->where('is_active', true)
            ->where('quantity_available', '>=', $quantity)
            ->whereNull('deleted_at')
            ->orderByRaw('expires_at IS NULL')
            ->orderBy('expires_at')
            ->orderBy('id');

        if ($user) {
            $this->records->apply($query, $user);
        }

        if ($invoice) {
            $query
                ->where('tenant_id', $invoice->tenant_id)
                ->where('company_id', $invoice->company_id);

            if ($invoice->store_id) {
                $query->where('store_id', $invoice->store_id);
            }
        }

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

    public function incrementCustomerBalance(int $customerId, float $amount, ?User $user = null, ?SalesInvoice $invoice = null): void
    {
        $query = Customer::query()->whereKey($customerId);

        if ($user) {
            $this->records->apply($query, $user, ['store' => null]);
        }

        if ($invoice) {
            $query
                ->where('tenant_id', $invoice->tenant_id)
                ->where('company_id', $invoice->company_id);
        }

        $updated = $query->increment('current_balance', $amount);

        if ($updated < 1) {
            throw ValidationException::withMessages(['customer_id' => 'Selected customer does not exist in this company.']);
        }
    }

    public function fresh(SalesInvoice $invoice, bool $includeReturns = false): SalesInvoice
    {
        $relations = ['customer', 'medicalRepresentative', 'paymentMode', 'items.product', 'items.batch'];

        if ($includeReturns) {
            $relations[] = 'returns.items.product';
            $relations[] = 'returns.items.batch';
        }

        return $invoice->fresh($relations);
    }
}
