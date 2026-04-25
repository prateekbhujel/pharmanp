<?php

namespace App\Modules\Setup\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateSetupInviteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_owner;
    }

    public function rules(): array
    {
        return [
            'client_name' => ['nullable', 'string', 'max:180'],
            'client_email' => ['nullable', 'email', 'max:180'],
            'requested_features' => ['nullable', 'array'],
            'requested_features.*' => ['string', 'max:120'],
            'prefill' => ['nullable', 'array'],
            'expires_on' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }
}
