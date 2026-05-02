<?php

namespace App\Modules\Accounting\Contracts;

use App\Models\User;

interface AccountTransactionPostingServiceInterface
{
    public function replaceForSource(User $user, string $sourceType, int $sourceId, string $date, array $entries): void;
}
