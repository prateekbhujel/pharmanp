<?php

namespace App\Modules\Setup\Http\Requests;

use App\Modules\Setup\Models\Target;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @OA\Schema(
 *     schema="TargetRequest",
 *     title="Target Request",
 *     description="Validated request contract for Target Request",
 *     type="object",
 *     additionalProperties=true
 * )
 */
class TargetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->is_owner || $this->user()?->can('mr.manage') || $this->user()?->can('reports.view'));
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'area_id' => ['nullable', 'integer', 'exists:areas,id'],
            'division_id' => ['nullable', 'integer', 'exists:divisions,id'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'target_type' => ['required', Rule::in(Target::TYPES)],
            'target_period' => ['required', Rule::in(Target::PERIODS)],
            'target_level' => ['required', Rule::in(Target::LEVELS)],
            'target_amount' => ['nullable', 'numeric', 'min:0'],
            'target_quantity' => ['nullable', 'numeric', 'min:0'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', Rule::in(['active', 'paused', 'closed'])],
            'notes' => ['nullable', 'string'],
        ];
    }
}
