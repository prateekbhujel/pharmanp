<?php

namespace App\Modules\Party\DTOs;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Modules\Base\DTOs\BaseDTO;

final readonly class PartyData extends BaseDTO
{
    public function __construct(
        public ?string $supplierCode = null,
        public ?string $customerCode = null,
        public ?int $supplierTypeId = null,
        public ?int $partyTypeId = null,
        public ?string $name = null,
        public ?string $contactPerson = null,
        public ?string $phone = null,
        public ?string $email = null,
        public ?string $panNumber = null,
        public ?string $address = null,
        public ?float $creditLimit = null,
        public ?float $openingBalance = null,
        public ?bool $isActive = null,
    ) {}
}
