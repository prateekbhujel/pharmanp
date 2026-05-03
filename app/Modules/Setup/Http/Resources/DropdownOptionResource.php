<?php

namespace App\Modules\Setup\Http\Resources;

use App\Core\Support\AssetUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="DropdownOptionResource",
 *     title="Dropdown Option Resource",
 *
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="alias", type="string", example="payment_mode"),
 *     @OA\Property(property="alias_label", type="string", example="Payment Mode"),
 *     @OA\Property(property="name", type="string", example="Cash"),
 *     @OA\Property(property="data", type="string", nullable=true),
 *     @OA\Property(property="status", type="integer", example=1),
 *     @OA\Property(property="is_active", type="boolean", example=true)
 * )
 */
class DropdownOptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $meta = $this->meta ?? [];

        if (! empty($meta['qr_url'])) {
            $meta['qr_url'] = AssetUrl::resolve($meta['qr_url']);
        }

        return [
            'id' => $this->id,
            'alias' => $this->alias,
            'alias_label' => $this->alias_label,
            'name' => $this->name,
            'data' => $this->data,
            'meta' => $meta,
            'status' => (int) $this->status,
            'is_active' => (bool) $this->status,
        ];
    }
}
