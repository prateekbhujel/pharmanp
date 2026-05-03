<?php

namespace App\Modules\ImportExport\Http\Resources;

use App\Modules\ImportExport\Services\ImportPreviewService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="ImportJobResource",
 *     title="Import Job Resource",
 *
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="target", type="string", example="products"),
 *     @OA\Property(property="status", type="string", example="previewed"),
 *     @OA\Property(property="original_filename", type="string", example="products.xlsx"),
 *     @OA\Property(property="total_rows", type="integer", example=120),
 *     @OA\Property(property="valid_rows", type="integer", example=118),
 *     @OA\Property(property="invalid_rows", type="integer", example=2)
 * )
 */
class ImportJobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $fields = app(ImportPreviewService::class)->targetFields();

        return [
            'id' => $this->id,
            'target' => $this->target,
            'status' => $this->status,
            'original_filename' => $this->original_filename,
            'detected_columns' => $this->detected_columns,
            'system_fields' => $fields[$this->target] ?? [],
            'required_fields' => app(ImportPreviewService::class)->requiredFields($this->target),
            'total_rows' => $this->total_rows,
            'valid_rows' => $this->valid_rows,
            'invalid_rows' => $this->invalid_rows,
            'rows' => $this->rows->sortBy('row_number')->take(50)->map(fn ($row): array => [
                'row_number' => $row->row_number,
                'raw_data' => $row->raw_data,
                'errors' => $row->errors,
                'status' => $row->status,
            ])->values(),
        ];
    }
}
