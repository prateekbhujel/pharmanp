<?php

namespace App\Modules\Party\Repositories\Interfaces;

use App\Modules\Party\Models\Customer;
use Illuminate\Support\Collection;

interface CustomerLedgerRepositoryInterface
{
    public function invoices(Customer $customer, ?string $from = null, ?string $to = null): Collection;

    public function returns(Customer $customer, ?string $from = null, ?string $to = null): Collection;

    public function payments(Customer $customer, ?string $from = null, ?string $to = null): Collection;

    public function totals(Customer $customer): object;
}
