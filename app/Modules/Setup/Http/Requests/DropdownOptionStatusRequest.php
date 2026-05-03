<?php

namespace App\Modules\Setup\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @OA\Schema(
 *     schema="DropdownOptionStatusRequest",
 *     title="Dropdown Option Status Request",
 *
 *     @OA\Property(property="is_active", type="boolean", example=true)
 * )
 */
class DropdownOptionStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_owner || (bool) $this->user()?->can('settings.manage');
    }

    public function rules(): array
    {
        return [
            'is_active' => ['required', 'boolean'],
        ];
    }
}
