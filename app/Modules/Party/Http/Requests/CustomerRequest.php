<?php

namespace App\Modules\Party\Http\Requests;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @OA\Schema(
 *     schema="CustomerRequest",
 *     title="Customer Request",
 *     description="Validated request contract for Customer Request",
 *     type="object",
 *     additionalProperties=true
 * )
 */
class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_code' => ['nullable', 'string', 'max:80'],
            'party_type_id' => ['nullable', 'integer', 'exists:party_types,id'],
            'name' => ['required', 'string', 'max:180'],
            'contact_person' => ['nullable', 'string', 'max:180'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:180'],
            'pan_number' => ['nullable', 'string', 'max:60'],
            'address' => ['nullable', 'string', 'max:255'],
            'credit_limit' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'opening_balance' => ['nullable', 'numeric', 'min:-999999999', 'max:999999999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
