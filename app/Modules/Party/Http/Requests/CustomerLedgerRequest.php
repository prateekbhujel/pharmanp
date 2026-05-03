<?php

namespace App\Modules\Party\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @OA\Schema(
 *     schema="CustomerLedgerRequest",
 *     title="Customer Ledger Request",
 *
 *     @OA\Property(property="from", type="string", nullable=true, example="2026-04-01"),
 *     @OA\Property(property="to", type="string", nullable=true, example="2026-04-30")
 * )
 */
class CustomerLedgerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'from' => ['nullable', 'string', 'max:20'],
            'to' => ['nullable', 'string', 'max:20'],
        ];
    }
}
