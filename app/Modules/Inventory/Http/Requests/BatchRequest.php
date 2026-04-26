<?php

namespace App\Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'batch_no' => [
                'required',
                'string',
                'max:120',
                Rule::unique('batches', 'batch_no')
                    ->where('product_id', $this->integer('product_id'))
                    ->ignore($this->route('batch')),
            ],
            'barcode' => ['nullable', 'string', 'max:120'],
            'manufactured_at' => ['nullable', 'date'],
            'expires_at' => ['required', 'date'],
            'quantity_received' => ['required', 'numeric', 'min:0'],
            'quantity_available' => ['nullable', 'numeric', 'min:0'],
            'purchase_price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'mrp' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
