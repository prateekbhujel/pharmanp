<?php

namespace App\Modules\Accounting\Repositories\Interfaces;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Core\DTOs\TableQueryData;
use App\Models\User;
use App\Modules\Accounting\Models\Payment;
use App\Modules\Accounting\Models\PaymentBillAllocation;
use App\Modules\Setup\Models\DropdownOption;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

interface PaymentRepositoryInterface
{
    public function paginate(TableQueryData $table, ?User $user = null): LengthAwarePaginator;

    public function lookups(): array;

    public function getForSettlement(?int $id = null, ?User $user = null): Payment;

    public function deleteAllocations(int $paymentId): void;

    public function createAllocation(array $data): PaymentBillAllocation;

    public function paymentMode(int $id, ?User $user = null): DropdownOption;

    public function outstandingCustomerBills(int $customerId, ?User $user = null): Collection;

    public function outstandingSupplierBills(int $supplierId, ?User $user = null): Collection;

    public function resolveBill(string $billType, int $billId, int $partyId, string $partyType, ?User $user = null): Model;

    public function partyExists(string $partyType, int $partyId, User $user): bool;
}
