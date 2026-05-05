<?php

namespace App\Modules\Sales\Http\Requests;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Modules\Sales\Models\SalesInvoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @OA\Schema(
 *     schema="SalesInvoicePaymentRequest",
 *     title="Sales Invoice Payment Request",
 *     description="Validated request contract for direct invoice payment updates",
 *     type="object",
 *     additionalProperties=true
 * )
 */
class SalesInvoicePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var SalesInvoice|null $invoice */
        $invoice = $this->route('invoice');

        return [
            'paid_amount' => ['required', 'numeric', 'min:0', 'max:'.(float) $invoice?->grand_total],
            'payment_mode_id' => [
                'nullable',
                Rule::exists('dropdown_options', 'id')->where(fn ($query) => $query->where('alias', 'payment_mode')),
            ],
        ];
    }
}
