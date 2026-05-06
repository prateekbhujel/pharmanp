<?php

namespace App\Modules\Setup\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * @OA\Schema(
 *     schema="CompleteSetupRequest",
 *     title="Complete Setup Request",
 *     description="Validated request contract for Complete Setup Request",
 *     type="object",
 *     additionalProperties=true
 * )
 */
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
            'store.name' => ['nullable', 'string', 'max:180'],
            'branch.name' => ['nullable', 'string', 'max:180'],
            'branch.code' => ['nullable', 'string', 'max:60'],
            'branch.address' => ['nullable', 'string', 'max:255'],
            'branch.phone' => ['nullable', 'string', 'max:40'],
            'areas' => ['nullable', 'array', 'max:20'],
            'areas.*.name' => ['required_with:areas', 'string', 'max:160'],
            'areas.*.code' => ['nullable', 'string', 'max:60'],
            'areas.*.district' => ['nullable', 'string', 'max:120'],
            'areas.*.province' => ['nullable', 'string', 'max:120'],
            'divisions' => ['nullable', 'array', 'max:20'],
            'divisions.*.name' => ['required_with:divisions', 'string', 'max:160'],
            'divisions.*.code' => ['nullable', 'string', 'max:60'],
            'payment_modes' => ['nullable', 'array', 'max:20'],
            'payment_modes.*.name' => ['required_with:payment_modes', 'string', 'max:160'],
            'payment_modes.*.data' => ['nullable', 'string', 'max:120'],
            'employees' => ['nullable', 'array', 'max:20'],
            'employees.*.name' => ['required_with:employees', 'string', 'max:180'],
            'employees.*.designation' => ['nullable', 'string', 'max:120'],
            'employees.*.phone' => ['nullable', 'string', 'max:40'],
            'employees.*.email' => ['nullable', 'email', 'max:180'],
            'store.phone' => ['nullable', 'string', 'max:40'],
            'store.address' => ['nullable', 'string', 'max:255'],
            'branding.app_name' => ['required', 'string', 'max:120'],
            'branding.logo_url' => ['nullable', 'string', 'max:255'],
            'branding.sidebar_logo_url' => ['nullable', 'string', 'max:255'],
            'branding.app_icon_url' => ['nullable', 'string', 'max:255'],
            'branding.favicon_url' => ['nullable', 'string', 'max:255'],
            'branding.logo_file' => ['nullable', 'image', 'max:2048'],
            'branding.sidebar_logo_file' => ['nullable', 'image', 'max:2048'],
            'branding.app_icon_file' => ['nullable', 'image', 'max:2048'],
            'branding.favicon_file' => ['nullable', 'mimes:ico,png,jpg,jpeg,svg,webp', 'max:1024'],
            'branding.accent_color' => ['nullable', 'string', 'max:20'],
            'branding.sidebar_default_collapsed' => ['sometimes', 'boolean'],
            'branding.show_breadcrumbs' => ['sometimes', 'boolean'],
            'branding.country_code' => ['required', 'string', 'size:2'],
            'branding.currency_symbol' => ['required', 'string', 'max:20'],
            'branding.calendar_type' => ['required', 'in:ad,bs'],
            'fiscal_year.name' => ['required', 'string', 'max:80'],
            'fiscal_year.starts_on' => ['required', 'date'],
            'fiscal_year.ends_on' => ['required', 'date', 'after:fiscal_year.starts_on'],
            'admin.name' => ['required', 'string', 'max:180'],
            'admin.email' => ['required', 'email', 'max:180', 'unique:users,email'],
            'admin.password' => ['required', 'confirmed', Password::min(8)],
            'seed_demo' => ['sometimes', 'boolean'],
        ];
    }
}
