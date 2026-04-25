<?php

namespace App\Modules\MR\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'visit_date' => ['required', 'date'],
            'status' => ['required', 'in:planned,visited,missed,converted'],
            'order_value' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
