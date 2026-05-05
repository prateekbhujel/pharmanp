<?php

namespace App\Modules\Purchase\DTOs;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Modules\Base\DTOs\BaseDTO;

final readonly class PurchaseData extends BaseDTO
{
    public function __construct(
        public ?int $supplierId = null,
        public ?string $supplierInvoiceNo = null,
        public ?string $purchaseDate = null,
        public ?string $dueDate = null,
        public ?int $paymentModeId = null,
        public ?string $paymentType = null,
        public ?float $paidAmount = null,
        public ?string $notes = null,
        public array $items = [],
    ) {}
}
