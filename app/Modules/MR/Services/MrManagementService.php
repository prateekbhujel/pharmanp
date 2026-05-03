<?php

namespace App\Modules\MR\Services;

use App\Core\DTOs\TableQueryData;
use App\Models\User;
use App\Modules\MR\DTOs\MedicalRepresentativeData;
use App\Modules\MR\DTOs\RepresentativeVisitData;
use App\Modules\MR\Models\MedicalRepresentative;
use App\Modules\MR\Models\RepresentativeVisit;
use App\Modules\MR\Repositories\Interfaces\MrRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class MrManagementService
{
    public function __construct(private readonly MrRepositoryInterface $mrs) {}

    public function representatives(TableQueryData $table, ?User $user = null): LengthAwarePaginator
    {
        return $this->mrs->representatives($table, $user);
    }

    public function visits(TableQueryData $table, ?User $user = null): LengthAwarePaginator
    {
        return $this->mrs->visits($table, $user);
    }

    public function createRepresentative(array $data, User $user): MedicalRepresentative
    {
        $dto = MedicalRepresentativeData::fromArray($data);

        return DB::transaction(function () use ($dto, $user) {
            return $this->mrs->createRepresentative([
                'tenant_id' => $user->tenant_id,
                'company_id' => $user->company_id,
                ...$dto->toArray(),
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
        });
    }

    public function updateRepresentative(MedicalRepresentative $representative, array $data, User $user): MedicalRepresentative
    {
        $dto = MedicalRepresentativeData::fromArray([
            'branch_id' => $representative->branch_id,
            'is_active' => $representative->is_active,
            ...$data,
        ]);

        return DB::transaction(function () use ($representative, $dto, $user) {
            return $this->mrs->updateRepresentative($representative, [
                ...$dto->toArray(),
                'updated_by' => $user->id,
            ]);
        });
    }

    public function deleteRepresentative(MedicalRepresentative $representative, User $user): void
    {
        DB::transaction(function () use ($representative, $user) {
            $representative->forceFill([
                'is_active' => false,
                'updated_by' => $user->id,
            ])->save();
            $this->mrs->deleteRepresentative($representative);
        });
    }

    public function createVisit(array $data, User $user): RepresentativeVisit
    {
        $dto = RepresentativeVisitData::fromArray($data);

        return DB::transaction(function () use ($dto, $user) {
            $data = $dto->toArray();
            $representativeId = $this->resolveRepresentativeId($data, $user);

            return $this->mrs->createVisit([
                'tenant_id' => $user->tenant_id,
                'company_id' => $user->company_id,
                'medical_representative_id' => $representativeId,
                ...$data,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
        });
    }

    public function updateVisit(RepresentativeVisit $visit, array $data, User $user): RepresentativeVisit
    {
        $dto = RepresentativeVisitData::fromArray($data);

        return DB::transaction(function () use ($visit, $dto, $user) {
            $data = $dto->toArray();
            $representativeId = $this->resolveRepresentativeId($data, $user);

            return $this->mrs->updateVisit($visit, [
                'tenant_id' => $visit->tenant_id ?: $user->tenant_id,
                'company_id' => $visit->company_id ?: $user->company_id,
                'medical_representative_id' => $representativeId,
                ...$data,
                'updated_by' => $user->id,
            ]);
        });
    }

    public function deleteVisit(RepresentativeVisit $visit): void
    {
        $this->mrs->deleteVisit($visit);
    }

    public function representativeOptions(?User $user = null)
    {
        return $this->mrs->representativeOptions($user);
    }

    private function isRepresentativeUser(User $user): bool
    {
        return $this->mrs->isRepresentativeUser($user);
    }

    private function resolveRepresentativeId(array $data, User $user): int
    {
        if ($this->isRepresentativeUser($user)) {
            return (int) $user->medical_representative_id;
        }

        return (int) $data['medical_representative_id'];
    }
}
