<?php

namespace App\Modules\Setup\Services;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Models\User;
use App\Modules\Setup\Models\Employee;
use App\Modules\Setup\Models\UserAccessScope;

class AccessScopeService
{
    public function canAccessAll(User $user): bool
    {
        if ($user->canAccessAllTenants() || $user->is_owner || $user->hasRole('Owner')) {
            return true;
        }

        return UserAccessScope::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->where('scope_type', 'all')
            ->exists();
    }

    public function subordinateEmployeeIds(User $user): array
    {
        if (! $user->employee_id) {
            return [];
        }

        $seen = [];
        $frontier = [(int) $user->employee_id];

        while ($frontier !== []) {
            $children = Employee::query()
                ->whereIn('reports_to_employee_id', $frontier)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->reject(fn (int $id) => in_array($id, $seen, true))
                ->values()
                ->all();

            $seen = array_values(array_unique([...$seen, ...$children]));
            $frontier = $children;
        }

        return $seen;
    }

    public function apply($query, User $user, array $columns = [])
    {
        if ($this->canAccessAll($user)) {
            return $query;
        }

        if ($user->tenant_id && ($columns['tenant'] ?? null)) {
            $query->where($columns['tenant'], $user->tenant_id);
        }

        if ($user->company_id && ($columns['company'] ?? null)) {
            $query->where($columns['company'], $user->company_id);
        }

        $scopes = UserAccessScope::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->get(['scope_type', 'scope_id'])
            ->groupBy('scope_type');

        $scopeFilters = [];

        foreach (['branch', 'area', 'division', 'company'] as $type) {
            if (! isset($columns[$type]) || ! $scopes->has($type)) {
                continue;
            }

            $ids = $scopes[$type]->pluck('scope_id')->filter()->values()->all();

            if ($ids !== []) {
                $scopeFilters[] = [$columns[$type], $ids];
            }
        }

        if (($columns['employee'] ?? null) && $scopes->has('own') && $user->employee_id) {
            $scopeFilters[] = [$columns['employee'], [(int) $user->employee_id]];
        }

        if (($columns['employee'] ?? null) && $scopes->has('subordinate')) {
            $ids = $this->subordinateEmployeeIds($user);

            if ($ids !== []) {
                $scopeFilters[] = [$columns['employee'], $ids];
            }
        }

        if ($scopeFilters !== []) {
            $query->where(function ($builder) use ($scopeFilters) {
                foreach ($scopeFilters as [$column, $ids]) {
                    $builder->orWhereIn($column, $ids);
                }
            });
        }

        return $query;
    }
}
