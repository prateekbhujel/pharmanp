<?php

namespace App\Modules\Setup\Http\Controllers;

use App\Http\Controllers\ModularController;
use App\Modules\Setup\DTOs\SetupTypeData;
use App\Modules\Setup\Http\Requests\SupplierTypeRequest;
use App\Modules\Setup\Http\Resources\SetupTypeResource;
use App\Modules\Setup\Models\SupplierType;
use App\Modules\Setup\Services\SetupTypeService;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="SETUP - Administration",
 *     description="API endpoints for SETUP - Administration"
 * )
 */
class SupplierTypeController extends ModularController
{
    public function __construct(private readonly SetupTypeService $types) {}

    /**
     * @OA\Get(
     *     path="/settings/supplier-types",
     *     summary="Api Settings Supplier Types Index",
     *     tags={"SETUP - Supplier Types"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function index(): JsonResponse
    {
        return $this->resource(SetupTypeResource::collection($this->types->all(SupplierType::class)), 'Supplier types retrieved successfully.');
    }

    /**
     * @OA\Post(
     *     path="/settings/supplier-types",
     *     summary="Api Settings Supplier Types Store",
     *     tags={"SETUP - Supplier Types"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\RequestBody(required=false, @OA\JsonContent(ref="#/components/schemas/SupplierTypeRequest")),
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(SupplierTypeRequest $request): JsonResponse
    {
        return $this->resource(
            new SetupTypeResource($this->types->create(SupplierType::class, SetupTypeData::fromRequest($request))),
            'Supplier type created.',
            201,
        );
    }

    /**
     * @OA\Put(
     *     path="/settings/supplier-types/{supplierType}",
     *     summary="Api Settings Supplier Types Update",
     *     tags={"SETUP - Supplier Types"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\RequestBody(required=false, @OA\JsonContent(ref="#/components/schemas/SupplierTypeRequest")),
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(SupplierTypeRequest $request, SupplierType $supplierType): JsonResponse
    {
        return $this->resource(new SetupTypeResource($this->types->update($supplierType, SetupTypeData::fromRequest($request))), 'Supplier type updated.');
    }

    /**
     * @OA\Delete(
     *     path="/settings/supplier-types/{supplierType}",
     *     summary="Api Settings Supplier Types Destroy",
     *     tags={"SETUP - Supplier Types"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function destroy(SupplierType $supplierType): JsonResponse
    {
        $this->types->delete($supplierType);

        return $this->success(null, 'Supplier type deleted.');
    }
}
