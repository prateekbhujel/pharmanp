<?php

namespace App\Modules\Setup\DTOs;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use Illuminate\Http\Request;

final readonly class SetupTypeData
{
    public function __construct(
        public string $name,
        public ?string $code = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $validated = $request->validated();

        return new self(
            name: trim((string) $validated['name']),
            code: filled($validated['code'] ?? null) ? trim((string) $validated['code']) : null,
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'code' => $this->code,
        ];
    }
}
