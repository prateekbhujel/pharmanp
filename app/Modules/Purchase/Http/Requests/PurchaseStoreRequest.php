<?php

namespace App\Modules\Purchase\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_owner || (bool) $this->user()?->can('purchase.entries.create');
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'supplier_invoice_no' => ['nullable', 'string', 'max:120'],
            'purchase_date' => ['required', 'date'],
            'paid_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.batch_no' => ['required', 'string', 'max:120'],
            'items.*.barcode' => ['nullable', 'string', 'max:120'],
            'items.*.manufactured_at' => ['nullable', 'date'],
            'items.*.expires_at' => ['required', 'date', 'after:purchase_date'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001', 'max:999999'],
            'items.*.free_quantity' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'items.*.purchase_price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'items.*.mrp' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
