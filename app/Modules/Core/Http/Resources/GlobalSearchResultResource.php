<?php

namespace App\Modules\Core\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="GlobalSearchResultResource",
 *     title="Global Search Result",
 *
 *     @OA\Property(property="key", type="string", example="product-1"),
 *     @OA\Property(property="label", type="string", example="Paracetamol 500mg"),
 *     @OA\Property(property="description", type="string", example="SKU: PARA-500 | Stock: 120.000"),
 *     @OA\Property(property="type", type="string", example="Product"),
 *     @OA\Property(property="route", type="string", example="/app/inventory/products?id=1")
 * )
 */
class GlobalSearchResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'key' => $this['key'],
            'label' => $this['label'],
            'description' => $this['description'],
            'type' => $this['type'],
            'route' => $this['route'],
        ];
    }
}
