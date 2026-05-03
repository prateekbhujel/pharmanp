<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Core\DTOs\TableQueryData;
use App\Http\Controllers\ModularController;
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
use App\Modules\Setup\Models\Division;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="INVENTORY - Products and Stock",
 *     description="API endpoints for INVENTORY - Products and Stock"
 * )
 */
class ProductController extends ModularController
{
    /**
     * @OA\Get(
     *     path="/inventory/products",
     *     summary="Api Products Index",
     *     tags={"INVENTORY - Products"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function index(ProductIndexRequest $request, ProductService $service)
    {
        $this->authorize('viewAny', Product::class);

        $products = $service->paginate(TableQueryData::fromRequest($request, [
            'company_id',
            'category_id',
            'division_id',
            'is_active',
            'deleted',
        ]), $request->user());

        return ProductResource::collection($products);
    }

    /**
     * @OA\Post(
     *     path="/inventory/products",
     *     summary="Api Products Store",
     *     tags={"INVENTORY - Products"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\RequestBody(required=false, @OA\JsonContent(type="object", additionalProperties=true)),
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(ProductStoreRequest $request, ProductService $service): JsonResponse
    {
        $this->authorize('create', Product::class);

        $product = $service->create(
            ProductData::fromArray($request->validated()),
            $request->user(),
            $request->file('image')
        );

        return (new ProductResource($product))
            ->additional(['message' => 'Product created.'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * @OA\Put(
     *     path="/inventory/products/{product}",
     *     summary="Api Products Update",
     *     tags={"INVENTORY - Products"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\RequestBody(required=false, @OA\JsonContent(type="object", additionalProperties=true)),
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(ProductUpdateRequest $request, Product $product, ProductService $service): ProductResource
    {
        $this->authorize('update', $product);

        $product = $service->update(
            $product,
            ProductData::fromArray($request->validated()),
            $request->user(),
            $request->file('image'),
            $request->boolean('remove_image')
        );

        return new ProductResource($product);
    }

    /**
     * @OA\Delete(
     *     path="/inventory/products/{product}",
     *     summary="Api Products Destroy",
     *     tags={"INVENTORY - Products"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function destroy(Request $request, Product $product, ProductService $service): JsonResponse
    {
        $this->authorize('delete', $product);

        $service->delete($product, $request->user());

        return response()->json(['message' => 'Product deleted.']);
    }

    /**
     * @OA\Post(
     *     path="/inventory/products/{id}/restore",
     *     summary="Api Inventory Products Restore",
     *     tags={"INVENTORY - Products"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\RequestBody(required=false, @OA\JsonContent(type="object", additionalProperties=true)),
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function restore(Request $request, int $id, ProductService $service): JsonResponse
    {
        abort_unless($request->user()?->is_owner || $request->user()?->can('inventory.products.update'), 403);

        $product = $service->restore($id, $request->user());

        return (new ProductResource($product))
            ->additional(['message' => 'Product restored.'])
            ->response();
    }

    /**
     * @OA\Patch(
     *     path="/inventory/products/{product}/status",
     *     summary="Api Inventory Products Status",
     *     tags={"INVENTORY - Products"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\RequestBody(required=false, @OA\JsonContent(type="object", additionalProperties=true)),
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function toggleStatus(Request $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $product->update([
            'is_active' => $request->boolean('is_active'),
            'updated_by' => $request->user()->id,
        ]);

        return response()->json(['message' => 'Status updated.']);
    }

    /**
     * @OA\Get(
     *     path="/inventory/products/meta",
     *     summary="Api Inventory Products Meta",
     *     tags={"INVENTORY - Products"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function meta(): JsonResponse
    {
        $this->authorize('viewAny', Product::class);

        return response()->json([
            'data' => [
                'companies' => Company::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
                'units' => Unit::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
                'categories' => ProductCategory::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
                'divisions' => Division::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
                'formulations' => ['Tablet', 'Capsule', 'Syrup', 'Injection', 'Ointment', 'Drops', 'Inhaler', 'Other'],
            ],
        ]);
    }
}
