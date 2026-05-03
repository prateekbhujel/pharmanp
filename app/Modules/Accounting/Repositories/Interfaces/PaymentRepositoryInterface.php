<?php

namespace App\Modules\Accounting\Repositories\Interfaces;

use App\Models\User;
use App\Modules\Accounting\Models\Payment;
use App\Modules\Accounting\Models\PaymentBillAllocation;
use App\Modules\Setup\Models\DropdownOption;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

interface PaymentRepositoryInterface
{
    public function getForSettlement(?int $id = null): Payment;

    public function deleteAllocations(int $paymentId): void;

    public function createAllocation(array $data): PaymentBillAllocation;

    public function paymentMode(int $id): DropdownOption;

    public function outstandingCustomerBills(int $customerId, ?User $user = null): Collection;

    public function outstandingSupplierBills(int $supplierId, ?User $user = null): Collection;

    public function resolveBill(string $billType, int $billId, int $partyId, string $partyType): Model;

    public function partyExists(string $partyType, int $partyId, User $user): bool;
}
