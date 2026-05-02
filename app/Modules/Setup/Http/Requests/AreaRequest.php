<?php

namespace App\Modules\Setup\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AreaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->is_owner || $this->user()?->can('settings.manage') || $this->user()?->can('mr.manage'));
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'name' => ['required', 'string', 'max:160'],
            'code' => [
                'nullable',
                'string',
                'max:60',
                Rule::unique('areas', 'code')
                    ->where(fn ($query) => $query
                        ->where('company_id', $this->user()?->company_id)
                        ->whereNull('deleted_at'))
                    ->ignore($this->route('area')),
            ],
            'district' => ['nullable', 'string', 'max:120'],
            'province' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
