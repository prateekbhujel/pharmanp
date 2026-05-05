<?php

namespace App\Modules\Reports\Http\Resources;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="ReportRowResource",
 *     title="Report Row Resource",
 *     description="Generic report row response. Concrete columns are report-specific and documented by tag/endpoint.",
 *     type="object",
 *     additionalProperties=true
 * )
 */
class ReportRowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return (array) $this->resource;
    }
}
