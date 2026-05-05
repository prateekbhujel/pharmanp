<?php

namespace App\Modules\Core\Repositories\Interfaces;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Models\User;
use Illuminate\Support\Collection;

interface GlobalSearchRepositoryInterface
{
    public function search(string $query, ?User $user = null, int $limit = 5): Collection;
}
