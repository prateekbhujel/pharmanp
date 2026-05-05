<?php

namespace App\Modules\Setup\Http\Controllers;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Core\DTOs\TableQueryData;
use App\Http\Controllers\ModularController;
use App\Modules\Setup\Http\Requests\AreaRequest;
use App\Modules\Setup\Http\Resources\AreaResource;
use App\Modules\Setup\Models\Area;
use App\Modules\Setup\Services\OrganizationStructureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="SETUP - Administration",
 *     description="API endpoints for SETUP - Administration"
 * )
 */
class AreaController extends ModularController
{
    /**
     * @OA\Get(
     *     path="/setup/areas",
     *     summary="Api Areas Index",
     *     tags={"SETUP - Areas"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function index(Request $request, OrganizationStructureService $service): JsonResponse
    {
        $service->assertMayManageOrgStructure($request->user());

        $page = $service->areas(TableQueryData::fromRequest($request, ['branch_id', 'is_active', 'deleted']), $request->user());

        return response()->json(AreaResource::collection($page)->response()->getData(true));
    }

    /**
     * @OA\Post(
     *     path="/setup/areas",
     *     summary="Api Areas Store",
     *     tags={"SETUP - Areas"},
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
    public function store(AreaRequest $request, OrganizationStructureService $service): JsonResponse
    {
        $area = $service->saveArea(new Area, $request->validated(), $request->user());

        return (new AreaResource($area))
            ->additional(['message' => 'Area created.'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * @OA\Put(
     *     path="/setup/areas/{area}",
     *     summary="Api Areas Update",
     *     tags={"SETUP - Areas"},
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
    public function update(AreaRequest $request, Area $area, OrganizationStructureService $service): AreaResource
    {
        return new AreaResource($service->saveArea($area, $request->validated(), $request->user()));
    }

    /**
     * @OA\Delete(
     *     path="/setup/areas/{area}",
     *     summary="Api Areas Destroy",
     *     tags={"SETUP - Areas"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function destroy(Request $request, Area $area, OrganizationStructureService $service): JsonResponse
    {
        $service->assertMayManageOrgStructure($request->user());
        $service->deleteArea($area, $request->user());

        return response()->json(['message' => 'Area deleted.']);
    }

    /**
     * @OA\Post(
     *     path="/setup/areas/{id}/restore",
     *     summary="Api Setup Areas Restore",
     *     tags={"SETUP - Areas"},
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
    public function restore(Request $request, int $id, OrganizationStructureService $service): JsonResponse
    {
        $service->assertMayManageOrgStructure($request->user());

        $area = $service->restoreArea($id, $request->user());

        return (new AreaResource($area))
            ->additional(['message' => 'Area restored.'])
            ->response();
    }

    /**
     * @OA\Get(
     *     path="/setup/areas/options",
     *     summary="Api Setup Areas Options",
     *     tags={"SETUP - Areas"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function options(Request $request, OrganizationStructureService $service): JsonResponse
    {
        return response()->json(['data' => $service->options('areas', $request->user(), $request->query('search'))]);
    }
}
