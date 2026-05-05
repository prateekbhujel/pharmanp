<?php

namespace App\Modules\Party\Services;

use App\Core\DTOs\TableQueryData;
use App\Core\Security\TenantRecordScope;
use App\Core\Services\DocumentNumberService;
use App\Core\Services\SupplierCodeGenerator;
use App\Models\User;
use App\Modules\Party\DTOs\PartyData;
use App\Modules\Party\Models\Customer;
use App\Modules\Party\Models\Supplier;
use App\Modules\Party\Repositories\Interfaces\PartyRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class PartyService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly SupplierCodeGenerator $supplierCodes,
        private readonly PartyRepositoryInterface $parties,
        private readonly TenantRecordScope $records,
    ) {}

    public function suppliers(TableQueryData $table, ?User $user = null): LengthAwarePaginator
    {
        return $this->parties->suppliers($table, $user);
    }

    public function customers(TableQueryData $table, ?User $user = null): LengthAwarePaginator
    {
        return $this->parties->customers($table, $user);
    }

    public function createSupplier(array $data, User $user): Supplier
    {
        $data['supplier_code'] ??= $this->supplierCodes->next($user);
        $party = PartyData::fromArray($data);

        return $this->parties->createSupplier($party->toArray(), $user);
    }

    public function updateSupplier(Supplier $supplier, array $data, User $user): Supplier
    {
        $this->assertAccessible($supplier, $user);

        $party = PartyData::fromArray($data);

        return $this->parties->updateSupplier($supplier, $party->toArray(), $user);
    }

    public function createCustomer(array $data, User $user): Customer
    {
        $data['customer_code'] ??= $this->numbers->next('customer', 'customers', null, $user);
        $party = PartyData::fromArray($data);

        return $this->parties->createCustomer($party->toArray(), $user);
    }

    public function updateCustomer(Customer $customer, array $data, User $user): Customer
    {
        $this->assertAccessible($customer, $user);

        $party = PartyData::fromArray($data);

        return $this->parties->updateCustomer($customer, $party->toArray(), $user);
    }

    public function deleteSupplier(Supplier $supplier, User $user): void
    {
        $this->assertAccessible($supplier, $user);

        $supplier->forceFill(['is_active' => false, 'updated_by' => $user->id])->save();
        $supplier->delete();
    }

    public function deleteCustomer(Customer $customer, User $user): void
    {
        $this->assertAccessible($customer, $user);

        $customer->forceFill(['is_active' => false, 'updated_by' => $user->id])->save();
        $customer->delete();
    }

    public function setSupplierStatus(Supplier $supplier, bool $active, User $user): Supplier
    {
        $this->assertAccessible($supplier, $user);

        $supplier->forceFill(['is_active' => $active, 'updated_by' => $user->id])->save();

        return $supplier->refresh();
    }

    public function setCustomerStatus(Customer $customer, bool $active, User $user): Customer
    {
        $this->assertAccessible($customer, $user);

        $customer->forceFill(['is_active' => $active, 'updated_by' => $user->id])->save();

        return $customer->refresh();
    }

    public function restoreSupplier(int $id, User $user): Supplier
    {
        $query = Supplier::query()->onlyTrashed()->whereKey($id);
        $this->records->apply($query, $user, ['store' => null]);
        $supplier = $query->firstOrFail();

        $supplier->restore();
        $supplier->forceFill(['is_active' => true, 'updated_by' => $user->id])->save();

        return $supplier->refresh();
    }

    public function restoreCustomer(int $id, User $user): Customer
    {
        $query = Customer::query()->onlyTrashed()->whereKey($id);
        $this->records->apply($query, $user, ['store' => null]);
        $customer = $query->firstOrFail();

        $customer->restore();
        $customer->forceFill(['is_active' => true, 'updated_by' => $user->id])->save();

        return $customer->refresh();
    }

    public function supplierOptions(User $user): Collection
    {
        $query = Supplier::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(50);

        $this->records->apply($query, $user, ['store' => null]);

        return $query->get(['id', 'name', 'phone', 'current_balance']);
    }

    public function customerOptions(User $user): Collection
    {
        $query = Customer::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(50);

        $this->records->apply($query, $user, ['store' => null]);

        return $query->get(['id', 'name', 'phone', 'current_balance', 'credit_limit']);
    }

    public function assertAccessible(Model $party, User $user): void
    {
        if (! $this->records->canAccess($user, $party, ['store' => null])) {
            abort(404);
        }
    }
}
