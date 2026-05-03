<?php

namespace App\Modules\Setup\Http\Requests;

use App\Modules\Setup\Models\DropdownOption;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @OA\Schema(
 *     schema="DropdownOptionRequest",
 *     title="Dropdown Option Request",
 *
 *     @OA\Property(property="alias", type="string", example="payment_mode"),
 *     @OA\Property(property="name", type="string", example="Cash"),
 *     @OA\Property(property="data", type="string", nullable=true, example="cash"),
 *     @OA\Property(property="status", type="boolean", example=true)
 * )
 */
class DropdownOptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_owner || (bool) $this->user()?->can('settings.manage');
    }

    public function rules(): array
    {
        $option = $this->route('dropdownOption');
        $ignoreId = $option instanceof DropdownOption ? $option->id : null;

        return [
            'alias' => ['required', Rule::in(array_keys(DropdownOption::managedAliases()))],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('dropdown_options', 'name')
                    ->where(fn ($query) => $query->where('alias', $this->input('alias')))
                    ->ignore($ignoreId),
            ],
            'data' => ['nullable', 'string', 'max:255'],
            'meta' => ['nullable', 'array'],
            'meta.instructions' => ['nullable', 'string', 'max:1000'],
            'qr_file' => ['nullable', 'image', 'max:2048'],
            'status' => ['nullable', 'boolean'],
        ];
    }
}
