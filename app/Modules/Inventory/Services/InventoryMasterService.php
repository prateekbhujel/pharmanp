<?php

namespace App\Modules\Inventory\Services;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Core\DTOs\TableQueryData;
use App\Core\Support\ApiResponse;
use App\Models\User;
use App\Modules\Inventory\Models\Company;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Inventory\Repositories\Interfaces\InventoryMasterRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class InventoryMasterService
{
    public function __construct(private readonly InventoryMasterRepositoryInterface $masters) {}

    public function table(string $master, TableQueryData $table): array
    {
        $page = $this->masters->paginate($master, $table);

        return [
            'data' => collect($page->items())->map(fn (Model $row) => $this->payload($master, $row))->values(),
            'meta' => ApiResponse::paginationMeta($page),
        ];
    }

    public function create(string $master, array $data, User $user): Model
    {
        return DB::transaction(fn () => $this->masters->create($master, [
            ...$this->dataPayload($master, $data, $user),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]));
    }

    public function update(string $master, int $id, array $data, User $user): Model
    {
        return DB::transaction(function () use ($master, $id, $data, $user) {
            $row = $this->masters->find($master, $id);

            return $this->masters->save($row, [
                ...$this->dataPayload($master, $data, $user),
                'updated_by' => $user->id,
            ])->refresh();
        });
    }

    public function toggleStatus(string $master, int $id, bool $isActive, User $user): Model
    {
        return DB::transaction(function () use ($master, $id, $isActive, $user) {
            $row = $this->masters->find($master, $id);

            return $this->masters->save($row, [
                'is_active' => $isActive,
                'updated_by' => $user->id,
            ])->refresh();
        });
    }

    public function delete(string $master, int $id): void
    {
        DB::transaction(function () use ($master, $id): void {
            $row = $this->masters->find($master, $id);
            $this->masters->save($row, ['is_active' => false]);
            $this->masters->delete($row);
        });
    }

    public function restore(string $master, int $id, User $user): Model
    {
        return DB::transaction(function () use ($master, $id, $user) {
            $row = $this->masters->findTrashed($master, $id);
            $row->restore();

            return $this->masters->save($row, [
                'is_active' => true,
                'updated_by' => $user->id,
            ])->refresh();
        });
    }

    public function quickCompany(array $data, User $user): Company
    {
        return DB::transaction(fn () => Company::query()->create([
            ...$data,
            'tenant_id' => $user->tenant_id,
            'company_type' => $data['company_type'] ?? 'domestic',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]));
    }

    public function quickUnit(array $data, User $user): Unit
    {
        return DB::transaction(fn () => Unit::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $data['company_id'] ?? $user->company_id,
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'type' => $data['type'] ?? 'both',
            'factor' => $data['factor'] ?? 1,
            'description' => $data['description'] ?? null,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]));
    }

    public function payload(string $master, Model $row): array
    {
        return match ($master) {
            'companies' => [
                ...$row->only(['id', 'name', 'legal_name', 'pan_number', 'phone', 'email', 'address', 'company_type', 'default_cc_rate', 'is_active']),
                'deleted_at' => $row->deleted_at?->toISOString(),
                'created_at' => $row->created_at?->toDateString(),
            ],
            'units' => [
                ...$row->only(['id', 'company_id', 'name', 'code', 'type', 'factor', 'description', 'is_active']),
                'deleted_at' => $row->deleted_at?->toISOString(),
                'created_at' => $row->created_at?->toDateString(),
            ],
        };
    }

    private function dataPayload(string $master, array $data, User $user): array
    {
        $base = [
            'tenant_id' => $user->tenant_id,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];

        return match ($master) {
            'companies' => [
                ...$base,
                'name' => $data['name'],
                'legal_name' => $data['legal_name'] ?? null,
                'pan_number' => $data['pan_number'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'address' => $data['address'] ?? null,
                'company_type' => $data['company_type'] ?? 'domestic',
                'default_cc_rate' => $data['default_cc_rate'] ?? 0,
            ],
            'units' => [
                ...$base,
                'company_id' => $data['company_id'] ?? $user->company_id,
                'name' => $data['name'],
                'code' => $data['code'] ?? null,
                'type' => $data['type'] ?? 'both',
                'factor' => $data['factor'] ?? 1,
                'description' => $data['description'] ?? null,
            ],
        };
    }
}
