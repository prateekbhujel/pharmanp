<?php

namespace App\Modules\Setup\Http\Requests;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @OA\Schema(
 *     schema="FiscalYearIndexRequest",
 *     title="Fiscal Year Index Request",
 *
 *     @OA\Property(property="search", type="string", nullable=true),
 *     @OA\Property(property="sort_field", type="string", nullable=true),
 *     @OA\Property(property="sort_order", type="string", enum={"asc", "desc"}, nullable=true),
 *     @OA\Property(property="page", type="integer", example=1),
 *     @OA\Property(property="per_page", type="integer", example=15)
 * )
 */
class FiscalYearIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_owner || (bool) $this->user()?->can('settings.manage');
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'sort_field' => ['nullable', Rule::in(['name', 'starts_on', 'ends_on', 'status', 'created_at', 'updated_at'])],
            'sort_order' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ];
    }
}
