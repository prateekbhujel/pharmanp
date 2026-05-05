<?php

namespace App\Modules\Setup\Services;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Modules\Setup\DTOs\SetupTypeData;
use App\Modules\Setup\Repositories\Interfaces\SetupTypeRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class SetupTypeService
{
    public function __construct(private readonly SetupTypeRepositoryInterface $types) {}

    public function all(string $model): Collection
    {
        return $this->types->all($model);
    }

    public function create(string $model, SetupTypeData $data): Model
    {
        return $this->types->create($model, $data->toArray());
    }

    public function update(Model $model, SetupTypeData $data): Model
    {
        return $this->types->update($model, $data->toArray());
    }

    public function delete(Model $model): void
    {
        $this->types->delete($model);
    }
}
