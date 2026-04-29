<?php

namespace App\Modules\Setup\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'is_active' => (bool) $this->is_active,
            'is_owner' => (bool) $this->is_owner,
            'tenant_id' => $this->tenant_id,
            'company_id' => $this->company_id,
            'store_id' => $this->store_id,
            'branch_id' => $this->branch_id,
            'branch' => $this->whenLoaded('branch', fn () => [
                'id' => $this->branch?->id,
                'name' => $this->branch?->name,
                'code' => $this->branch?->code,
                'type' => $this->branch?->type,
            ]),
            'medical_representative_id' => $this->medical_representative_id,
            'medical_representative' => $this->whenLoaded('medicalRepresentative', fn () => [
                'id' => $this->medicalRepresentative?->id,
                'name' => $this->medicalRepresentative?->name,
            ]),
            'role_names' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')->values()->all()),
            'last_login_at' => $this->last_login_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
