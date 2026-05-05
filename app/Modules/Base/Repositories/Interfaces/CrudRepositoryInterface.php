<?php

namespace App\Modules\Base\Repositories\Interfaces;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

interface CrudRepositoryInterface
{
    public function getAll(array $params = []): LengthAwarePaginator;

    public function getById(int|string $id): Model;

    public function create(array $data): Model;

    public function update(int|string $id, array $data): Model;

    public function delete(int|string $id): bool;
}
