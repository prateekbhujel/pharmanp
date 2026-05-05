<?php

namespace App\Modules\Accounting\Repositories;

use App\Core\Security\TenantRecordScope;
use App\Core\Support\MoneyAmount;
use App\Models\User;
use App\Modules\Accounting\Repositories\Interfaces\PartyBalanceRepositoryInterface;
use App\Modules\Party\Models\Customer;
use App\Modules\Party\Models\Supplier;
use Illuminate\Validation\ValidationException;

class PartyBalanceRepository implements PartyBalanceRepositoryInterface
{
    public function __construct(private readonly TenantRecordScope $records) {}

    public function adjustSupplierBalance(int $supplierId, float $amount, ?User $user = null): void
    {
        $query = Supplier::query()->lockForUpdate();

        if ($user) {
            $this->records->apply($query, $user, ['store' => null]);
        }

        $supplier = $query->find($supplierId);

        if (! $supplier) {
            throw ValidationException::withMessages(['supplier_id' => 'Selected supplier does not exist in this company.']);
        }

        $supplier->forceFill([
            'current_balance' => MoneyAmount::add($supplier->current_balance, $amount),
        ])->save();
    }

    public function adjustCustomerBalance(int $customerId, float $amount, ?User $user = null): void
    {
        $query = Customer::query()->lockForUpdate();

        if ($user) {
            $this->records->apply($query, $user, ['store' => null]);
        }

        $customer = $query->find($customerId);

        if (! $customer) {
            throw ValidationException::withMessages(['customer_id' => 'Selected customer does not exist in this company.']);
        }

        $customer->forceFill([
            'current_balance' => MoneyAmount::add($customer->current_balance, $amount),
        ])->save();
    }
}
