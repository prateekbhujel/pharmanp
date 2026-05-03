<?php

namespace App\Modules\Sales\DTOs;

use App\Modules\Base\DTOs\BaseDTO;

final readonly class SalesInvoiceData extends BaseDTO
{
    public function __construct(
        public ?int $customerId = null,
        public ?int $medicalRepresentativeId = null,
        public ?string $invoiceDate = null,
        public ?string $dueDate = null,
        public ?string $saleType = null,
        public ?int $paymentModeId = null,
        public ?string $paymentType = null,
        public ?float $paidAmount = null,
        public ?string $notes = null,
        public array $items = [],
    ) {}
}
