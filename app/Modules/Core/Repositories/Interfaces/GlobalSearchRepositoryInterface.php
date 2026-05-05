<?php

namespace App\Modules\Core\Repositories\Interfaces;

use App\Models\User;
use Illuminate\Support\Collection;

interface GlobalSearchRepositoryInterface
{
    public function search(string $query, ?User $user = null, int $limit = 5): Collection;
}
