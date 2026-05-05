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

    /**
     * @param  array{tenant?: string|null, company?: string|null, store?: string|null}  $columns
     */
    public function operatingContext(Builder $query, ?User $user, array $columns = []): Builder
    {
        if (! $user || $user->canAccessAllTenants()) {
            return $query;
        }

        $columns = [
            'tenant' => array_key_exists('tenant', $columns) ? $columns['tenant'] : 'tenant_id',
            'company' => array_key_exists('company', $columns) ? $columns['company'] : 'company_id',
            'store' => array_key_exists('store', $columns) ? $columns['store'] : 'store_id',
        ];

        $query
            ->when($user->tenant_id && $columns['tenant'], fn (Builder $builder): Builder => $builder->where($columns['tenant'], $user->tenant_id))
            ->when($user->company_id && $columns['company'], fn (Builder $builder): Builder => $builder->where($columns['company'], $user->company_id))
            ->when($user->store_id && $columns['store'], function (Builder $builder) use ($columns, $user): Builder {
                return $builder->where(function (Builder $store) use ($columns, $user): void {
                    $store->where($columns['store'], $user->store_id)
                        ->orWhereNull($columns['store']);
                });
            });

        return $query;
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
