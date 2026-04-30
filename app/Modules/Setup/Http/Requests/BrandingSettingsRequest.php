<?php

namespace App\Modules\Setup\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BrandingSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_owner || $this->user()?->can('setup.manage');
    }

    public function rules(): array
    {
        return [
            'app_name' => ['required', 'string', 'max:120'],
            'logo_url' => ['nullable', 'string', 'max:255'],
            'sidebar_logo_url' => ['nullable', 'string', 'max:255'],
            'app_icon_url' => ['nullable', 'string', 'max:255'],
            'favicon_url' => ['nullable', 'string', 'max:255'],
            'logo_file' => ['nullable', 'image', 'max:2048'],
            'sidebar_logo_file' => ['nullable', 'image', 'max:2048'],
            'app_icon_file' => ['nullable', 'image', 'max:2048'],
            'favicon_file' => ['nullable', 'mimes:ico,png,jpg,jpeg,svg,webp', 'max:1024'],
            'accent_color' => ['nullable', 'string', 'max:20'],
            'sidebar_default_collapsed' => ['sometimes', 'boolean'],
            'show_breadcrumbs' => ['sometimes', 'boolean'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'currency_symbol' => ['nullable', 'string', 'max:20'],
            'calendar_type' => ['nullable', 'in:ad,bs'],
        ];
    }
}
