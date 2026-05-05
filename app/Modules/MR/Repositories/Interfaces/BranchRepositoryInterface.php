<?php

namespace App\Modules\MR\Repositories\Interfaces;

use App\Core\DTOs\TableQueryData;
use App\Models\User;
use App\Modules\MR\Models\Branch;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface BranchRepositoryInterface
{
    public function paginate(TableQueryData $table, ?User $user = null): LengthAwarePaginator;

    public function create(array $payload): Branch;

    public function save(Branch $branch, array $payload): Branch;

    public function trashed(int $id, ?User $user = null): Branch;

    public function parentExists(int $parentId, ?User $user = null, ?int $ignoreId = null): bool;

    public function parentOptions(?User $user = null): Collection;

    public function options(?User $user = null): Collection;

    public function hasAssignedRepresentatives(Branch $branch): bool;

    public function hasChildren(Branch $branch): bool;
}
