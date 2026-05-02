<?php

namespace App\Modules\Setup\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DivisionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'notes' => $this->notes,
            'is_active' => (bool) $this->is_active,
            'products_count' => (int) ($this->products_count ?? 0),
            'employees_count' => (int) ($this->employees_count ?? 0),
            'deleted_at' => $this->deleted_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
