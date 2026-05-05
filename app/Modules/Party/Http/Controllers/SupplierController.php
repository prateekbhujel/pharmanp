<?php

namespace App\Modules\Party\Http\Controllers;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

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
    public function destroy(Request $request, Supplier $supplier): JsonResponse
    {
        $supplier->forceFill(['is_active' => false, 'updated_by' => $request->user()?->id])->save();
        $supplier->delete();

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
    public function toggleStatus(Request $request, Supplier $supplier): JsonResponse
    {
        $supplier->forceFill([
            'is_active' => $request->boolean('is_active'),
            'updated_by' => $request->user()?->id,
        ])->save();

        return response()->json(['message' => 'Supplier status updated.', 'data' => new PartyResource($supplier->refresh())]);
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
    public function restore(Request $request, int $id): JsonResponse
    {
        $supplier = Supplier::query()->onlyTrashed()->findOrFail($id);
        $supplier->restore();
        $supplier->forceFill(['is_active' => true, 'updated_by' => $request->user()?->id])->save();

        return response()->json(['message' => 'Supplier restored.', 'data' => new PartyResource($supplier->refresh())]);
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
    public function options(): JsonResponse
    {
        return response()->json([
            'data' => Supplier::query()
                ->where('is_active', true)
                ->when(request()->user()?->tenant_id, fn ($query, $tenantId) => $query->where('tenant_id', $tenantId))
                ->orderBy('name')
                ->limit(50)
                ->get(['id', 'name', 'phone', 'current_balance']),
        ]);
    }
}
