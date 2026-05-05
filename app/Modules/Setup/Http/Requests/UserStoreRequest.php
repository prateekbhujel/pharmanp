<?php

namespace App\Modules\Setup\Http\Requests;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @OA\Schema(
 *     schema="UserStoreRequest",
 *     title="User Store Request",
 *     description="Validated request contract for User Store Request",
 *     type="object",
 *     additionalProperties=true
 * )
 */
class UserStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_owner || (bool) $this->user()?->can('users.manage');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'role_names' => ['required', 'array', 'min:1'],
            'role_names.*' => ['string', Rule::exists('roles', 'name')->where('guard_name', 'web')],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'medical_representative_id' => ['nullable', 'integer', 'exists:medical_representatives,id'],
            'is_active' => ['nullable', 'boolean'],
            'is_owner' => ['nullable', 'boolean'],
        ];
    }
}
