<?php

namespace App\Modules\Party\Http\Requests;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @OA\Schema(
 *     schema="PartyIndexRequest",
 *     title="Party Index Request",
 *     description="Validated request contract for Party Index Request",
 *     type="object",
 *     additionalProperties=true
 * )
 */
class PartyIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:120'],
            'sort_field' => ['nullable', 'string', 'max:80'],
            'sort_order' => ['nullable', 'in:asc,desc'],
            'is_active' => ['nullable', 'boolean'],
            'deleted' => ['nullable', 'boolean'],
        ];
    }
}
