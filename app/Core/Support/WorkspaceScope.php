<?php

namespace App\Core\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class WorkspaceScope
{
    /**
     * @param  array<int, string>  $columns
     */
    public static function apply(EloquentBuilder|QueryBuilder|Relation $query, ?User $user, string $table, array $columns = ['tenant_id', 'company_id']): EloquentBuilder|QueryBuilder|Relation
    {
        if (! $user) {
            return $query;
        }

        foreach ($columns as $column) {
            $value = $user->{$column} ?? null;

            if ($value) {
                $query->where($table.'.'.$column, $value);
            }
        }

        return $query;
    }

    /**
     * @param  array<int, string>  $columns
     */
    public static function ensure(Model $model, ?User $user, array $columns = ['tenant_id', 'company_id']): void
    {
        if (! $user) {
            throw new NotFoundHttpException;
        }

        foreach ($columns as $column) {
            if (! array_key_exists($column, $model->getAttributes())) {
                continue;
            }

            $expected = $user->{$column} ?? null;
            $actual = $model->getAttribute($column);

            if ($expected && (string) $actual !== (string) $expected) {
                throw new NotFoundHttpException;
            }
        }
    }
}
