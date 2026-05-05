<?php

namespace App\Modules\Party\Repositories\Interfaces;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Core\DTOs\TableQueryData;
use App\Models\User;
use App\Modules\Party\Models\Customer;
use App\Modules\Party\Models\Supplier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PartyRepositoryInterface
{
    public function suppliers(TableQueryData $table, ?User $user = null): LengthAwarePaginator;

    public function customers(TableQueryData $table, ?User $user = null): LengthAwarePaginator;

    public function createSupplier(array $data, User $user): Supplier;

    public function updateSupplier(Supplier $supplier, array $data, User $user): Supplier;

    public function createCustomer(array $data, User $user): Customer;

    public function updateCustomer(Customer $customer, array $data, User $user): Customer;
}
