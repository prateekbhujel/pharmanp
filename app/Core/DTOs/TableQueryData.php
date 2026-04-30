<?php

namespace App\Core\DTOs;

use Illuminate\Http\Request;

final readonly class TableQueryData
{
    public function __construct(
        public int $page = 1,
        public int $perPage = 20,
        public ?string $search = null,
        public string $sortField = 'updated_at',
        public string $sortOrder = 'desc',
        public array $filters = [],
    ) {}

    public static function fromRequest(Request $request, array $allowedFilters = []): self
    {
        $filters = [];

        foreach ($allowedFilters as $filter) {
            $value = $request->input($filter);

            if ($value !== null && $value !== '') {
                $filters[$filter] = $value;
            }
        }

        return new self(
            page: max(1, (int) $request->input('page', 1)),
            perPage: min(100, max(5, (int) $request->input('per_page', 20))),
            search: filled($request->input('search')) ? trim((string) $request->input('search')) : null,
            sortField: (string) $request->input('sort_field', 'updated_at'),
            sortOrder: strtolower((string) $request->input('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc',
            filters: $filters,
        );
    }
}
