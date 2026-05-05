<?php

namespace App\Core\Security;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class TenantRecordScope
{
    private static array $columnCache = [];

    /**
     * Apply the authenticated user's tenant/company/store context to a query.
     *
     * @param  array{tenant?: string|null, company?: string|null, store?: string|null}  $columns
     */
    public function apply(Builder $query, User $user, array $columns = []): Builder
    {
        if ($user->canAccessAllTenants()) {
            return $query;
        }

        $columns = $this->columns($columns);

        if ($user->tenant_id && $columns['tenant']) {
            if ($this->hasColumn($query->getModel(), $columns['tenant'])) {
                $query->where($query->getModel()->getTable().'.'.$columns['tenant'], $user->tenant_id);
            }
        }

        if ($user->company_id && $columns['company']) {
            if ($this->hasColumn($query->getModel(), $columns['company'])) {
                $query->where($query->getModel()->getTable().'.'.$columns['company'], $user->company_id);
            }
        }

        if ($user->store_id && $columns['store']) {
            if ($this->hasColumn($query->getModel(), $columns['store'])) {
                $query->where($query->getModel()->getTable().'.'.$columns['store'], $user->store_id);
            }
        }

        return $query;
    }

    private function hasColumn(Model $model, string $column): bool
    {
        $table = $model->getTable();
        $key = $model->getConnectionName().'.'.$table.'.'.$column;

        if (! isset(self::$columnCache[$key])) {
            self::$columnCache[$key] = $model->getConnection()->getSchemaBuilder()->hasColumn($table, $column);
        }

        return self::$columnCache[$key];
    }

    /**
     * @param  array{tenant?: string|null, company?: string|null, store?: string|null}  $columns
     */
    public function canAccess(User $user, Model $record, array $columns = []): bool
    {
        if ($user->canAccessAllTenants()) {
            return true;
        }

        $columns = $this->columns($columns);

        return $this->matches($user->tenant_id, $record, $columns['tenant'])
            && $this->matches($user->company_id, $record, $columns['company'])
            && $this->matches($user->store_id, $record, $columns['store']);
    }

    /**
     * @param  array{tenant?: string|null, company?: string|null, store?: string|null}  $columns
     */
    public function scopedFindOrFail(Builder $query, User $user, int $id, array $columns = []): Model
    {
        return $this->apply($query, $user, $columns)->whereKey($id)->firstOrFail();
    }

    private function matches(mixed $userValue, Model $record, ?string $column): bool
    {
        if (! $column || $userValue === null || ! $this->hasColumn($record, $column)) {
            return true;
        }

        $recordValue = $record->getAttribute($column);

        return $recordValue !== null && (int) $recordValue === (int) $userValue;
    }

    /**
     * @param  array{tenant?: string|null, company?: string|null, store?: string|null}  $columns
     * @return array{tenant: string|null, company: string|null, store: string|null}
     */
    private function columns(array $columns): array
    {
        return [
            'tenant' => array_key_exists('tenant', $columns) ? $columns['tenant'] : 'tenant_id',
            'company' => array_key_exists('company', $columns) ? $columns['company'] : 'company_id',
            'store' => array_key_exists('store', $columns) ? $columns['store'] : 'store_id',
        ];
    }
}
