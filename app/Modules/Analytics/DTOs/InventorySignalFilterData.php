<?php

namespace App\Modules\Analytics\DTOs;

use App\Core\DTOs\TableQueryData;
use Illuminate\Http\Request;

final readonly class InventorySignalFilterData
{
    public function __construct(
        public ?int $companyId = null,
        public ?int $divisionId = null,
        public ?string $search = null,
        public ?string $signal = null,
        public int $perPage = TableQueryData::DEFAULT_PER_PAGE,
    ) {}

    public static function fromRequest(Request $request, ?int $perPage = null): self
    {
        return new self(
            companyId: $request->filled('company_id') ? $request->integer('company_id') : null,
            divisionId: $request->filled('division_id') ? $request->integer('division_id') : null,
            search: $request->filled('search') ? trim((string) $request->query('search')) : null,
            signal: $request->filled('signal') ? (string) $request->query('signal') : null,
            perPage: TableQueryData::normalizePerPage($perPage ?? $request->query('per_page')),
        );
    }
}
