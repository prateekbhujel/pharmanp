<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Modules\Accounting\Support\AccountCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @OA\Schema(
 *     schema="VoucherStoreRequest",
 *     title="Voucher Store Request",
 *     description="Validated request contract for Voucher Store Request",
 *     type="object",
 *     additionalProperties=true
 * )
 */
class VoucherStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'voucher_date' => ['required', 'date'],
            'voucher_type' => ['required', 'in:payment_in,payment_out,journal,contra'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'entries' => ['required', 'array', 'min:2'],
            'entries.*.account_type' => ['required', 'string', Rule::in(AccountCatalog::keys())],
            'entries.*.party_type' => ['nullable', 'in:supplier,customer,other'],
            'entries.*.party_id' => ['nullable', 'integer'],
            'entries.*.entry_type' => ['required', 'in:debit,credit'],
            'entries.*.amount' => ['required', 'numeric', 'min:0.01', 'max:999999999'],
            'entries.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
