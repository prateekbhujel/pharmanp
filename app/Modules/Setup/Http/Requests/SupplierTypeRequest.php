<?php

namespace App\Modules\Setup\Http\Requests;

use App\Modules\Setup\Models\SupplierType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @OA\Schema(
 *     schema="SupplierTypeRequest",
 *     title="Supplier Type Request",
 *
 *     @OA\Property(property="name", type="string", example="Distributor"),
 *     @OA\Property(property="code", type="string", nullable=true, example="DIST")
 * )
 */
class SupplierTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_owner || (bool) $this->user()?->can('settings.manage');
    }

    public function rules(): array
    {
        $supplierType = $this->route('supplierType');
        $ignoreId = $supplierType instanceof SupplierType ? $supplierType->id : null;

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('supplier_types', 'name')->ignore($ignoreId)],
            'code' => ['nullable', 'string', 'max:80'],
        ];
    }
}
