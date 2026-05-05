<?php

namespace App\Modules\Setup\Repositories\Interfaces;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

interface SetupTypeRepositoryInterface
{
    public function all(string $model): Collection;

    public function create(string $model, array $data): Model;

    public function update(Model $model, array $data): Model;

    public function delete(Model $model): void;
}
