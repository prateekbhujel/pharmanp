<?php

namespace App\Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuickUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_owner || (bool) $this->user()?->can('inventory.masters.manage');
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
        ];
    }
}
