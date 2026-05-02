<?php

namespace App\Modules\MR\Contracts;

use App\Core\DTOs\TableQueryData;
use App\Models\User;
use App\Modules\MR\Models\MedicalRepresentative;
use App\Modules\MR\Models\RepresentativeVisit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface MrManagementServiceInterface
{
    public function representatives(TableQueryData $table, ?User $user = null): LengthAwarePaginator;

    public function visits(TableQueryData $table, ?User $user = null): LengthAwarePaginator;

    public function createRepresentative(array $data, User $user): MedicalRepresentative;

    public function updateRepresentative(MedicalRepresentative $representative, array $data, User $user): MedicalRepresentative;

    public function deleteRepresentative(MedicalRepresentative $representative, User $user): void;

    public function createVisit(array $data, User $user): RepresentativeVisit;

    public function updateVisit(RepresentativeVisit $visit, array $data, User $user): RepresentativeVisit;

    public function deleteVisit(RepresentativeVisit $visit): void;
}
