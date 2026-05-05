<?php

namespace App\Modules\MR\Http\Controllers;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Core\DTOs\TableQueryData;
use App\Http\Controllers\ModularController;
use App\Modules\MR\Http\Requests\RepresentativeVisitRequest;
use App\Modules\MR\Http\Resources\RepresentativeVisitResource;
use App\Modules\MR\Models\RepresentativeVisit;
use App\Modules\MR\Services\MrManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="FIELD FORCE - MR Tracking",
 *     description="API endpoints for FIELD FORCE - MR Tracking"
 * )
 */
class RepresentativeVisitController extends ModularController
{
    /**
     * @OA\Get(
     *     path="/mr/visits",
     *     summary="Api Visits Index",
     *     tags={"FIELD FORCE - Visits"},
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
        abort_unless($request->user()?->is_owner || $request->user()?->can('mr.view') || $request->user()?->can('mr.visits.manage'), 403);

        $page = $service->visits(
            TableQueryData::fromRequest($request, ['medical_representative_id', 'employee_id', 'status', 'from', 'to']),
            $request->user(),
        );

        return RepresentativeVisitResource::collection($page)->response();
    }

    /**
     * @OA\Post(
     *     path="/mr/visits",
     *     summary="Api Visits Store",
     *     tags={"FIELD FORCE - Visits"},
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
    public function store(RepresentativeVisitRequest $request, MrManagementService $service): JsonResponse
    {
        return response()->json([
            'data' => RepresentativeVisitResource::make($service->createVisit($request->validated(), $request->user())),
            'message' => 'Representative visit saved.',
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/mr/visits/{visit}",
     *     summary="Api Visits Update",
     *     tags={"FIELD FORCE - Visits"},
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
    public function update(RepresentativeVisitRequest $request, RepresentativeVisit $visit, MrManagementService $service): JsonResponse
    {
        return response()->json([
            'data' => RepresentativeVisitResource::make($service->updateVisit($visit, $request->validated(), $request->user())),
            'message' => 'Representative visit updated.',
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/mr/visits/{visit}",
     *     summary="Api Visits Destroy",
     *     tags={"FIELD FORCE - Visits"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function destroy(Request $request, RepresentativeVisit $visit, MrManagementService $service): JsonResponse
    {
        abort_unless($request->user()?->is_owner || $request->user()?->can('mr.visits.manage') || $request->user()?->can('mr.manage'), 403);

        $service->deleteVisit($visit);

        return response()->json(['message' => 'Representative visit deleted.']);
    }
}
