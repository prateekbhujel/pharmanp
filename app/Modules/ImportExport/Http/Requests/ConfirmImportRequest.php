<?php

namespace App\Modules\ImportExport\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @OA\Schema(
 *     schema="ConfirmImportRequest",
 *     title="Confirm Import Request",
 *     description="Validated request contract for Confirm Import Request",
 *     type="object",
 *     additionalProperties=true
 * )
 */
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
