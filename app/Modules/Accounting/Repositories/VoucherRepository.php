<?php

namespace App\Modules\Accounting\Repositories;

use App\Core\DTOs\TableQueryData;
use App\Core\Query\TableQueryApplier;
use App\Models\User;
use App\Modules\Accounting\Models\AccountTransaction;
use App\Modules\Accounting\Models\Voucher;
use App\Modules\Accounting\Models\VoucherEntry;
use App\Modules\Accounting\Repositories\Interfaces\VoucherRepositoryInterface;
use App\Modules\Party\Models\Customer;
use App\Modules\Party\Models\Supplier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class VoucherRepository implements VoucherRepositoryInterface
{
    private const SORTS = [
        'voucher_date' => 'voucher_date',
        'voucher_no' => 'voucher_no',
        'voucher_type' => 'voucher_type',
        'total_amount' => 'total_amount',
        'created_at' => 'created_at',
        'updated_at' => 'updated_at',
    ];

    public function __construct(private readonly TableQueryApplier $tables) {}

    public function paginate(TableQueryData $table, ?User $user = null): LengthAwarePaginator
    {
        $query = Voucher::query()
            ->withCount('entries');

        $this->tables->tenant($query, $user, 'tenant_id');

        $query
            ->when($table->search, function (Builder $builder, string $search): void {
                $builder->where(function (Builder $inner) use ($search): void {
                    $this->tables->search($inner, $search, ['voucher_no', 'voucher_type', 'notes']);
                });
            })
            ->when($table->filters['voucher_type'] ?? null, fn (Builder $builder, mixed $type) => $builder->where('voucher_type', $type))
            ->when($table->filters['from'] ?? null, fn (Builder $builder, mixed $from) => $builder->whereDate('voucher_date', '>=', $from))
            ->when($table->filters['to'] ?? null, fn (Builder $builder, mixed $to) => $builder->whereDate('voucher_date', '<=', $to));

        return $this->tables->paginate(
            $query
                ->orderBy($this->tables->sortColumn($table, self::SORTS, 'voucher_date'), $table->sortOrder)
                ->orderByDesc('id'),
            $table,
        );
    }

    public function create(array $data): Voucher
    {
        return Voucher::query()->create($data);
    }

    public function update(Voucher $voucher, array $data): Voucher
    {
        $voucher->update($data);

        return $voucher;
    }

    public function createEntry(Voucher $voucher, array $data): VoucherEntry
    {
        return $voucher->entries()->create($data);
    }

    public function createTransaction(array $data): AccountTransaction
    {
        return AccountTransaction::query()->create($data);
    }

    public function deleteEntries(Voucher $voucher): void
    {
        $voucher->entries()->delete();
    }

    public function deleteTransactions(Voucher $voucher): void
    {
        AccountTransaction::query()
            ->where('source_type', 'voucher')
            ->where('source_id', $voucher->id)
            ->delete();
    }

    public function delete(Voucher $voucher): void
    {
        $voucher->delete();
    }

    public function fresh(Voucher $voucher): Voucher
    {
        return $voucher->fresh('entries');
    }

    public function partyExists(?string $partyType, ?int $partyId, ?User $user = null): bool
    {
        if (! $partyType || ! $partyId || $partyType === 'other') {
            return true;
        }

        $scope = function (Builder $query) use ($user): void {
            $query
                ->when($user?->tenant_id && ! $user->canAccessAllTenants(), fn (Builder $builder, int $tenantId) => $builder->where('tenant_id', $tenantId))
                ->when($user?->company_id && ! $user->canAccessAllTenants(), fn (Builder $builder, int $companyId) => $builder->where('company_id', $companyId));
        };

        return match ($partyType) {
            'supplier' => Supplier::query()->whereKey($partyId)->where($scope)->exists(),
            'customer' => Customer::query()->whereKey($partyId)->where($scope)->exists(),
            default => true,
        };
    }
}
