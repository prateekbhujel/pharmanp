<?php

namespace App\Modules\Core\Http\Requests;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @OA\Schema(
 *     schema="GlobalSearchRequest",
 *     title="Global Search Request",
 *
 *     @OA\Property(property="query", type="string", example="paracetamol"),
 *     @OA\Property(property="limit", type="integer", example=5)
 * )
 */
class GlobalSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'query' => ['nullable', 'string', 'max:120'],
            'limit' => ['nullable', 'integer', 'min:3', 'max:10'],
        ];
    }
}
