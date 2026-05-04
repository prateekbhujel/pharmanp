<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Core\DTOs\TableQueryData;
use App\Http\Controllers\ModularController;
use App\Modules\Inventory\Http\Requests\InventoryMasterRequest;
use App\Modules\Inventory\Http\Requests\QuickCompanyRequest;
use App\Modules\Inventory\Http\Requests\QuickUnitRequest;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Services\InventoryMasterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="INVENTORY - Products and Stock",
 *     description="API endpoints for INVENTORY - Products and Stock"
 * )
 */
class InventoryMasterController extends ModularController
{
    public function __construct(private readonly InventoryMasterService $masters) {}

    /**
     * @OA\Get(
     *     path="/inventory/masters/{master}",
     *     summary="Api Inventory Masters Index",
     *     tags={"INVENTORY - Masters"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function index(Request $request, string $master): JsonResponse
    {
        $this->authorize('viewAny', Product::class);

        return response()->json($this->masters->table(
            $master,
            TableQueryData::fromRequest($request, ['deleted']),
        ));
    }

    /**
     * @OA\Post(
     *     path="/inventory/masters/{master}",
     *     summary="Api Inventory Masters Store",
     *     tags={"INVENTORY - Masters"},
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
    public function store(InventoryMasterRequest $request, string $master): JsonResponse
    {
        $this->authorize('create', Product::class);
        $row = $this->masters->create($master, $request->validated(), $request->user());

        return response()->json(['message' => 'Inventory master saved.', 'data' => $this->masters->payload($master, $row)], 201);
    }

    /**
     * @OA\Put(
     *     path="/inventory/masters/{master}/{id}",
     *     summary="Api Inventory Masters Update",
     *     tags={"INVENTORY - Masters"},
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
    public function update(InventoryMasterRequest $request, string $master, int $id): JsonResponse
    {
        abort_unless($request->user()?->is_owner || $request->user()?->can('inventory.products.update'), 403);
        $row = $this->masters->update($master, $id, $request->validated(), $request->user());

        return response()->json(['message' => 'Inventory master updated.', 'data' => $this->masters->payload($master, $row)]);
    }

    /**
     * @OA\Patch(
     *     path="/inventory/masters/{master}/{id}/status",
     *     summary="Api Inventory Masters Status",
     *     tags={"INVENTORY - Masters"},
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
    public function toggleStatus(Request $request, string $master, int $id): JsonResponse
    {
        abort_unless($request->user()?->is_owner || $request->user()?->can('inventory.products.update'), 403);
        $row = $this->masters->toggleStatus($master, $id, $request->boolean('is_active'), $request->user());

        return response()->json(['message' => 'Inventory master status updated.', 'data' => $this->masters->payload($master, $row)]);
    }

    /**
     * @OA\Delete(
     *     path="/inventory/masters/{master}/{id}",
     *     summary="Api Inventory Masters Destroy",
     *     tags={"INVENTORY - Masters"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function destroy(Request $request, string $master, int $id): JsonResponse
    {
        abort_unless($request->user()?->is_owner || $request->user()?->can('inventory.products.delete'), 403);
        $this->masters->delete($master, $id);

        return response()->json(['message' => 'Inventory master deleted.']);
    }

    /**
     * @OA\Post(
     *     path="/inventory/masters/{master}/{id}/restore",
     *     summary="Api Inventory Masters Restore",
     *     tags={"INVENTORY - Masters"},
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
    public function restore(Request $request, string $master, int $id): JsonResponse
    {
        abort_unless($request->user()?->is_owner || $request->user()?->can('inventory.products.update'), 403);
        $row = $this->masters->restore($master, $id, $request->user());

        return response()->json(['message' => 'Inventory master restored.', 'data' => $this->masters->payload($master, $row)]);
    }

    /**
     * @OA\Post(
     *     path="/inventory/companies/quick",
     *     summary="Api Inventory Companies Quick",
     *     tags={"INVENTORY - Companies"},
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
    public function company(QuickCompanyRequest $request): JsonResponse
    {
        $this->authorize('create', Product::class);
        $company = $this->masters->quickCompany($request->validated(), $request->user());

        return response()->json(['message' => 'Company added.', 'data' => $company->only(['id', 'name'])], 201);
    }

    /**
     * @OA\Post(
     *     path="/inventory/units/quick",
     *     summary="Api Inventory Units Quick",
     *     tags={"INVENTORY - Units"},
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
    public function unit(QuickUnitRequest $request): JsonResponse
    {
        $this->authorize('create', Product::class);
        $unit = $this->masters->quickUnit($request->validated(), $request->user());

        return response()->json(['message' => 'Unit added.', 'data' => $unit->only(['id', 'name'])], 201);
    }

}
