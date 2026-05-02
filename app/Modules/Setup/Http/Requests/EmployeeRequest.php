<?php

namespace App\Modules\Setup\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->is_owner || $this->user()?->can('users.manage') || $this->user()?->can('mr.manage'));
    }

    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'area_id' => ['nullable', 'integer', 'exists:areas,id'],
            'division_id' => ['nullable', 'integer', 'exists:divisions,id'],
            'reports_to_employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'employee_code' => [
                'nullable',
                'string',
                'max:80',
                Rule::unique('employees', 'employee_code')
                    ->where(fn ($query) => $query
                        ->where('company_id', $this->user()?->company_id)
                        ->whereNull('deleted_at'))
                    ->ignore($this->route('employee')),
            ],
            'name' => ['required', 'string', 'max:180'],
            'designation' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'joined_on' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
