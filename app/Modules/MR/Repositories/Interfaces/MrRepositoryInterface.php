<?php

namespace App\Modules\MR\Repositories\Interfaces;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Core\DTOs\TableQueryData;
use App\Models\User;
use App\Modules\MR\Models\MedicalRepresentative;
use App\Modules\MR\Models\RepresentativeVisit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface MrRepositoryInterface
{
    public function representatives(TableQueryData $table, ?User $user = null): LengthAwarePaginator;

    public function visits(TableQueryData $table, ?User $user = null): LengthAwarePaginator;

    public function createRepresentative(array $data): MedicalRepresentative;

    public function updateRepresentative(MedicalRepresentative $representative, array $data): MedicalRepresentative;

    public function deleteRepresentative(MedicalRepresentative $representative): void;

    public function createVisit(array $data): RepresentativeVisit;

    public function updateVisit(RepresentativeVisit $visit, array $data): RepresentativeVisit;

    public function deleteVisit(RepresentativeVisit $visit): void;

    public function representativeOptions(?User $user = null): Collection;

    public function isRepresentativeUser(User $user): bool;
}
