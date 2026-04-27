<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Http\Requests\InventoryMasterRequest;
use App\Modules\Inventory\Http\Requests\QuickCategoryRequest;
use App\Modules\Inventory\Http\Requests\QuickCompanyRequest;
use App\Modules\Inventory\Http\Requests\QuickUnitRequest;
use App\Modules\Inventory\Models\Company;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\ProductCategory;
use App\Modules\Inventory\Models\Unit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryMasterController extends Controller
{
    public function index(Request $request, string $master): JsonResponse
    {
        $this->authorize('viewAny', Product::class);

        $model = $this->modelFor($master);
        $search = trim((string) $request->query('search'));
        $sortField = in_array($request->query('sort_field'), ['name', 'code', 'created_at'], true)
            ? $request->query('sort_field')
            : 'name';
        $sortOrder = $request->query('sort_order') === 'desc' ? 'desc' : 'asc';

        $rows = $model::query()
            ->when($request->boolean('deleted'), fn ($query) => $query->onlyTrashed())
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->orderBy($sortField, $sortOrder)
            ->paginate(min(100, max(5, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($rows->items())->map(fn (Model $row) => $this->shape($master, $row))->values(),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
                'last_page' => $rows->lastPage(),
            ],
        ]);
    }

    public function store(InventoryMasterRequest $request, string $master): JsonResponse
    {
        $this->authorize('create', Product::class);

        $row = DB::transaction(function () use ($request, $master) {
            $model = $this->modelFor($master);

            return $model::query()->create([
                ...$this->payload($master, $request->validated(), $request),
                'created_by' => $request->user()?->id,
                'updated_by' => $request->user()?->id,
            ]);
        });

        return response()->json(['message' => 'Inventory master saved.', 'data' => $this->shape($master, $row)], 201);
    }

    public function update(InventoryMasterRequest $request, string $master, int $id): JsonResponse
    {
        abort_unless($request->user()?->is_owner || $request->user()?->can('inventory.products.update'), 403);

        $row = DB::transaction(function () use ($request, $master, $id) {
            $model = $this->modelFor($master);
            $row = $model::query()->findOrFail($id);
            $row->update([
                ...$this->payload($master, $request->validated(), $request),
                'updated_by' => $request->user()?->id,
            ]);

            return $row->refresh();
        });

        return response()->json(['message' => 'Inventory master updated.', 'data' => $this->shape($master, $row)]);
    }

    public function toggleStatus(Request $request, string $master, int $id): JsonResponse
    {
        abort_unless($request->user()?->is_owner || $request->user()?->can('inventory.products.update'), 403);

        $row = DB::transaction(function () use ($request, $master, $id) {
            $model = $this->modelFor($master);
            $row = $model::query()->findOrFail($id);
            $row->forceFill([
                'is_active' => $request->boolean('is_active'),
                'updated_by' => $request->user()?->id,
            ])->save();

            return $row->refresh();
        });

        return response()->json(['message' => 'Inventory master status updated.', 'data' => $this->shape($master, $row)]);
    }

    public function destroy(Request $request, string $master, int $id): JsonResponse
    {
        abort_unless($request->user()?->is_owner || $request->user()?->can('inventory.products.delete'), 403);

        DB::transaction(function () use ($master, $id) {
            $model = $this->modelFor($master);
            $row = $model::query()->findOrFail($id);
            $row->forceFill(['is_active' => false])->save();
            $row->delete();
        });

        return response()->json(['message' => 'Inventory master deleted.']);
    }

    public function restore(Request $request, string $master, int $id): JsonResponse
    {
        abort_unless($request->user()?->is_owner || $request->user()?->can('inventory.products.update'), 403);

        $row = DB::transaction(function () use ($request, $master, $id) {
            $model = $this->modelFor($master);
            $row = $model::query()->onlyTrashed()->findOrFail($id);
            $row->restore();
            $row->forceFill([
                'is_active' => true,
                'updated_by' => $request->user()?->id,
            ])->save();

            return $row->refresh();
        });

        return response()->json(['message' => 'Inventory master restored.', 'data' => $this->shape($master, $row)]);
    }

    public function company(QuickCompanyRequest $request): JsonResponse
    {
        $this->authorize('create', Product::class);

        $company = DB::transaction(fn () => Company::query()->create([
            ...$request->validated(),
            'tenant_id' => $request->user()?->tenant_id,
            'company_type' => $request->validated('company_type', 'domestic'),
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]));

        return response()->json(['message' => 'Company added.', 'data' => $company->only(['id', 'name'])], 201);
    }

    public function unit(QuickUnitRequest $request): JsonResponse
    {
        $this->authorize('create', Product::class);

        $data = $request->validated();
        $unit = DB::transaction(fn () => Unit::query()->create([
            'tenant_id' => $request->user()?->tenant_id,
            'company_id' => $data['company_id'] ?? $request->user()?->company_id,
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'type' => $data['type'] ?? 'both',
            'factor' => $data['factor'] ?? 1,
            'description' => $data['description'] ?? null,
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]));

        return response()->json(['message' => 'Unit added.', 'data' => $unit->only(['id', 'name'])], 201);
    }

    public function category(QuickCategoryRequest $request): JsonResponse
    {
        $this->authorize('create', Product::class);

        $data = $request->validated();
        $category = DB::transaction(fn () => ProductCategory::query()->create([
            'tenant_id' => $request->user()?->tenant_id,
            'company_id' => $data['company_id'] ?? $request->user()?->company_id,
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]));

        return response()->json(['message' => 'Category added.', 'data' => $category->only(['id', 'name'])], 201);
    }

    private function modelFor(string $master): string
    {
        return match ($master) {
            'companies' => Company::class,
            'units' => Unit::class,
            'categories' => ProductCategory::class,
            default => throw ValidationException::withMessages(['master' => 'Unknown inventory master.']),
        };
    }

    private function payload(string $master, array $data, Request $request): array
    {
        $base = [
            'tenant_id' => $request->user()?->tenant_id,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];

        return match ($master) {
            'companies' => [
                ...$base,
                'name' => $data['name'],
                'legal_name' => $data['legal_name'] ?? null,
                'pan_number' => $data['pan_number'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'address' => $data['address'] ?? null,
                'company_type' => $data['company_type'] ?? 'domestic',
                'default_cc_rate' => $data['default_cc_rate'] ?? 0,
            ],
            'units' => [
                ...$base,
                'company_id' => $data['company_id'] ?? $request->user()?->company_id,
                'name' => $data['name'],
                'code' => $data['code'] ?? null,
                'type' => $data['type'] ?? 'both',
                'factor' => $data['factor'] ?? 1,
                'description' => $data['description'] ?? null,
            ],
            'categories' => [
                ...$base,
                'company_id' => $data['company_id'] ?? $request->user()?->company_id,
                'name' => $data['name'],
                'code' => $data['code'] ?? null,
            ],
        };
    }

    private function shape(string $master, Model $row): array
    {
        return match ($master) {
            'companies' => [
                ...$row->only(['id', 'name', 'legal_name', 'pan_number', 'phone', 'email', 'address', 'company_type', 'default_cc_rate', 'is_active']),
                'deleted_at' => $row->deleted_at?->toISOString(),
                'created_at' => $row->created_at?->toDateString(),
            ],
            'units' => [
                ...$row->only(['id', 'company_id', 'name', 'code', 'type', 'factor', 'description', 'is_active']),
                'deleted_at' => $row->deleted_at?->toISOString(),
                'created_at' => $row->created_at?->toDateString(),
            ],
            'categories' => [
                ...$row->only(['id', 'company_id', 'name', 'code', 'is_active']),
                'deleted_at' => $row->deleted_at?->toISOString(),
                'created_at' => $row->created_at?->toDateString(),
            ],
        };
    }
}
