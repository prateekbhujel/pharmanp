<?php

namespace App\Modules\ImportExport\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @OA\Schema(
 *     schema="PurchaseOcrExtractRequest",
 *     title="Purchase OCR Extract Request",
 *
 *     @OA\Property(property="image", type="string", format="binary")
 * )
 */
class PurchaseOcrExtractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $uploadMaxKb = max(1024, (int) config('services.ocr_space.upload_max_kb', 10240));

        return [
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:'.$uploadMaxKb],
        ];
    }
}
