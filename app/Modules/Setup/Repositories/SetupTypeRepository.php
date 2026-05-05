<?php

namespace App\Modules\Setup\Repositories;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Modules\Setup\Repositories\Interfaces\SetupTypeRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class SetupTypeRepository implements SetupTypeRepositoryInterface
{
    public function all(string $model): Collection
    {
        return $model::query()->orderBy('name')->get();
    }

    public function create(string $model, array $data): Model
    {
        return $model::query()->create($data);
    }

    public function update(Model $model, array $data): Model
    {
        $model->update($data);

        return $model->fresh();
    }

    public function delete(Model $model): void
    {
        $model->delete();
    }
}
