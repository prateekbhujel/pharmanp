<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Http\Requests\QuickCategoryRequest;
use App\Modules\Inventory\Http\Requests\QuickCompanyRequest;
use App\Modules\Inventory\Http\Requests\QuickUnitRequest;
use App\Modules\Inventory\Models\Company;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\ProductCategory;
use App\Modules\Inventory\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class InventoryMasterController extends Controller
{
    public function company(QuickCompanyRequest $request): JsonResponse
    {
        $this->authorize('create', Product::class);

        $company = DB::transaction(fn () => Company::query()->create([
            ...$request->validated(),
            'tenant_id' => $request->user()?->tenant_id,
            'company_type' => $request->validated('company_type', 'manufacturer'),
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
}
