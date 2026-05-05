<?php

namespace App\Modules\Inventory\Http\Resources;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Core\Support\AssetUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="ProductResource",
 *     title="Product Resource",
 *     description="PharmaNP Product Resource response contract",
 *
 *     @OA\Property(property="id", type="integer", nullable=true, example=1),
 *     @OA\Property(property="created_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="updated_at", type="string", format="date-time", nullable=true)
 * )
 */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'product_code' => $this->product_code,
            'hs_code' => $this->hs_code,
            'generic_name' => $this->generic_name,
            'composition' => $this->composition,
            'group_name' => $this->group_name,
            'strength' => $this->strength,
            'manufacturer_name' => $this->manufacturer_name,
            'packaging_type' => $this->packaging_type,
            'conversion' => (float) $this->conversion,
            'rack_location' => $this->rack_location,
            'previous_price' => (float) $this->previous_price,
            'mrp' => (float) $this->mrp,
            'purchase_price' => (float) $this->purchase_price,
            'selling_price' => (float) $this->selling_price,
            'cc_rate' => (float) $this->cc_rate,
            'discount_percent' => (float) $this->discount_percent,
            'reorder_level' => (int) $this->reorder_level,
            'reorder_quantity' => (int) $this->reorder_quantity,
            'is_batch_tracked' => (bool) $this->is_batch_tracked,
            'is_active' => (bool) $this->is_active,
            'stock_on_hand' => (float) ($this->stock_on_hand ?? 0),
            'notes' => $this->notes,
            'keywords' => $this->keywords,
            'description' => $this->description,
            'image_path' => $this->image_path,
            'image_url' => $this->image_path ? AssetUrl::resolve(AssetUrl::publicStorage($this->image_path)) : null,
            'deleted_at' => $this->deleted_at?->toISOString(),
            'company' => $this->whenLoaded('company', fn () => [
                'id' => $this->company?->id,
                'name' => $this->company?->name,
            ]),
            'unit' => $this->whenLoaded('unit', fn () => [
                'id' => $this->unit?->id,
                'name' => $this->unit?->name,
            ]),
            'division' => $this->whenLoaded('division', fn () => [
                'id' => $this->division?->id,
                'name' => $this->division?->name,
                'code' => $this->division?->code,
            ]),
            'batches' => $this->whenLoaded('batches', fn () => $this->batches->map(fn ($batch) => [
                'id' => $batch->id,
                'batch_no' => $batch->batch_no,
                'barcode' => $batch->barcode,
                'expires_at' => $batch->expires_at?->toDateString(),
                'quantity_available' => (float) $batch->quantity_available,
                'purchase_price' => (float) $batch->purchase_price,
                'mrp' => (float) $batch->mrp,
            ])->values()),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
