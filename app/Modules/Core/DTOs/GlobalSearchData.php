<?php

namespace App\Modules\Core\DTOs;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use Illuminate\Http\Request;

final readonly class GlobalSearchData
{
    public function __construct(
        public string $query,
        public int $limit = 5,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            query: trim((string) $request->query('query', '')),
            limit: min(10, max(3, (int) $request->query('limit', 5))),
        );
    }
}
