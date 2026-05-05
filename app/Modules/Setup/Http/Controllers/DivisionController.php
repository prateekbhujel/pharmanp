<?php

namespace App\Modules\Setup\Http\Controllers;

use App\Core\DTOs\TableQueryData;
use App\Http\Controllers\ModularController;
use App\Modules\Setup\Http\Requests\DivisionRequest;
use App\Modules\Setup\Http\Resources\DivisionResource;
use App\Modules\Setup\Models\Division;
use App\Modules\Setup\Services\OrganizationStructureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="SETUP - Administration",
 *     description="API endpoints for SETUP - Administration"
 * )
 */
class DivisionController extends ModularController
{
    /**
     * @OA\Get(
     *     path="/setup/divisions",
     *     summary="Api Divisions Index",
     *     tags={"SETUP - Divisions"},
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

        $page = $service->divisions(TableQueryData::fromRequest($request, ['is_active', 'deleted']), $request->user());

        return response()->json(DivisionResource::collection($page)->response()->getData(true));
    }

    /**
     * @OA\Post(
     *     path="/setup/divisions",
     *     summary="Api Divisions Store",
     *     tags={"SETUP - Divisions"},
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
    public function store(DivisionRequest $request, OrganizationStructureService $service): JsonResponse
    {
        $division = $service->saveDivision(new Division, $request->validated(), $request->user());

        return (new DivisionResource($division))
            ->additional(['message' => 'Division created.'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * @OA\Put(
     *     path="/setup/divisions/{division}",
     *     summary="Api Divisions Update",
     *     tags={"SETUP - Divisions"},
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
    public function update(DivisionRequest $request, Division $division, OrganizationStructureService $service): DivisionResource
    {
        return new DivisionResource($service->saveDivision($division, $request->validated(), $request->user()));
    }

    /**
     * @OA\Delete(
     *     path="/setup/divisions/{division}",
     *     summary="Api Divisions Destroy",
     *     tags={"SETUP - Divisions"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function destroy(Request $request, Division $division, OrganizationStructureService $service): JsonResponse
    {
        $service->assertMayManageOrgStructure($request->user());
        $service->deleteDivision($division, $request->user());

        return response()->json(['message' => 'Division deleted.']);
    }

    /**
     * @OA\Post(
     *     path="/setup/divisions/{id}/restore",
     *     summary="Api Setup Divisions Restore",
     *     tags={"SETUP - Divisions"},
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

        $division = $service->restoreDivision($id, $request->user());

        return (new DivisionResource($division))
            ->additional(['message' => 'Division restored.'])
            ->response();
    }

    /**
     * @OA\Get(
     *     path="/setup/divisions/options",
     *     summary="Api Setup Divisions Options",
     *     tags={"SETUP - Divisions"},
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
        return response()->json(['data' => $service->options('divisions', $request->user(), $request->query('search'))]);
    }
}
