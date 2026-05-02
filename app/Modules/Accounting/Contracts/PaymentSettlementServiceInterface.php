<?php

namespace App\Modules\Accounting\Contracts;

use App\Models\User;
use App\Modules\Accounting\Models\Payment;
use Illuminate\Support\Collection;

interface PaymentSettlementServiceInterface
{
    public function save(array $data, User $user): Payment;

    public function delete(Payment $payment, User $user): void;

    public function outstandingBills(string $partyType, int $partyId, ?User $user = null): Collection;

    public function payload(Payment $payment, bool $includeAllocations = false): array;
}
