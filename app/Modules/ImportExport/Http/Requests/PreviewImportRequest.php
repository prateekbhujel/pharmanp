<?php

namespace App\Modules\ImportExport\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_owner || $this->user()?->can('imports.preview');
    }

    public function rules(): array
    {
        return [
            'target' => ['required', Rule::in(['products', 'suppliers', 'customers', 'units', 'companies', 'opening_stock', 'batches'])],
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:10240'],
        ];
    }
}
