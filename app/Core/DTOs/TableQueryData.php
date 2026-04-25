<?php

namespace App\Core\DTOs;

use Illuminate\Http\Request;

final readonly class TableQueryData
{
    public function __construct(
        public int $page = 1,
        public int $perPage = 15,
        public ?string $search = null,
        public string $sortField = 'created_at',
        public string $sortOrder = 'desc',
        public array $filters = [],
    ) {}

    public static function fromRequest(Request $request, array $allowedFilters = []): self
    {
        $filters = [];

        foreach ($allowedFilters as $filter) {
            $value = $request->input($filter);

            if (is_string($value)) {
                $normalized = strtolower(trim($value));

                if (in_array($normalized, ['true', '1'], true)) {
                    $value = true;
                } elseif (in_array($normalized, ['false', '0'], true)) {
                    $value = false;
                }
            }

            if ($value !== null && $value !== '') {
                $filters[$filter] = $value;
            }
        }

        return new self(
            page: max(1, (int) $request->input('page', 1)),
            perPage: min(100, max(5, (int) $request->input('per_page', 15))),
            search: filled($request->input('search')) ? trim((string) $request->input('search')) : null,
            sortField: (string) $request->input('sort_field', 'created_at'),
            sortOrder: strtolower((string) $request->input('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc',
            filters: $filters,
        );
    }
}
