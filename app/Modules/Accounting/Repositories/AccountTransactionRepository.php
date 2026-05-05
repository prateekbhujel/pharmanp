<?php

namespace App\Modules\Accounting\Repositories;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Modules\Accounting\Models\AccountTransaction;
use App\Modules\Accounting\Repositories\Interfaces\AccountTransactionRepositoryInterface;
use App\Modules\Party\Models\Customer;
use App\Modules\Party\Models\Supplier;

class AccountTransactionRepository implements AccountTransactionRepositoryInterface
{
    public function deleteBySource(string $sourceType, int $sourceId): void
    {
        AccountTransaction::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->delete();
    }

    public function create(array $data): AccountTransaction
    {
        return AccountTransaction::query()->create($data);
    }

    public function partyExists(string $partyType, int $partyId): bool
    {
        return match ($partyType) {
            'supplier' => Supplier::query()->whereKey($partyId)->exists(),
            'customer' => Customer::query()->whereKey($partyId)->exists(),
            default => true,
        };
    }
}
