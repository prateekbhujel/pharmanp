<?php

namespace App\Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuickCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:180',
                Rule::unique('companies', 'name')->whereNull('deleted_at'),
            ],
            'phone' => ['nullable', 'string', 'max:40'],
            'pan_number' => ['nullable', 'string', 'max:60'],
            'company_type' => ['nullable', 'in:pharmacy,distributor,manufacturer,supplier'],
        ];
    }
}
