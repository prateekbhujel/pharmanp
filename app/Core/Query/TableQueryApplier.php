<?php

namespace App\Core\Query;

use App\Core\DTOs\TableQueryData;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class TableQueryApplier
{
    public function tenant(Builder $query, ?User $user, string $column): Builder
    {
        return $query->when(
            $user?->tenant_id,
            fn (Builder $builder, int $tenantId): Builder => $builder->where($column, $tenantId),
        );
    }

    public function softDeletes(Builder $query, TableQueryData $table): Builder
    {
        return $query->when(
            (bool) ($table->filters['deleted'] ?? false),
            fn (Builder $builder): Builder => $builder->onlyTrashed(),
        );
    }

    public function activeFilter(Builder $query, TableQueryData $table, string $column): Builder
    {
        return $query->when(
            array_key_exists('is_active', $table->filters),
            fn (Builder $builder): Builder => $builder->where($column, (bool) $table->filters['is_active']),
        );
    }

    public function search(Builder $query, ?string $search, array $columns): Builder
    {
        if (! $search || $columns === []) {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($search, $columns): void {
            foreach ($columns as $column) {
                $builder->orWhere($column, 'like', '%'.$search.'%');
            }
        });
    }

    public function sortColumn(TableQueryData $table, array $allowedSorts, string $default): string
    {
        return $allowedSorts[$table->sortField] ?? $allowedSorts[$default] ?? $default;
    }

    public function paginate(Builder $query, TableQueryData $table): LengthAwarePaginator
    {
        return $query->paginate($table->perPage, ['*'], 'page', $table->page);
    }
}
