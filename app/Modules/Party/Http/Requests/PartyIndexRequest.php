<?php

namespace App\Modules\Party\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PartyIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_owner
            || (bool) $this->user()?->can('party.suppliers.view')
            || (bool) $this->user()?->can('party.customers.view');
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
        ];
    }
}
