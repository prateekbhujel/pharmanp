<?php

namespace App\Modules\Setup\Http\Requests;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @OA\Schema(
 *     schema="SettingsAdminRequest",
 *     title="Settings Admin Request",
 *
 *     @OA\Property(property="company_email", type="string", nullable=true, example="info@example.com"),
 *     @OA\Property(property="company_phone", type="string", nullable=true, example="9800000000"),
 *     @OA\Property(property="currency_symbol", type="string", nullable=true, example="Rs."),
 *     @OA\Property(property="document_numbering", type="object", nullable=true)
 * )
 */
class SettingsAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_owner || (bool) $this->user()?->can('settings.manage');
    }

    public function rules(): array
    {
        return [
            'company_email' => ['nullable', 'email'],
            'company_phone' => ['nullable', 'string', 'max:255'],
            'company_address' => ['nullable', 'string', 'max:5000'],
            'currency_symbol' => ['nullable', 'string', 'max:20'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:1'],
            'smtp_host' => ['nullable', 'string', 'max:255'],
            'smtp_port' => ['nullable', 'string', 'max:255'],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:255'],
            'smtp_encryption' => ['nullable', 'string', 'max:255'],
            'mail_from_address' => ['nullable', 'email'],
            'mail_from_name' => ['nullable', 'string', 'max:255'],
            'notification_email' => ['nullable', 'email'],
            'document_numbering' => ['nullable', 'array'],
            'document_numbering.*.prefix' => ['nullable', 'string', 'max:12'],
            'document_numbering.*.date_format' => ['nullable', 'in:Ymd,Ym,Y,none'],
            'document_numbering.*.separator' => ['nullable', Rule::in(['-', '/', '.', ''])],
            'document_numbering.*.padding' => ['nullable', 'integer', 'min:1', 'max:12'],
        ];
    }
}
