<?php

namespace App\Modules\Setup\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_owner || (bool) $this->user()?->can('users.manage');
    }

    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'search' => ['nullable', 'string', 'max:120'],
            'sort_field' => ['nullable', 'in:name,email,is_active,last_login_at,created_at'],
            'sort_order' => ['nullable', 'in:asc,desc'],
            'is_active' => ['nullable', 'boolean'],
            'role_name' => ['nullable', 'string', 'max:80'],
        ];
    }
}
