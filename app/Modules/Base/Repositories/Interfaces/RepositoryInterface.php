<?php

namespace App\Modules\Base\Repositories\Interfaces;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

interface RepositoryInterface
{
    public function paginate(array $filters = []): LengthAwarePaginator;

    public function find(int|string $id): ?Model;

    public function create(array $payload): Model;

    public function update(Model $model, array $payload): Model;

    public function delete(Model $model): bool;
}
