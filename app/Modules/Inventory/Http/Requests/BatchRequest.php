<?php

namespace App\Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @OA\Schema(
 *     schema="BatchRequest",
 *     title="Batch Request",
 *     description="Validated request contract for Batch Request",
 *     type="object",
 *     additionalProperties=true
 * )
 */
class BatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->user();
        $tenantId = $user?->canAccessAllTenants() ? null : $user?->tenant_id;
        $companyId = $user?->canAccessAllTenants() ? null : $user?->company_id;
        $storeId = $user?->canAccessAllTenants() ? null : $user?->store_id;

        return [
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where(function ($query) use ($companyId, $storeId, $tenantId) {
                    return $query
                        ->when($tenantId !== null, fn ($builder) => $builder->where('tenant_id', $tenantId))
                        ->when($companyId !== null, fn ($builder) => $builder->where('company_id', $companyId))
                        ->when($storeId !== null, fn ($builder) => $builder->where('store_id', $storeId));
                }),
            ],
            'supplier_id' => [
                'nullable',
                'integer',
                Rule::exists('suppliers', 'id')->where(function ($query) use ($companyId, $tenantId) {
                    return $query
                        ->when($tenantId !== null, fn ($builder) => $builder->where('tenant_id', $tenantId))
                        ->when($companyId !== null, fn ($builder) => $builder->where('company_id', $companyId));
                }),
            ],
            'batch_no' => [
                'required',
                'string',
                'max:120',
                Rule::unique('batches', 'batch_no')
                    ->where('product_id', $this->integer('product_id'))
                    ->when($tenantId !== null, fn ($rule) => $rule->where('tenant_id', $tenantId))
                    ->when($companyId !== null, fn ($rule) => $rule->where('company_id', $companyId))
                    ->when($storeId !== null, fn ($rule) => $rule->where('store_id', $storeId))
                    ->ignore($this->route('batch')),
            ],
            'barcode' => ['nullable', 'string', 'max:120'],
            'storage_location' => ['nullable', 'string', 'max:120'],
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
