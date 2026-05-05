<?php

namespace App\Modules\Setup\Http\Requests;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @OA\Schema(
 *     schema="TestMailRequest",
 *     title="Test Mail Request",
 *
 *     @OA\Property(property="email", type="string", nullable=true, example="owner@example.com")
 * )
 */
class TestMailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_owner || (bool) $this->user()?->can('settings.manage');
    }

    public function rules(): array
    {
        return [
            'email' => ['nullable', 'email'],
        ];
    }
}
