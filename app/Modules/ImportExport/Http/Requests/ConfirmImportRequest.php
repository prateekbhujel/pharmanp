<?php

namespace App\Modules\ImportExport\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_owner || $this->user()?->can('imports.commit');
    }

    public function rules(): array
    {
        return [
            'import_job_id' => ['required', 'integer', 'exists:import_jobs,id'],
            'mapping' => ['required', 'array', 'min:1'],
            'mapping.*' => ['nullable', 'string', 'max:120'],
        ];
    }
}
