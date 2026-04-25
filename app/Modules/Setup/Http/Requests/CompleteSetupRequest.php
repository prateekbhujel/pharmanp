<?php

namespace App\Modules\Setup\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class CompleteSetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company.name' => ['required', 'string', 'max:180'],
            'company.legal_name' => ['nullable', 'string', 'max:180'],
            'company.pan_number' => ['nullable', 'string', 'max:60'],
            'company.phone' => ['nullable', 'string', 'max:40'],
            'company.email' => ['nullable', 'email', 'max:180'],
            'company.address' => ['nullable', 'string', 'max:255'],
            'store.name' => ['required', 'string', 'max:180'],
            'store.phone' => ['nullable', 'string', 'max:40'],
            'store.address' => ['nullable', 'string', 'max:255'],
            'admin.name' => ['required', 'string', 'max:180'],
            'admin.email' => ['required', 'email', 'max:180', 'unique:users,email'],
            'admin.password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
            'seed_demo' => ['sometimes', 'boolean'],
        ];
    }
}
