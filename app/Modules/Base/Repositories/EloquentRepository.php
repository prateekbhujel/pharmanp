<?php

namespace App\Modules\Base\Repositories;

use App\Modules\Base\Repositories\Interfaces\RepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

abstract class EloquentRepository implements RepositoryInterface
{
    /**
     * @return class-string<Model>
     */
    abstract protected function modelClass(): string;

    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = $this->query();
        $this->applyFilters($query, $filters);

        $sortBy = (string) ($filters['sort_by'] ?? 'updated_at');
        $sortOrder = strtolower((string) ($filters['sort_order'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $perPage = min(max((int) ($filters['per_page'] ?? 15), 5), 100);

        return $query
            ->orderBy($sortBy, $sortOrder)
            ->paginate($perPage, ['*'], 'page', max((int) ($filters['page'] ?? 1), 1));
    }

    public function find(int|string $id): ?Model
    {
        return $this->query()->find($id);
    }

    public function create(array $payload): Model
    {
        return $this->query()->create($payload);
    }

    public function update(Model $model, array $payload): Model
    {
        $model->update($payload);

        return $model;
    }

    public function delete(Model $model): bool
    {
        return (bool) $model->delete();
    }

    protected function query(): Builder
    {
        $class = $this->modelClass();

        if (! is_a($class, Model::class, true)) {
            throw new RuntimeException($class.' must be an Eloquent model.');
        }

        return $class::query();
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        //
    }
}
