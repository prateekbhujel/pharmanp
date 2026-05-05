<?php

namespace App\Modules\MR\DTOs;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Modules\Base\DTOs\BaseDTO;

final readonly class MedicalRepresentativeData extends BaseDTO
{
    public function __construct(
        public string $name,
        public ?int $branchId = null,
        public ?int $employeeId = null,
        public ?int $areaId = null,
        public ?int $divisionId = null,
        public ?string $employeeCode = null,
        public ?string $phone = null,
        public ?string $email = null,
        public float $monthlyTarget = 0,
        public bool $isActive = true,
    ) {}
}
