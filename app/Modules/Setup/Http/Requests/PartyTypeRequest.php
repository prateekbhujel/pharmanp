<?php

namespace App\Modules\Setup\Http\Requests;

use App\Modules\Setup\Models\PartyType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @OA\Schema(
 *     schema="PartyTypeRequest",
 *     title="Party Type Request",
 *
 *     @OA\Property(property="name", type="string", example="Retailer"),
 *     @OA\Property(property="code", type="string", nullable=true, example="RTL")
 * )
 */
class PartyTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_owner || (bool) $this->user()?->can('settings.manage');
    }

    public function rules(): array
    {
        $partyType = $this->route('partyType');
        $ignoreId = $partyType instanceof PartyType ? $partyType->id : null;

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('party_types', 'name')->ignore($ignoreId)],
            'code' => ['nullable', 'string', 'max:80'],
        ];
    }
}
