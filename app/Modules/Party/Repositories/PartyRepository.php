<?php

namespace App\Modules\Party\Repositories;

use App\Core\DTOs\TableQueryData;
use App\Models\User;
use App\Modules\Party\Models\Customer;
use App\Modules\Party\Models\Supplier;
use App\Modules\Party\Repositories\Interfaces\PartyRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PartyRepository implements PartyRepositoryInterface
{
    public function suppliers(TableQueryData $table, ?User $user = null): LengthAwarePaginator
    {
        $sorts = [
            'name' => 'name',
            'supplier_code' => 'supplier_code',
            'phone' => 'phone',
            'current_balance' => 'current_balance',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
        ];

        $query = Supplier::query()
            ->with('supplierType:id,name')
            ->when($user?->tenant_id, fn (Builder $builder, int $tenantId) => $builder->where('tenant_id', $tenantId))
            ->when((bool) ($table->filters['deleted'] ?? false), fn (Builder $builder) => $builder->onlyTrashed())
            ->when($table->search, function (Builder $builder, string $search) {
                $builder->where(function (Builder $inner) use ($search) {
                    $inner->where('name', 'like', '%'.$search.'%')
                        ->orWhere('supplier_code', 'like', '%'.$search.'%')
                        ->orWhere('contact_person', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('pan_number', 'like', '%'.$search.'%')
                        ->orWhereHas('supplierType', fn (Builder $typeQuery) => $typeQuery->where('name', 'like', '%'.$search.'%'));
                });
            });

        if (array_key_exists('is_active', $table->filters)) {
            $query->where('is_active', (bool) $table->filters['is_active']);
        }

        return $query
            ->orderBy($sorts[$table->sortField] ?? 'updated_at', $table->sortOrder)
            ->paginate($table->perPage, ['*'], 'page', $table->page);
    }

    public function customers(TableQueryData $table, ?User $user = null): LengthAwarePaginator
    {
        $sorts = [
            'name' => 'name',
            'customer_code' => 'customer_code',
            'phone' => 'phone',
            'current_balance' => 'current_balance',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
        ];

        $query = Customer::query()
            ->with('partyType:id,name')
            ->when($user?->tenant_id, fn (Builder $builder, int $tenantId) => $builder->where('tenant_id', $tenantId))
            ->when((bool) ($table->filters['deleted'] ?? false), fn (Builder $builder) => $builder->onlyTrashed())
            ->when($table->search, function (Builder $builder, string $search) {
                $builder->where(function (Builder $inner) use ($search) {
                    $inner->where('name', 'like', '%'.$search.'%')
                        ->orWhere('customer_code', 'like', '%'.$search.'%')
                        ->orWhere('contact_person', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('pan_number', 'like', '%'.$search.'%')
                        ->orWhereHas('partyType', fn (Builder $typeQuery) => $typeQuery->where('name', 'like', '%'.$search.'%'));
                });
            });

        if (array_key_exists('is_active', $table->filters)) {
            $query->where('is_active', (bool) $table->filters['is_active']);
        }

        return $query
            ->orderBy($sorts[$table->sortField] ?? 'updated_at', $table->sortOrder)
            ->paginate($table->perPage, ['*'], 'page', $table->page);
    }

    public function createSupplier(array $data, User $user): Supplier
    {
        return DB::transaction(function () use ($data, $user) {
            $opening = (float) ($data['opening_balance'] ?? 0);

            return Supplier::query()->create([
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

    public function updateSupplier(Supplier $supplier, array $data, User $user): Supplier
    {
        return DB::transaction(function () use ($supplier, $data, $user) {
            $openingBalanceChanged = array_key_exists('opening_balance', $data)
                && (float) $data['opening_balance'] !== (float) $supplier->opening_balance;

            $payload = [
                ...$data,
                'is_active' => $data['is_active'] ?? $supplier->is_active,
                'updated_by' => $user->id,
            ];

            if ($openingBalanceChanged) {
                $difference = (float) $data['opening_balance'] - (float) $supplier->opening_balance;
                $payload['current_balance'] = (float) $supplier->current_balance + $difference;
            }

            $supplier->update($payload);

            return $supplier->fresh('supplierType:id,name');
        });
    }

    public function createCustomer(array $data, User $user): Customer
    {
        return DB::transaction(function () use ($data, $user) {
            $opening = (float) ($data['opening_balance'] ?? 0);

            return Customer::query()->create([
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

    public function updateCustomer(Customer $customer, array $data, User $user): Customer
    {
        return DB::transaction(function () use ($customer, $data, $user) {
            $openingBalanceChanged = array_key_exists('opening_balance', $data)
                && (float) $data['opening_balance'] !== (float) $customer->opening_balance;

            $payload = [
                ...$data,
                'is_active' => $data['is_active'] ?? $customer->is_active,
                'updated_by' => $user->id,
            ];

            if ($openingBalanceChanged) {
                $difference = (float) $data['opening_balance'] - (float) $customer->opening_balance;
                $payload['current_balance'] = (float) $customer->current_balance + $difference;
            }

            $customer->update($payload);

            return $customer->fresh('partyType:id,name');
        });
    }
}
