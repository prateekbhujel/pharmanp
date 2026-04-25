<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Inventory\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Product|null $product */
        $product = $this->route('product');
        $companyId = $this->input('company_id', $product?->company_id);

        return [
            'name' => ['required', 'string', 'max:180'],
            'sku' => [
                'nullable',
                'string',
                'max:80',
                Rule::unique('products', 'sku')->ignore($product?->id)->where(fn ($query) => $query->where('company_id', $companyId)->whereNull('deleted_at')),
            ],
            'barcode' => [
                'nullable',
                'string',
                'max:120',
                Rule::unique('products', 'barcode')->ignore($product?->id)->where(fn ($query) => $query->where('company_id', $companyId)->whereNull('deleted_at')),
            ],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'manufacturer_id' => ['nullable', 'integer', 'exists:companies,id'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'generic_name' => ['nullable', 'string', 'max:180'],
            'composition' => ['nullable', 'string', 'max:255'],
            'formulation' => ['nullable', 'string', 'max:80'],
            'strength' => ['nullable', 'string', 'max:80'],
            'rack_location' => ['nullable', 'string', 'max:80'],
            'mrp' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'purchase_price' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'selling_price' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'cc_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'reorder_level' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'reorder_quantity' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'is_batch_tracked' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
