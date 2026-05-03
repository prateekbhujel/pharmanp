<?php

namespace App\Modules\Reports\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @OA\Schema(
 *     schema="ReportRunRequest",
 *     title="Report Run Request",
 *     description="Shared server-side filter contract for operational, aging, expiry, target and accounting reports",
 *
 *     @OA\Property(property="from", type="string", nullable=true, example="2026-04-01"),
 *     @OA\Property(property="to", type="string", nullable=true, example="2026-04-30"),
 *     @OA\Property(property="page", type="integer", example=1),
 *     @OA\Property(property="per_page", type="integer", example=15),
 *     @OA\Property(property="search", type="string", nullable=true),
 *     @OA\Property(property="sort_by", type="string", nullable=true),
 *     @OA\Property(property="sort_order", type="string", enum={"asc", "desc"}, nullable=true)
 * )
 */
class ReportRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'from' => ['nullable', 'string', 'max:20'],
            'to' => ['nullable', 'string', 'max:20'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:5000'],
            'search' => ['nullable', 'string', 'max:160'],
            'sort_by' => ['nullable', 'string', 'max:80'],
            'sort_order' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }
}
