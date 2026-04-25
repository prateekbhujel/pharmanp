<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Core\DTOs\TableQueryData;
use App\Core\Support\WorkspaceScope;
use App\Http\Controllers\Controller;
use App\Modules\Inventory\DTOs\ProductData;
use App\Modules\Inventory\Http\Requests\ProductIndexRequest;
use App\Modules\Inventory\Http\Requests\ProductStoreRequest;
use App\Modules\Inventory\Http\Requests\ProductUpdateRequest;
use App\Modules\Inventory\Http\Resources\ProductResource;
use App\Modules\Inventory\Models\Company;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\ProductCategory;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Inventory\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(ProductIndexRequest $request, ProductService $service)
    {
        $this->authorize('viewAny', Product::class);

        $products = $service->paginate(TableQueryData::fromRequest($request, [
            'company_id',
            'category_id',
            'is_active',
        ]), $request->user());

        return ProductResource::collection($products);
    }

    public function store(ProductStoreRequest $request, ProductService $service): JsonResponse
    {
        $this->authorize('create', Product::class);

        $product = $service->create(ProductData::fromArray($request->validated()), $request->user());

        return (new ProductResource($product))
            ->additional(['message' => 'Product created.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(ProductUpdateRequest $request, Product $product, ProductService $service): ProductResource
    {
        WorkspaceScope::ensure($product, $request->user(), ['tenant_id', 'company_id']);
        $this->authorize('update', $product);

        $product = $service->update($product, ProductData::fromArray($request->validated()), $request->user());

        return new ProductResource($product);
    }

    public function destroy(Request $request, Product $product, ProductService $service): JsonResponse
    {
        WorkspaceScope::ensure($product, $request->user(), ['tenant_id', 'company_id']);
        $this->authorize('delete', $product);

        $service->delete($product, $request->user());

        return response()->json(['message' => 'Product deleted.']);
    }

    public function meta(): JsonResponse
    {
        $this->authorize('viewAny', Product::class);
        $user = request()->user();

        return response()->json([
            'data' => [
                'companies' => WorkspaceScope::apply(Company::query(), $user, 'companies', ['tenant_id'])
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name']),
                'units' => WorkspaceScope::apply(Unit::query(), $user, 'units', ['tenant_id', 'company_id'])
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name']),
                'categories' => WorkspaceScope::apply(ProductCategory::query(), $user, 'product_categories', ['tenant_id', 'company_id'])
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name']),
                'formulations' => ['Tablet', 'Capsule', 'Syrup', 'Injection', 'Ointment', 'Drops', 'Inhaler', 'Other'],
            ],
        ]);
    }
}
