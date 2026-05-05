<?php

namespace App\Modules\Core\Http\Requests;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @OA\Schema(
 *     schema="DeveloperGuideAccessRequest",
 *     required={"code"},
 *
 *     @OA\Property(property="code", type="string", example="9862500130")
 * )
 */
class DeveloperGuideAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_active;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'size:10'],
        ];
    }
}
