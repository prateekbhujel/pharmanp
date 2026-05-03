<?php

namespace App\Modules\MR\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="MedicalRepresentativeResource",
 *     title="Medical Representative Resource",
 *     description="Field-force employee, branch, area, division and target tracking response",
 *
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Pratik Bhujel"),
 *     @OA\Property(property="branch_id", type="integer", nullable=true),
 *     @OA\Property(property="area_id", type="integer", nullable=true),
 *     @OA\Property(property="division_id", type="integer", nullable=true),
 *     @OA\Property(property="is_active", type="boolean", example=true)
 * )
 */
class MedicalRepresentativeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return (array) $this->resource;
    }
}
