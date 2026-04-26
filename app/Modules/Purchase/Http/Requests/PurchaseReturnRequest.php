<?php

namespace App\Modules\Purchase\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'purchase_id' => ['nullable', 'integer', 'exists:purchases,id'],
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'return_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_item_id' => ['nullable', 'integer', 'exists:purchase_items,id'],
            'items.*.batch_id' => ['required', 'integer', 'exists:batches,id'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.return_qty' => ['required', 'numeric', 'min:0.001', 'max:999999'],
            'items.*.rate' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'items.*.net_rate' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Please add at least one return item.',
            'items.*.batch_id.required' => 'Please choose a batch for every return row.',
            'items.*.return_qty.min' => 'Return quantity must be greater than zero.',
        ];
    }
}
