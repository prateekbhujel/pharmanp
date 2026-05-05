<?php

namespace App\Modules\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @OA\Schema(
 *     schema="SalesInvoiceStoreRequest",
 *     title="Sales Invoice Store Request",
 *     description="Validated request contract for Sales Invoice Store Request",
 *     type="object",
 *     additionalProperties=true
 * )
 */
class SalesInvoiceStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'medical_representative_id' => ['nullable', 'integer', 'exists:medical_representatives,id'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'sale_type' => ['required', 'in:retail,wholesale,pos'],
            'payment_mode_id' => ['nullable', 'integer', 'exists:dropdown_options,id'],
            'payment_type' => ['nullable', 'string', 'max:40'],
            'paid_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.batch_id' => ['nullable', 'integer', 'exists:batches,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001', 'max:999999'],
            'items.*.free_quantity' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'items.*.cc_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
