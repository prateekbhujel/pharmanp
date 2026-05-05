<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @OA\Schema(
 *     schema="QuickUnitRequest",
 *     title="Quick Unit Request",
 *     description="Validated request contract for Quick Unit Request",
 *     type="object",
 *     additionalProperties=true
 * )
 */
class QuickUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->input('company_id', $this->user()?->company_id);

        return [
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('units', 'name')
                    ->where(fn ($query) => $query->where('company_id', $companyId)->whereNull('deleted_at')),
            ],
            'code' => ['nullable', 'string', 'max:30'],
            'type' => ['nullable', 'in:purchase,sale,both'],
            'factor' => ['nullable', 'numeric', 'min:0.0001', 'max:999999'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
