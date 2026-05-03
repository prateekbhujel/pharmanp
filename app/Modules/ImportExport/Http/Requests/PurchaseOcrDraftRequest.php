<?php

namespace App\Modules\ImportExport\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @OA\Schema(
 *     schema="PurchaseOcrDraftRequest",
 *     title="Purchase OCR Draft Request",
 *
 *     @OA\Property(property="ocr_text", type="string", example="Supplier bill text"),
 *     @OA\Property(property="analysis", type="object", nullable=true),
 *     @OA\Property(property="matches", type="array", @OA\Items(type="object")),
 *     @OA\Property(property="selected_purchase_id", type="integer", nullable=true, example=1)
 * )
 */
class PurchaseOcrDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'ocr_text' => ['required', 'string'],
            'analysis' => ['nullable', 'array'],
            'matches' => ['nullable', 'array'],
            'selected_purchase_id' => ['nullable', 'integer', 'exists:purchases,id'],
        ];
    }
}
