<?php

namespace App\Modules\Reports\DTOs;

use App\Core\DTOs\TableQueryData;
use Illuminate\Http\Request;

final readonly class ReportQueryData
{
    public function __construct(
        public ?string $from = null,
        public ?string $to = null,
        public int $perPage = TableQueryData::DEFAULT_PER_PAGE,
        public ?int $tenantId = null,
        public ?int $companyId = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            from: $request->filled('from') ? (string) $request->query('from') : null,
            to: $request->filled('to') ? (string) $request->query('to') : null,
            perPage: TableQueryData::perPageFromRequest($request),
            tenantId: $request->user()?->tenant_id,
            companyId: $request->user()?->company_id,
        );
    }
}
