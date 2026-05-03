<?php

namespace App\Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @OA\Schema(
 *     schema="OutstandingBillsRequest",
 *     title="Outstanding Bills Request",
 *     description="Validated request contract for payment outstanding bills lookup",
 *     type="object",
 *     additionalProperties=true
 * )
 */
class OutstandingBillsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'party_id' => ['required', 'integer'],
            'party_type' => ['required', Rule::in(['customer', 'supplier'])],
        ];
    }
}
