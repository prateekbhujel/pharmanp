<?php

namespace App\Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'entries.*.account_type' => ['required', 'string', 'max:60'],
            'entries.*.party_type' => ['nullable', 'in:supplier,customer,other'],
            'entries.*.party_id' => ['nullable', 'integer'],
            'entries.*.entry_type' => ['required', 'in:debit,credit'],
            'entries.*.amount' => ['required', 'numeric', 'min:0.01', 'max:999999999'],
            'entries.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
