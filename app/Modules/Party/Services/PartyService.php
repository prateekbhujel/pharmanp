<?php

namespace App\Modules\Party\Services;

use App\Core\DTOs\TableQueryData;
use App\Core\Services\DocumentNumberService;
use App\Models\User;
use App\Modules\Party\Contracts\PartyServiceInterface;
use App\Modules\Party\Models\Customer;
use App\Modules\Party\Models\Supplier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PartyService implements PartyServiceInterface
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
    ) {}

    private const SORTS = [
        'name' => 'name',
        'supplier_code' => 'supplier_code',
        'customer_code' => 'customer_code',
        'phone' => 'phone',
        'current_balance' => 'current_balance',
        'created_at' => 'created_at',
        'updated_at' => 'updated_at',
    ];

    public function suppliers(TableQueryData $table, ?User $user = null): LengthAwarePaginator
    {
        return $this->paginate(Supplier::query()->with('supplierType:id,name'), $table, 'supplierType', $user);
    }

    public function customers(TableQueryData $table, ?User $user = null): LengthAwarePaginator
    {
        return $this->paginate(Customer::query()->with('partyType:id,name'), $table, 'partyType', $user);
    }

    public function createSupplier(array $data, User $user): Supplier
    {
        $data['supplier_code'] ??= $this->numbers->next('supplier', 'suppliers');

        return $this->create(Supplier::class, $data, $user);
    }

    public function updateSupplier(Supplier $supplier, array $data, User $user): Supplier
    {
        return $this->update($supplier, $data, $user);
    }

    public function createCustomer(array $data, User $user): Customer
    {
        $data['customer_code'] ??= $this->numbers->next('customer', 'customers');

        return $this->create(Customer::class, $data, $user);
    }

    public function updateCustomer(Customer $customer, array $data, User $user): Customer
    {
        return $this->update($customer, $data, $user);
    }

    private function paginate(Builder $query, TableQueryData $table, ?string $typeRelation = null, ?User $user = null): LengthAwarePaginator
    {
        $query
            ->select('*')
            ->when($user?->tenant_id, fn (Builder $builder, int $tenantId) => $builder->where('tenant_id', $tenantId))
            ->when((bool) ($table->filters['deleted'] ?? false), fn (Builder $builder) => $builder->onlyTrashed());

        $codeColumn = $query->getModel()->getTable() === 'suppliers' ? 'supplier_code' : 'customer_code';

        $query->when($table->search, function (Builder $builder, string $search) use ($typeRelation, $codeColumn) {
            $builder->where(function (Builder $inner) use ($search, $typeRelation, $codeColumn) {
                $inner->where('name', 'like', '%'.$search.'%')
                    ->orWhere($codeColumn, 'like', '%'.$search.'%')
                    ->orWhere('contact_person', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('pan_number', 'like', '%'.$search.'%');

                if ($typeRelation) {
                    $inner->orWhereHas($typeRelation, fn (Builder $typeQuery) => $typeQuery->where('name', 'like', '%'.$search.'%'));
                }
            });
        });

        if (array_key_exists('is_active', $table->filters)) {
            $query->where('is_active', (bool) $table->filters['is_active']);
        }

        $query->orderBy(self::SORTS[$table->sortField] ?? 'updated_at', $table->sortOrder);

        return $query->paginate($table->perPage, ['*'], 'page', $table->page);
    }

    /**
     * @template TModel of Model
     * @param class-string<TModel> $model
     * @return TModel
     */
    private function create(string $model, array $data, User $user): Model
    {
        return DB::transaction(function () use ($model, $data, $user) {
            $opening = (float) ($data['opening_balance'] ?? 0);

            return $model::query()->create([
                ...$data,
                'tenant_id' => $user->tenant_id,
                'company_id' => $user->company_id,
                'opening_balance' => $opening,
                'current_balance' => $opening,
                'is_active' => $data['is_active'] ?? true,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
        });
    }

    private function update(Model $party, array $data, User $user): Model
    {
        return DB::transaction(function () use ($party, $data, $user) {
            $openingBalanceChanged = array_key_exists('opening_balance', $data)
                && (float) $data['opening_balance'] !== (float) $party->opening_balance;

            $payload = [
                ...$data,
                'is_active' => $data['is_active'] ?? $party->is_active,
                'updated_by' => $user->id,
            ];

            if ($openingBalanceChanged) {
                $difference = (float) $data['opening_balance'] - (float) $party->opening_balance;
                $payload['current_balance'] = (float) $party->current_balance + $difference;
            }

            $party->update($payload);

            return $party->fresh();
        });
    }
}
