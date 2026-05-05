<?php

namespace App\Modules\Accounting\DTOs;

use App\Modules\Base\DTOs\BaseDTO;

final readonly class VoucherData extends BaseDTO
{
    public function __construct(
        public ?string $voucherDate = null,
        public ?string $voucherType = null,
        public ?string $notes = null,
        public array $entries = [],
    ) {}
}
