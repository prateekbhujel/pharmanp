<?php

namespace App\Modules\MR\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="RepresentativeVisitResource",
 *     title="Representative Visit Resource",
 *     description="MR visit response with party, visit date, visit time and location name",
 *
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="medical_representative_id", type="integer", example=1),
 *     @OA\Property(property="customer_id", type="integer", nullable=true),
 *     @OA\Property(property="visit_date", type="string", format="date"),
 *     @OA\Property(property="visit_time", type="string", example="10:30"),
 *     @OA\Property(property="location_name", type="string", nullable=true)
 * )
 */
class RepresentativeVisitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return (array) $this->resource;
    }
}
