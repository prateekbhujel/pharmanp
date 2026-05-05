<?php

namespace App\Modules\Party\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="CustomerLedgerResource",
 *     title="Customer Ledger Resource",
 *
 *     @OA\Property(property="customer", type="object"),
 *     @OA\Property(property="invoices", type="array", @OA\Items(type="object")),
 *     @OA\Property(property="returns", type="array", @OA\Items(type="object")),
 *     @OA\Property(property="payments", type="array", @OA\Items(type="object")),
 *     @OA\Property(property="summary", type="object"),
 *     @OA\Property(property="filters", type="object")
 * )
 */
class CustomerLedgerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
