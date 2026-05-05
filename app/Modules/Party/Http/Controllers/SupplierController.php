<?php

namespace App\Modules\Party\Http\Controllers;

use App\Core\DTOs\TableQueryData;
use App\Http\Controllers\ModularController;
use App\Modules\Party\Http\Requests\PartyIndexRequest;
use App\Modules\Party\Http\Requests\SupplierRequest;
use App\Modules\Party\Http\Resources\PartyResource;
use App\Modules\Party\Models\Supplier;
use App\Modules\Party\Services\PartyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="PARTY - Customers and Suppliers",
 *     description="API endpoints for PARTY - Customers and Suppliers"
 * )
 */
class SupplierController extends ModularController
{
    /**
     * @OA\Get(
     *     path="/suppliers",
     *     summary="Api Suppliers Index",
     *     tags={"PARTY - Suppliers"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function index(PartyIndexRequest $request, PartyService $service)
    {
        return PartyResource::collection($service->suppliers(TableQueryData::fromRequest($request, ['is_active', 'deleted']), $request->user()));
    }

    /**
     * @OA\Post(
     *     path="/suppliers",
     *     summary="Api Suppliers Store",
     *     tags={"PARTY - Suppliers"},
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
    public function store(SupplierRequest $request, PartyService $service): JsonResponse
    {
        $supplier = $service->createSupplier($request->validated(), $request->user());

        return (new PartyResource($supplier))
            ->additional(['message' => 'Supplier created.'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * @OA\Put(
     *     path="/suppliers/{supplier}",
     *     summary="Api Suppliers Update",
     *     tags={"PARTY - Suppliers"},
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
    public function update(SupplierRequest $request, Supplier $supplier, PartyService $service): PartyResource
    {
        return new PartyResource($service->updateSupplier($supplier, $request->validated(), $request->user()));
    }

    /**
     * @OA\Delete(
     *     path="/suppliers/{supplier}",
     *     summary="Api Suppliers Destroy",
     *     tags={"PARTY - Suppliers"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function destroy(Request $request, Supplier $supplier, PartyService $service): JsonResponse
    {
        $service->deleteSupplier($supplier, $request->user());

        return response()->json(['message' => 'Supplier deleted.']);
    }

    /**
     * @OA\Patch(
     *     path="/suppliers/{supplier}/status",
     *     summary="Api Suppliers Status",
     *     tags={"PARTY - Suppliers"},
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
    public function toggleStatus(Request $request, Supplier $supplier, PartyService $service): JsonResponse
    {
        $supplier = $service->setSupplierStatus($supplier, $request->boolean('is_active'), $request->user());

        return response()->json(['message' => 'Supplier status updated.', 'data' => new PartyResource($supplier)]);
    }

    /**
     * @OA\Post(
     *     path="/suppliers/{id}/restore",
     *     summary="Api Suppliers Restore",
     *     tags={"PARTY - Suppliers"},
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
    public function restore(Request $request, int $id, PartyService $service): JsonResponse
    {
        $supplier = $service->restoreSupplier($id, $request->user());

        return response()->json(['message' => 'Supplier restored.', 'data' => new PartyResource($supplier)]);
    }

    /**
     * @OA\Get(
     *     path="/suppliers/options",
     *     summary="Api Suppliers Options",
     *     tags={"PARTY - Suppliers"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function options(Request $request, PartyService $service): JsonResponse
    {
        return response()->json([
            'data' => $service->supplierOptions($request->user()),
        ]);
    }
}
