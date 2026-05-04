<?php

namespace App\Modules\MR\DTOs;

use App\Modules\Base\DTOs\BaseDTO;

final readonly class RepresentativeVisitData extends BaseDTO
{
    public function __construct(
        public string $visitDate,
        public string $status,
        public ?int $medicalRepresentativeId = null,
        public ?int $employeeId = null,
        public ?int $customerId = null,
        public ?string $visitTime = null,
        public ?string $purpose = null,
        public float $orderValue = 0,
        public ?string $notes = null,
        public ?string $locationName = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
        public ?string $remarks = null,
    ) {}
}
