<?php

namespace App\Modules\Setup\DTOs;

use Illuminate\Http\Request;

final readonly class DropdownOptionData
{
    public function __construct(
        public string $alias,
        public string $name,
        public ?string $data = null,
        public array $meta = [],
        public ?bool $status = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $validated = $request->validated();

        return new self(
            alias: (string) $validated['alias'],
            name: trim((string) $validated['name']),
            data: filled($validated['data'] ?? null) ? trim((string) $validated['data']) : null,
            meta: array_filter($validated['meta'] ?? [], fn (mixed $value): bool => filled($value)),
            status: array_key_exists('status', $validated) ? (bool) $validated['status'] : null,
        );
    }

    public function toArray(): array
    {
        return [
            'alias' => $this->alias,
            'name' => $this->name,
            'data' => $this->data,
            'meta' => $this->meta,
            'status' => $this->status,
        ];
    }
}
