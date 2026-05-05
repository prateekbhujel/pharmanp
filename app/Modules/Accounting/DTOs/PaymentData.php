<?php

namespace App\Modules\Accounting\DTOs;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Modules\Base\DTOs\BaseDTO;

final readonly class PaymentData extends BaseDTO
{
    public function __construct(
        public ?int $id = null,
        public ?string $paymentDate = null,
        public ?string $direction = null,
        public ?string $partyType = null,
        public ?int $partyId = null,
        public ?int $paymentModeId = null,
        public ?float $amount = null,
        public ?string $referenceNo = null,
        public ?string $notes = null,
        public array $allocations = [],
    ) {}
}
