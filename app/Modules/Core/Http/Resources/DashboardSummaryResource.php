<?php

namespace App\Modules\Core\Http\Resources;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="DashboardSummaryResource",
 *     title="Dashboard Summary Resource",
 *     description="Operational KPI, alert, recent sales, recent purchase and field-force dashboard payload",
 *
 *     @OA\Property(property="stats", type="object"),
 *     @OA\Property(property="top_products", type="array", @OA\Items(type="object")),
 *     @OA\Property(property="recent_sales", type="array", @OA\Items(type="object")),
 *     @OA\Property(property="recent_purchases", type="array", @OA\Items(type="object"))
 * )
 */
class DashboardSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return (array) $this->resource;
    }
}
