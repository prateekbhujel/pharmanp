<?php

namespace App\Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @OA\Schema(
 *     schema="ProductIndexRequest",
 *     title="Product Index Request",
 *     description="Validated request contract for Product Index Request",
 *     type="object",
 *     additionalProperties=true
 * )
 */
class ProductIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'search' => ['nullable', 'string', 'max:120'],
            'sort_field' => ['nullable', Rule::in(['name', 'sku', 'barcode', 'product_code', 'hs_code', 'mrp', 'reorder_level', 'stock_on_hand', 'created_at', 'updated_at'])],
            'sort_order' => ['nullable', Rule::in(['asc', 'desc'])],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'division_id' => ['nullable', 'integer', 'exists:divisions,id'],
            'is_active' => ['nullable', 'boolean'],
            'deleted' => ['nullable', 'boolean'],
        ];
    }
}
