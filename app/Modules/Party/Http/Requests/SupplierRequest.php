<?php

namespace App\Modules\Party\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_owner
            || (bool) $this->user()?->can('party.suppliers.manage')
            || (bool) $this->user()?->can('purchase.entries.create')
            || (bool) $this->user()?->can('purchase.orders.manage');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:180'],
            'contact_person' => ['nullable', 'string', 'max:180'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:180'],
            'pan_number' => ['nullable', 'string', 'max:60'],
            'address' => ['nullable', 'string', 'max:255'],
            'opening_balance' => ['nullable', 'numeric', 'min:-999999999', 'max:999999999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
