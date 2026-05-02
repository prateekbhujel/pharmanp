<?php

namespace App\Modules\Setup\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
            ]),
            'employee_code' => $this->employee_code,
            'name' => $this->name,
            'designation' => $this->designation,
            'branch_id' => $this->branch_id,
            'area_id' => $this->area_id,
            'division_id' => $this->division_id,
            'reports_to_employee_id' => $this->reports_to_employee_id,
            'branch' => $this->whenLoaded('branch', fn () => [
                'id' => $this->branch?->id,
                'name' => $this->branch?->name,
                'code' => $this->branch?->code,
            ]),
            'area' => $this->whenLoaded('area', fn () => [
                'id' => $this->area?->id,
                'name' => $this->area?->name,
                'code' => $this->area?->code,
            ]),
            'division' => $this->whenLoaded('division', fn () => [
                'id' => $this->division?->id,
                'name' => $this->division?->name,
                'code' => $this->division?->code,
            ]),
            'manager' => $this->whenLoaded('manager', fn () => [
                'id' => $this->manager?->id,
                'name' => $this->manager?->name,
                'employee_code' => $this->manager?->employee_code,
            ]),
            'phone' => $this->phone,
            'email' => $this->email,
            'joined_on' => $this->joined_on?->toDateString(),
            'is_active' => (bool) $this->is_active,
            'deleted_at' => $this->deleted_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
