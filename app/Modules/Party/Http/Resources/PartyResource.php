<?php

namespace App\Modules\Party\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PartyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'contact_person' => $this->contact_person,
            'phone' => $this->phone,
            'email' => $this->email,
            'pan_number' => $this->pan_number,
            'address' => $this->address,
            'credit_limit' => (float) ($this->credit_limit ?? 0),
            'opening_balance' => (float) $this->opening_balance,
            'current_balance' => (float) $this->current_balance,
            'is_active' => (bool) $this->is_active,
            'deleted_at' => $this->deleted_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
