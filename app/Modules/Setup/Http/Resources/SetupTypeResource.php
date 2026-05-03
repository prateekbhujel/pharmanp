<?php

namespace App\Modules\Setup\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="SetupTypeResource",
 *     title="Setup Type Resource",
 *
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Retailer"),
 *     @OA\Property(property="code", type="string", nullable=true, example="RTL")
 * )
 */
class SetupTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'display_name' => $this->display_name,
            'display_code' => $this->display_code,
        ];
    }
}
