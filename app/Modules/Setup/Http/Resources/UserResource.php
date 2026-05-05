<?php

namespace App\Modules\Setup\Http\Resources;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="UserResource",
 *     title="User Resource",
 *     description="PharmaNP User Resource response contract",
 *
 *     @OA\Property(property="id", type="integer", nullable=true, example=1),
 *     @OA\Property(property="created_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="updated_at", type="string", format="date-time", nullable=true)
 * )
 */
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
