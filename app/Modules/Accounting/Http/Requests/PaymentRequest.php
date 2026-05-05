<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @OA\Schema(
 *     schema="PaymentRequest",
 *     title="Payment Request",
 *     description="Validated request contract for payment settlement",
 *     type="object",
 *     additionalProperties=true
 * )
 */
class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['nullable', 'integer', 'exists:payments,id'],
            'direction' => ['required', Rule::in(['in', 'out'])],
            'party_type' => ['required', Rule::in(['customer', 'supplier'])],
            'party_id' => ['required', 'integer'],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_mode_id' => [
                'required',
                'integer',
                Rule::exists('dropdown_options', 'id')->where(fn ($query) => $query->where('alias', 'payment_mode')),
            ],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'allocations' => ['nullable', 'array'],
            'allocations.*.bill_id' => ['nullable', 'integer'],
            'allocations.*.bill_type' => ['nullable', Rule::in(['sales_invoice', 'purchase'])],
            'allocations.*.allocated_amount' => ['nullable', 'numeric', 'min:0.01'],
        ];
    }
}
