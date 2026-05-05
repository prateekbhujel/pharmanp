<?php

namespace App\Modules\Purchase\DTOs;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Modules\Base\DTOs\BaseDTO;

final readonly class PurchaseReturnData extends BaseDTO
{
    public function __construct(
        public ?int $purchaseId = null,
        public ?int $supplierId = null,
        public ?string $returnType = null,
        public ?string $returnDate = null,
        public ?string $notes = null,
        public array $items = [],
    ) {}
}
