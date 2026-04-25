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
            'branding.app_name' => ['required', 'string', 'max:120'],
            'branding.logo_url' => ['nullable', 'string', 'max:255'],
            'branding.sidebar_logo_url' => ['nullable', 'string', 'max:255'],
            'branding.accent_color' => ['nullable', 'string', 'max:20'],
            'branding.layout' => ['required', 'in:vertical,horizontal'],
            'branding.sidebar_default_collapsed' => ['sometimes', 'boolean'],
            'fiscal_year.name' => ['required', 'string', 'max:80'],
            'fiscal_year.starts_on' => ['required', 'date'],
            'fiscal_year.ends_on' => ['required', 'date', 'after:fiscal_year.starts_on'],
            'admin.name' => ['required', 'string', 'max:180'],
            'admin.email' => ['required', 'email', 'max:180', 'unique:users,email'],
            'admin.password' => ['required', 'confirmed', Password::min(4)->letters()],
            'seed_demo' => ['sometimes', 'boolean'],
        ];
    }
}
