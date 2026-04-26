<?php

namespace App\Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'adjustment_date' => ['required', 'date'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'batch_id' => ['required', 'integer', 'exists:batches,id'],
            'adjustment_type' => ['required', Rule::in(['add', 'subtract', 'expired', 'damaged', 'return'])],
            'quantity' => ['required', 'numeric', 'min:0.001', 'max:999999'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
