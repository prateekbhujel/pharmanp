<?php

namespace App\Modules\Purchase\DTOs;

use App\Modules\Base\DTOs\BaseDTO;

final readonly class PurchaseOrderData extends BaseDTO
{
    public function __construct(
        public ?int $supplierId = null,
        public ?string $orderDate = null,
        public ?string $expectedDate = null,
        public ?float $paidAmount = null,
        public ?int $paymentModeId = null,
        public ?string $paymentType = null,
        public ?string $dueDate = null,
        public ?string $supplierInvoiceNo = null,
        public ?string $purchaseDate = null,
        public ?string $notes = null,
        public array $items = [],
    ) {}
}
