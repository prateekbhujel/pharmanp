<?php

namespace App\Modules\Setup\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TargetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'target_type' => $this->target_type,
            'target_period' => $this->target_period,
            'target_level' => $this->target_level,
            'target_amount' => (float) $this->target_amount,
            'target_quantity' => (float) $this->target_quantity,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'status' => $this->status,
            'branch_id' => $this->branch_id,
            'area_id' => $this->area_id,
            'division_id' => $this->division_id,
            'employee_id' => $this->employee_id,
            'product_id' => $this->product_id,
            'notes' => $this->notes,
            'deleted_at' => $this->deleted_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
