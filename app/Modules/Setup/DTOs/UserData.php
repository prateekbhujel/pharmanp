<?php

namespace App\Modules\Setup\DTOs;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Modules\Base\DTOs\BaseDTO;

final readonly class UserData extends BaseDTO
{
    public function __construct(
        public string $name,
        public string $email,
        public ?string $phone = null,
        public ?int $branchId = null,
        public ?int $medicalRepresentativeId = null,
        public bool $isOwner = false,
        public bool $isActive = true,
        public ?string $password = null,
        public array $roleNames = [],
    ) {}
}
