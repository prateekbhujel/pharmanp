<?php

namespace App\Modules\MR\Http\Requests;

use App\Modules\MR\Models\Branch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @OA\Schema(
 *     schema="BranchRequest",
 *     title="Branch Request",
 *     description="Validated request contract for branch create and update",
 *     type="object",
 *     additionalProperties=true
 * )
 */
class BranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_owner
            || $this->user()?->can('setup.manage')
            || $this->user()?->can('users.manage')
            || $this->user()?->can('mr.manage');
    }

    public function rules(): array
    {
        /** @var Branch|null $branch */
        $branch = $this->route('branch');
        $tenantId = $this->user()?->tenant_id;
        $companyId = $this->user()?->company_id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable',
                'string',
                'max:40',
                Rule::unique('branches', 'code')
                    ->where(fn ($query) => $query
                        ->when($tenantId, fn ($builder) => $builder->where('tenant_id', $tenantId))
                        ->when($companyId, fn ($builder) => $builder->where('company_id', $companyId)))
                    ->ignore($branch?->id),
            ],
            'type' => ['required', Rule::in(['hq', 'branch'])],
            'parent_id' => ['nullable', 'integer'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:40'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
