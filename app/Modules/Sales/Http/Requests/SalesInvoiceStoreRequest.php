<?php

namespace App\Modules\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SalesInvoiceStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_owner
            || (bool) $this->user()?->can('sales.invoices.create')
            || (bool) $this->user()?->can('sales.pos.use');
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'medical_representative_id' => ['nullable', 'integer', 'exists:medical_representatives,id'],
            'invoice_date' => ['required', 'date'],
            'sale_type' => ['required', 'in:retail,wholesale,pos'],
            'paid_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.batch_id' => ['nullable', 'integer', 'exists:batches,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001', 'max:999999'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
