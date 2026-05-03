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
            'product_code' => ['nullable', 'string', 'max:80'],
            'hs_code' => ['nullable', 'string', 'max:80'],
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'manufacturer_id' => ['nullable', 'integer', 'exists:companies,id'],
            'division_id' => ['nullable', 'integer', 'exists:divisions,id'],
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'conversion' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'generic_name' => ['nullable', 'string', 'max:180'],
            'composition' => ['nullable', 'string', 'max:255'],
            'group_name' => ['nullable', 'string', 'max:120'],
            'manufacturer_name' => ['nullable', 'string', 'max:180'],
            'packaging_type' => ['nullable', 'string', 'max:120'],
            'case_movement' => ['nullable', 'string', 'max:120'],
            'formulation' => ['nullable', 'string', 'max:80'],
            'strength' => ['nullable', 'string', 'max:80'],
            'rack_location' => ['nullable', 'string', 'max:80'],
            'previous_price' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'mrp' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'purchase_price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'selling_price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'cc_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'reorder_level' => ['required', 'integer', 'min:0', 'max:999999'],
            'reorder_quantity' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'is_batch_tracked' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'keywords' => ['nullable', 'string', 'max:1000'],
            'description' => ['nullable', 'string', 'max:10000'],
            'image' => ['nullable', 'image', 'max:2048'],
            'remove_image' => ['nullable', 'boolean'],
        ];
    }
}
