<?php

namespace App\Modules\Core\Http\Requests;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @OA\Schema(
 *     schema="ApiLoginRequest",
 *     title="API Login Request",
 *     description="Session login and optional PharmaNP bearer token contract for Swagger and standalone SPA clients",
 *     required={"email", "password"},
 *
 *     @OA\Property(property="email", type="string", format="email", example="pratik@admin.com"),
 *     @OA\Property(property="password", type="string", format="password", example="password"),
 *     @OA\Property(property="remember", type="boolean", example=false),
 *     @OA\Property(property="issue_token", type="boolean", example=true),
 *     @OA\Property(property="device_name", type="string", example="Swagger UI")
 * )
 */
class ApiLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
            'issue_token' => ['nullable', 'boolean'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ];
    }
}
