<?php

namespace App\Modules\Accounting\Repositories\Interfaces;

use App\Modules\Accounting\Models\AccountTransaction;

interface AccountTransactionRepositoryInterface
{
    public function deleteBySource(string $sourceType, int $sourceId): void;

    public function create(array $data): AccountTransaction;

    public function partyExists(string $partyType, int $partyId): bool;
}
