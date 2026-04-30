<?php

namespace App\Modules\MR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MR\Models\Branch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BranchController extends Controller
{
    // Return flat list with optional hierarchy info, used for selects and management.
    public function index(Request $request): JsonResponse
    {
        $this->ensureCanManage($request);

        $sortField = in_array($request->query('sort_field'), ['name', 'code', 'type', 'created_at', 'updated_at'], true)
            ? $request->query('sort_field')
            : 'updated_at';
        $sortOrder = $request->query('sort_order') === 'asc' ? 'asc' : 'desc';
        $search = trim((string) $request->query('search'));

        $query = Branch::query()
            ->with('parent:id,name,code')
            ->withCount('medicalRepresentatives')
            ->when($request->user()?->tenant_id, fn ($query, $tenantId) => $query->where('tenant_id', $tenantId))
            ->when($request->user()?->company_id, fn ($query, $companyId) => $query->where('company_id', $companyId))
            ->when($request->boolean('deleted'), fn ($query) => $query->onlyTrashed())
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $builder) use ($search) {
                    $builder->where('name', 'like', '%'.$search.'%')
                        ->orWhere('code', 'like', '%'.$search.'%')
                        ->orWhere('address', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%');
                });
            })
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->query('type')))
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderByRaw("CASE WHEN type = 'hq' THEN 0 ELSE 1 END")
            ->orderBy($sortField, $sortOrder);

        $rows = $query->paginate(min(100, max(5, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($rows->items())->map(fn (Branch $branch) => $this->rowPayload($branch))->values(),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
                'last_page' => $rows->lastPage(),
            ],
            'lookups' => [
                'parents' => $this->parentOptions($request),
            ],
        ]);
    }

    // Create a new branch.
    public function store(Request $request): JsonResponse
    {
        $this->ensureCanManage($request);
        $validated = $this->validatedPayload($request);

        $branch = DB::transaction(fn () => Branch::query()->create([
            ...$this->normalizedPayload($validated),
            'tenant_id' => $request->user()?->tenant_id,
            'company_id' => $request->user()?->company_id,
            'store_id' => $request->user()?->store_id,
        ]));

        return response()->json([
            'message' => "Branch '{$branch->name}' created.",
            'data'    => $this->rowPayload($branch->load('parent')),
        ], 201);
    }

    // Update an existing branch.
    public function update(Request $request, Branch $branch): JsonResponse
    {
        $this->ensureCanManage($request);
        $this->assertSameScope($request, $branch);
        $validated = $this->validatedPayload($request, $branch->id);

        DB::transaction(fn () => $branch->update($this->normalizedPayload($validated)));

        return response()->json([
            'message' => "Branch '{$branch->name}' updated.",
            'data'    => $this->rowPayload($branch->fresh('parent')),
        ]);
    }

    public function toggleStatus(Request $request, Branch $branch): JsonResponse
    {
        $this->ensureCanManage($request);
        $this->assertSameScope($request, $branch);

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $branch->forceFill(['is_active' => (bool) $validated['is_active']])->save();

        return response()->json([
            'message' => 'Branch status updated.',
            'data' => $this->rowPayload($branch->fresh('parent')),
        ]);
    }

    // Delete a branch only when it has no assigned MRs.
    public function destroy(Request $request, Branch $branch): JsonResponse
    {
        $this->ensureCanManage($request);
        $this->assertSameScope($request, $branch);

        if ($branch->medicalRepresentatives()->exists()) {
            return response()->json([
                'message' => 'This branch has assigned MRs. Reassign them first.',
            ], 422);
        }

        if ($branch->children()->exists()) {
            return response()->json([
                'message' => 'This branch has child branches. Move or delete child branches first.',
            ], 422);
        }

        DB::transaction(function () use ($branch) {
            $branch->forceFill(['is_active' => false])->save();
            $branch->delete();
        });

        return response()->json(['message' => 'Branch deleted.']);
    }

    public function restore(Request $request, int $id): JsonResponse
    {
        $this->ensureCanManage($request);

        $branch = Branch::query()
            ->onlyTrashed()
            ->when($request->user()?->tenant_id, fn ($query, $tenantId) => $query->where('tenant_id', $tenantId))
            ->when($request->user()?->company_id, fn ($query, $companyId) => $query->where('company_id', $companyId))
            ->findOrFail($id);

        DB::transaction(function () use ($branch) {
            $branch->restore();
            $branch->forceFill(['is_active' => true])->save();
        });

        return response()->json([
            'message' => 'Branch restored.',
            'data' => $this->rowPayload($branch->fresh('parent')),
        ]);
    }

    // Lightweight options list for Select dropdowns.
    public function options(Request $request): JsonResponse
    {
        $options = Branch::query()
            ->where('is_active', true)
            ->when($request->user()?->tenant_id, fn ($query, $tenantId) => $query->where('tenant_id', $tenantId))
            ->when($request->user()?->company_id, fn ($query, $companyId) => $query->where('company_id', $companyId))
            ->orderByRaw("CASE WHEN type = 'hq' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'type'])
            ->map(fn ($b) => [
                'id'    => $b->id,
                'name'  => $b->name . ($b->type === 'hq' ? ' (HQ)' : ''),
                'code'  => $b->code,
                'type'  => $b->type,
            ]);

        return response()->json(['data' => $options]);
    }

    private function validatedPayload(Request $request, ?int $ignoreId = null): array
    {
        $tenantId = $request->user()?->tenant_id;
        $companyId = $request->user()?->company_id;

        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'code'      => [
                'nullable',
                'string',
                'max:40',
                Rule::unique('branches', 'code')
                    ->where(fn ($query) => $query
                        ->when($tenantId, fn ($builder) => $builder->where('tenant_id', $tenantId))
                        ->when($companyId, fn ($builder) => $builder->where('company_id', $companyId)))
                    ->ignore($ignoreId),
            ],
            'type'      => ['required', Rule::in(['hq', 'branch'])],
            'parent_id' => ['nullable', 'integer'],
            'address'   => ['nullable', 'string', 'max:500'],
            'phone'     => ['nullable', 'string', 'max:40'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (($validated['type'] ?? null) === 'hq') {
            $validated['parent_id'] = null;
        }

        if (! empty($validated['parent_id'])) {
            $parentExists = Branch::query()
                ->whereKey($validated['parent_id'])
                ->where('type', 'hq')
                ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
                ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
                ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                ->exists();

            if (! $parentExists) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Select a valid HQ branch from this company.',
                ]);
            }
        }

        return $validated;
    }

    private function normalizedPayload(array $validated): array
    {
        return [
            ...$validated,
            'code' => filled($validated['code'] ?? null) ? strtoupper(trim((string) $validated['code'])) : null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ];
    }

    private function rowPayload(Branch $branch): array
    {
        return [
            'id'        => $branch->id,
            'name'      => $branch->name,
            'code'      => $branch->code,
            'type'      => $branch->type,
            'parent_id' => $branch->parent_id,
            'parent' => $branch->relationLoaded('parent') && $branch->parent ? [
                'id' => $branch->parent->id,
                'name' => $branch->parent->name,
                'code' => $branch->parent->code,
            ] : null,
            'address'   => $branch->address,
            'phone'     => $branch->phone,
            'is_active' => (bool) $branch->is_active,
            'is_hq'     => $branch->is_hq,
            'medical_representatives_count' => (int) ($branch->medical_representatives_count ?? 0),
            'deleted_at' => $branch->deleted_at?->toISOString(),
            'created_at' => $branch->created_at?->toDateString(),
        ];
    }

    private function parentOptions(Request $request): array
    {
        return Branch::query()
            ->where('type', 'hq')
            ->where('is_active', true)
            ->when($request->user()?->tenant_id, fn ($query, $tenantId) => $query->where('tenant_id', $tenantId))
            ->when($request->user()?->company_id, fn ($query, $companyId) => $query->where('company_id', $companyId))
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (Branch $branch) => [
                'id' => $branch->id,
                'name' => $branch->name,
                'code' => $branch->code,
            ])
            ->values()
            ->all();
    }

    private function ensureCanManage(Request $request): void
    {
        abort_unless(
            $request->user()?->is_owner
                || $request->user()?->can('setup.manage')
                || $request->user()?->can('users.manage')
                || $request->user()?->can('mr.manage'),
            403,
        );
    }

    private function assertSameScope(Request $request, Branch $branch): void
    {
        if ($request->user()?->tenant_id && (int) $branch->tenant_id !== (int) $request->user()->tenant_id) {
            abort(404);
        }

        if ($request->user()?->company_id && (int) $branch->company_id !== (int) $request->user()->company_id) {
            abort(404);
        }
    }
}
