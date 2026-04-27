<?php

namespace App\Modules\Setup\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class FiscalYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) ($user?->is_owner || $user?->can('settings.manage'));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
            'is_current' => ['nullable', 'boolean'],
            'status' => ['required', 'in:open,closed'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->boolean('is_current') && $this->input('status') === 'closed') {
                $validator->errors()->add('status', 'Current fiscal year must remain open.');
            }
        });
    }
}
