<?php

namespace App\Modules\Inventory\Repositories\Interfaces;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Core\DTOs\TableQueryData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

interface InventoryMasterRepositoryInterface
{
    public function paginate(string $master, TableQueryData $table): LengthAwarePaginator;

    public function create(string $master, array $payload): Model;

    public function find(string $master, int $id): Model;

    public function findTrashed(string $master, int $id): Model;

    public function save(Model $row, array $payload): Model;

    public function delete(Model $row): void;
}
