<?php

namespace App\Modules\MR\Http\Resources;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

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
 *     @OA\Property(property="location_name", type="string", nullable=true),
 *     @OA\Property(property="has_coordinates", type="boolean", example=true),
 *     @OA\Property(property="map_embed_url", type="string", nullable=true),
 *     @OA\Property(property="map_view_url", type="string", nullable=true)
 * )
 */
class RepresentativeVisitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $latitude = $this->latitude;
        $longitude = $this->longitude;
        $hasCoordinates = $latitude !== null && $longitude !== null;

        return [
            'id' => $this->id,
            'medical_representative_id' => $this->medical_representative_id,
            'employee_id' => $this->employee_id,
            'customer_id' => $this->customer_id,
            'visit_date' => $this->visit_date?->toDateString(),
            'visit_time' => $this->visit_time,
            'status' => $this->status,
            'purpose' => $this->purpose,
            'location_name' => $this->location_name,
            'order_value' => (float) $this->order_value,
            'notes' => $this->notes,
            'remarks' => $this->remarks,
            'has_coordinates' => $hasCoordinates,
            'map_embed_url' => $hasCoordinates ? $this->openStreetMapEmbedUrl((float) $latitude, (float) $longitude) : null,
            'map_view_url' => $hasCoordinates ? $this->openStreetMapViewUrl((float) $latitude, (float) $longitude) : null,
            'medical_representative' => $this->whenLoaded('medicalRepresentative', fn (): ?array => $this->medicalRepresentative ? [
                'id' => $this->medicalRepresentative->id,
                'name' => $this->medicalRepresentative->name,
            ] : null),
            'employee' => $this->whenLoaded('employee', fn (): ?array => $this->employee ? [
                'id' => $this->employee->id,
                'name' => $this->employee->name,
                'employee_code' => $this->employee->employee_code,
            ] : null),
            'customer' => $this->whenLoaded('customer', fn (): ?array => $this->customer ? [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
            ] : null),
        ];
    }

    private function openStreetMapEmbedUrl(float $latitude, float $longitude): string
    {
        $padding = 0.01;

        return sprintf(
            'https://www.openstreetmap.org/export/embed.html?bbox=%F,%F,%F,%F&layer=mapnik&marker=%F,%F',
            $longitude - $padding,
            $latitude - $padding,
            $longitude + $padding,
            $latitude + $padding,
            $latitude,
            $longitude,
        );
    }

    private function openStreetMapViewUrl(float $latitude, float $longitude): string
    {
        return sprintf(
            'https://www.openstreetmap.org/?mlat=%F&mlon=%F#map=15/%F/%F',
            $latitude,
            $longitude,
            $latitude,
            $longitude,
        );
    }
}
