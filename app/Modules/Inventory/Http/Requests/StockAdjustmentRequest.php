<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Modules\Setup\Models\DropdownOption;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @OA\Schema(
 *     schema="StockAdjustmentRequest",
 *     title="Stock Adjustment Request",
 *     description="Validated request contract for Stock Adjustment Request",
 *     type="object",
 *     additionalProperties=true
 * )
 */
class StockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $adjustmentTypes = [
            'add',
            'subtract',
            'expired',
            'damaged',
            'return',
            ...DropdownOption::query()->forAlias('adjustment_type')->active()->pluck('name')->all(),
        ];

        return [
            'adjustment_date' => ['required', 'date'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'batch_id' => ['required', 'integer', 'exists:batches,id'],
            'adjustment_type' => ['required', Rule::in(array_values(array_unique($adjustmentTypes)))],
            'quantity' => ['required', 'numeric', 'min:0.001', 'max:999999'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
