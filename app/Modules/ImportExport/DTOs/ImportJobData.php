<?php

namespace App\Modules\ImportExport\DTOs;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Modules\Base\DTOs\BaseDTO;

final readonly class ImportJobData extends BaseDTO
{
    public function __construct(
        public string $target,
        public string $originalFilename,
        public string $storedPath,
        public array $detectedColumns = [],
        public int $totalRows = 0,
        public int $validRows = 0,
        public int $invalidRows = 0,
        public string $status = 'previewed',
        public ?int $tenantId = null,
        public ?int $companyId = null,
        public ?int $storeId = null,
        public ?int $createdBy = null,
    ) {}
}
