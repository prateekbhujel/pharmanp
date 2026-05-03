<?php

namespace App\Modules\MR\Http\Controllers;

use App\Core\DTOs\TableQueryData;
use App\Core\Support\ApiResponse;
use App\Http\Controllers\ModularController;
use App\Modules\MR\Http\Requests\MedicalRepresentativeRequest;
use App\Modules\MR\Models\MedicalRepresentative;
use App\Modules\MR\Services\MrManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="FIELD FORCE - MR Tracking",
 *     description="API endpoints for FIELD FORCE - MR Tracking"
 * )
 */
class MedicalRepresentativeController extends ModularController
{
    /**
     * @OA\Get(
     *     path="/mr/representatives",
     *     summary="Api Representatives Index",
     *     tags={"FIELD FORCE - Representatives"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function index(Request $request, MrManagementService $service): JsonResponse
    {
        abort_unless($request->user()?->is_owner || $request->user()?->can('mr.view'), 403);

        $page = $service->representatives(TableQueryData::fromRequest($request, ['is_active', 'branch_id', 'area_id', 'division_id']), $request->user());

        return response()->json([
            'data' => $page->items(),
            'meta' => ApiResponse::paginationMeta($page),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/mr/representatives",
     *     summary="Api Representatives Store",
     *     tags={"FIELD FORCE - Representatives"},
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
    public function store(MedicalRepresentativeRequest $request, MrManagementService $service): JsonResponse
    {
        $representative = $service->createRepresentative($request->validated(), $request->user());

        return response()->json([
            'data' => $representative,
            'message' => 'Medical representative created.',
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/mr/representatives/{representative}",
     *     summary="Api Representatives Update",
     *     tags={"FIELD FORCE - Representatives"},
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
    public function update(MedicalRepresentativeRequest $request, MedicalRepresentative $representative, MrManagementService $service): JsonResponse
    {
        return response()->json([
            'data' => $service->updateRepresentative($representative, $request->validated(), $request->user()),
            'message' => 'Medical representative updated.',
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/mr/representatives/{representative}",
     *     summary="Api Representatives Destroy",
     *     tags={"FIELD FORCE - Representatives"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function destroy(Request $request, MedicalRepresentative $representative, MrManagementService $service): JsonResponse
    {
        abort_unless($request->user()?->is_owner || $request->user()?->can('mr.manage'), 403);

        $service->deleteRepresentative($representative, $request->user());

        return response()->json(['message' => 'Medical representative deleted.']);
    }

    /**
     * @OA\Get(
     *     path="/mr/options",
     *     summary="Api Mr Options",
     *     tags={"FIELD FORCE - Options"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function options(Request $request, MrManagementService $service): JsonResponse
    {
        abort_unless($request->user()?->is_owner || $request->user()?->can('mr.view') || $request->user()?->can('sales.invoices.create'), 403);

        return response()->json([
            'data' => $service->representativeOptions($request->user()),
        ]);
    }
}
