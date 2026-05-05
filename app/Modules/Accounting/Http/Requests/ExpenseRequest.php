<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @OA\Schema(
 *     schema="ExpenseRequest",
 *     title="Expense Request",
 *     description="Validated request contract for expense create and update",
 *     type="object",
 *     additionalProperties=true
 * )
 */
class ExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['nullable', 'integer', 'exists:expenses,id'],
            'expense_date' => ['required', 'date'],
            'expense_category_id' => [
                'required',
                'integer',
                Rule::exists('dropdown_options', 'id')->where(fn ($query) => $query->where('alias', 'expense_category')),
            ],
            'vendor_name' => ['nullable', 'string', 'max:255'],
            'payment_mode_id' => [
                'required',
                'integer',
                Rule::exists('dropdown_options', 'id')->where(fn ($query) => $query->where('alias', 'payment_mode')),
            ],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
