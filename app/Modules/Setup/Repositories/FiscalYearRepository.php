<?php

namespace App\Modules\Setup\Repositories;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Core\DTOs\TableQueryData;
use App\Core\Query\TableQueryApplier;
use App\Models\User;
use App\Modules\Setup\Models\FiscalYear;
use App\Modules\Setup\Repositories\Interfaces\FiscalYearRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class FiscalYearRepository implements FiscalYearRepositoryInterface
{
    private const SORTS = [
        'name' => 'name',
        'starts_on' => 'starts_on',
        'ends_on' => 'ends_on',
        'status' => 'status',
        'created_at' => 'created_at',
        'updated_at' => 'updated_at',
    ];

    public function __construct(private readonly TableQueryApplier $tables) {}

    public function paginate(TableQueryData $table, User $user): LengthAwarePaginator
    {
        $query = FiscalYear::query()
            ->where('company_id', $user->company_id)
            ->when($table->search, function (Builder $builder, string $search): void {
                $builder->where(function (Builder $inner) use ($search): void {
                    $inner->where('name', 'like', '%'.$search.'%')
                        ->orWhere('status', 'like', '%'.$search.'%');
                });
            })
            ->orderBy($this->tables->sortColumn($table, self::SORTS, 'starts_on'), $table->sortOrder)
            ->orderByDesc('id');

        return $this->tables->paginate($query, $table);
    }

    public function save(FiscalYear $fiscalYear, array $data, User $user): FiscalYear
    {
        $status = $data['status'];
        $closedAt = $status === 'closed' ? ($fiscalYear->closed_at ?? now()) : null;

        $fiscalYear->fill([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'name' => $data['name'],
            'starts_on' => $data['starts_on'],
            'ends_on' => $data['ends_on'],
            'is_current' => (bool) ($data['is_current'] ?? false),
            'status' => $status,
            'closed_at' => $closedAt,
            'updated_by' => $user->id,
        ]);

        if (! $fiscalYear->exists) {
            $fiscalYear->created_by = $user->id;
        }

        $fiscalYear->save();

        return $fiscalYear->fresh();
    }

    public function delete(FiscalYear $fiscalYear): void
    {
        $fiscalYear->delete();
    }

    public function replacement(int $companyId): ?FiscalYear
    {
        return FiscalYear::query()
            ->where('company_id', $companyId)
            ->where('status', 'open')
            ->latest('starts_on')
            ->first();
    }

    public function clearCurrent(User $user, ?FiscalYear $except = null): void
    {
        FiscalYear::query()
            ->where('company_id', $user->company_id)
            ->when($except?->exists, fn (Builder $builder) => $builder->whereKeyNot($except->id))
            ->update(['is_current' => false]);
    }

    public function markCurrent(FiscalYear $fiscalYear): void
    {
        $fiscalYear->forceFill(['is_current' => true])->save();
    }
}
