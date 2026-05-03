<?php

namespace App\Core\DTOs;

use Illuminate\Http\Request;

final readonly class TableQueryData
{
    public const DEFAULT_PER_PAGE = 15;

    public const MIN_PER_PAGE = 5;

    public const MAX_PER_PAGE = 100;

    public function __construct(
        public int $page = 1,
        public int $perPage = self::DEFAULT_PER_PAGE,
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
            perPage: self::perPageFromRequest($request),
            search: filled($request->input('search')) ? trim((string) $request->input('search')) : null,
            sortField: (string) $request->input('sort_field', 'updated_at'),
            sortOrder: strtolower((string) $request->input('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc',
            filters: $filters,
        );
    }

    public static function perPageFromRequest(Request $request, int $default = self::DEFAULT_PER_PAGE, int $max = self::MAX_PER_PAGE): int
    {
        return self::normalizePerPage($request->input('per_page', $default), $default, $max);
    }

    public static function normalizePerPage(mixed $value, int $default = self::DEFAULT_PER_PAGE, int $max = self::MAX_PER_PAGE): int
    {
        $perPage = is_numeric($value) ? (int) $value : $default;

        return min($max, max(self::MIN_PER_PAGE, $perPage));
    }
}
