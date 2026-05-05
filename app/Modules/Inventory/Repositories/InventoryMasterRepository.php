<?php

namespace App\Modules\Inventory\Repositories;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Core\DTOs\TableQueryData;
use App\Core\Query\TableQueryApplier;
use App\Modules\Inventory\Models\Company;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Inventory\Repositories\Interfaces\InventoryMasterRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class InventoryMasterRepository implements InventoryMasterRepositoryInterface
{
    private const SORTS = [
        'name' => 'name',
        'code' => 'code',
        'created_at' => 'created_at',
        'updated_at' => 'updated_at',
    ];

    public function __construct(private readonly TableQueryApplier $tables) {}

    public function paginate(string $master, TableQueryData $table): LengthAwarePaginator
    {
        $model = $this->modelFor($master);

        $query = $model::query()
            ->when($table->filters['deleted'] ?? null, fn (Builder $builder) => $builder->onlyTrashed())
            ->when($table->search, fn (Builder $builder, string $search) => $builder->where('name', 'like', '%'.$search.'%'));

        return $this->tables->paginate(
            $query->orderBy($this->tables->sortColumn($table, self::SORTS, 'updated_at'), $table->sortOrder),
            $table,
        );
    }

    public function create(string $master, array $payload): Model
    {
        return $this->modelFor($master)::query()->create($payload);
    }

    public function find(string $master, int $id): Model
    {
        return $this->modelFor($master)::query()->findOrFail($id);
    }

    public function findTrashed(string $master, int $id): Model
    {
        return $this->modelFor($master)::query()->onlyTrashed()->findOrFail($id);
    }

    public function save(Model $row, array $payload): Model
    {
        $row->forceFill($payload)->save();

        return $row;
    }

    public function delete(Model $row): void
    {
        $row->delete();
    }

    private function modelFor(string $master): string
    {
        return match ($master) {
            'companies' => Company::class,
            'units' => Unit::class,
            default => throw ValidationException::withMessages(['master' => 'Unknown inventory master.']),
        };
    }
}
