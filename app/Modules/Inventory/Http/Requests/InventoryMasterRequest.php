<?php

namespace App\Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InventoryMasterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_owner
            || $this->user()?->can('inventory.products.create')
            || $this->user()?->can('inventory.products.update');
    }

    public function rules(): array
    {
        $master = $this->route('master');

        return match ($master) {
            'companies' => [
                'name' => ['required', 'string', 'max:180'],
                'legal_name' => ['nullable', 'string', 'max:180'],
                'pan_number' => ['nullable', 'string', 'max:60'],
                'phone' => ['nullable', 'string', 'max:40'],
                'email' => ['nullable', 'email', 'max:180'],
                'address' => ['nullable', 'string', 'max:255'],
                'company_type' => ['nullable', 'string', 'max:60'],
                'default_cc_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'is_active' => ['sometimes', 'boolean'],
            ],
            'units' => [
                'name' => ['required', 'string', 'max:120'],
                'code' => ['nullable', 'string', 'max:40'],
                'type' => ['required', Rule::in(['purchase', 'sale', 'both'])],
                'factor' => ['nullable', 'numeric', 'min:0.0001', 'max:999999'],
                'company_id' => ['nullable', 'integer', 'exists:companies,id'],
                'is_active' => ['sometimes', 'boolean'],
            ],
            'categories' => [
                'name' => ['required', 'string', 'max:120'],
                'code' => ['nullable', 'string', 'max:40'],
                'company_id' => ['nullable', 'integer', 'exists:companies,id'],
                'is_active' => ['sometimes', 'boolean'],
            ],
            default => [],
        };
    }
}
