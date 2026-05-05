<?php

namespace App\Modules\Setup\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @OA\Schema(
 *     schema="DivisionRequest",
 *     title="Division Request",
 *     description="Validated request contract for Division Request",
 *     type="object",
 *     additionalProperties=true
 * )
 */
class DivisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->is_owner || $this->user()?->can('settings.manage') || $this->user()?->can('mr.manage'));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'code' => [
                'nullable',
                'string',
                'max:60',
                Rule::unique('divisions', 'code')
                    ->where(fn ($query) => $query
                        ->where('company_id', $this->user()?->company_id)
                        ->whereNull('deleted_at'))
                    ->ignore($this->route('division')),
            ],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
