<?php

namespace App\Modules\Party\Services;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Core\DTOs\TableQueryData;
use App\Core\Services\DocumentNumberService;
use App\Core\Services\SupplierCodeGenerator;
use App\Models\User;
use App\Modules\Party\DTOs\PartyData;
use App\Modules\Party\Models\Customer;
use App\Modules\Party\Models\Supplier;
use App\Modules\Party\Repositories\Interfaces\PartyRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PartyService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly SupplierCodeGenerator $supplierCodes,
        private readonly PartyRepositoryInterface $parties,
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
        $data['supplier_code'] ??= $this->supplierCodes->next();
        $party = PartyData::fromArray($data);

        return $this->parties->createSupplier($party->toArray(), $user);
    }

    public function updateSupplier(Supplier $supplier, array $data, User $user): Supplier
    {
        $party = PartyData::fromArray($data);

        return $this->parties->updateSupplier($supplier, $party->toArray(), $user);
    }

    public function createCustomer(array $data, User $user): Customer
    {
        $data['customer_code'] ??= $this->numbers->next('customer', 'customers');
        $party = PartyData::fromArray($data);

        return $this->parties->createCustomer($party->toArray(), $user);
    }

    public function updateCustomer(Customer $customer, array $data, User $user): Customer
    {
        $party = PartyData::fromArray($data);

        return $this->parties->updateCustomer($customer, $party->toArray(), $user);
    }
}
