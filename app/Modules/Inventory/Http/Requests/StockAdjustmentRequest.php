<?php

namespace App\Modules\Inventory\Http\Requests;

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
        $user = $this->user();
        $tenantId = $user?->canAccessAllTenants() ? null : $user?->tenant_id;
        $companyId = $user?->canAccessAllTenants() ? null : $user?->company_id;
        $storeId = $user?->canAccessAllTenants() ? null : $user?->store_id;
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
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where(function ($query) use ($companyId, $storeId, $tenantId) {
                    return $query
                        ->when($tenantId !== null, fn ($builder) => $builder->where('tenant_id', $tenantId))
                        ->when($companyId !== null, fn ($builder) => $builder->where('company_id', $companyId))
                        ->when($storeId !== null, function ($builder) use ($storeId) {
                            $builder->where(function ($store) use ($storeId): void {
                                $store->where('store_id', $storeId)->orWhereNull('store_id');
                            });
                        });
                }),
            ],
            'batch_id' => [
                'required',
                'integer',
                Rule::exists('batches', 'id')->where(function ($query) use ($companyId, $storeId, $tenantId) {
                    return $query
                        ->when($tenantId !== null, fn ($builder) => $builder->where('tenant_id', $tenantId))
                        ->when($companyId !== null, fn ($builder) => $builder->where('company_id', $companyId))
                        ->when($storeId !== null, function ($builder) use ($storeId) {
                            $builder->where(function ($store) use ($storeId): void {
                                $store->where('store_id', $storeId)->orWhereNull('store_id');
                            });
                        });
                }),
            ],
            'adjustment_type' => ['required', Rule::in(array_values(array_unique($adjustmentTypes)))],
            'quantity' => ['required', 'numeric', 'min:0.001', 'max:999999'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
