<?php

namespace App\Modules\MR\Http\Requests;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @OA\Schema(
 *     schema="MedicalRepresentativeRequest",
 *     title="Medical Representative Request",
 *     description="Validated request contract for Medical Representative Request",
 *     type="object",
 *     additionalProperties=true
 * )
 */
class MedicalRepresentativeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_owner || (bool) $this->user()?->can('mr.manage');
    }

    public function rules(): array
    {
        $representative = $this->route('representative');

        return [
            'name' => ['required', 'string', 'max:255'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'area_id' => ['nullable', 'integer', 'exists:areas,id'],
            'division_id' => ['nullable', 'integer', 'exists:divisions,id'],
            'employee_code' => ['nullable', 'string', 'max:80', Rule::unique('medical_representatives', 'employee_code')->ignore($representative?->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'monthly_target' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
