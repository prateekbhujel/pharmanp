<?php

namespace App\Modules\MR\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @OA\Schema(
 *     schema="RepresentativeVisitRequest",
 *     title="Representative Visit Request",
 *     description="Validated request contract for Representative Visit Request",
 *     type="object",
 *     additionalProperties=true
 * )
 */
class RepresentativeVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_owner || (bool) $this->user()?->can('mr.visits.manage') || (bool) $this->user()?->can('mr.manage');
    }

    public function rules(): array
    {
        return [
            'medical_representative_id' => ['required', 'integer', 'exists:medical_representatives,id'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'visit_date' => ['required', 'date'],
            'visit_time' => ['nullable', 'date_format:H:i'],
            'status' => ['required', 'in:planned,visited,missed,converted'],
            'purpose' => ['nullable', 'string', 'max:160'],
            'location_name' => ['nullable', 'string', 'max:255'],
            'order_value' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
