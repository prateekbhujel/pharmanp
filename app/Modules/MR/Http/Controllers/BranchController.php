<?php

namespace App\Modules\MR\Http\Controllers;

use App\Core\DTOs\TableQueryData;
use App\Http\Controllers\ModularController;
use App\Modules\MR\Http\Requests\BranchRequest;
use App\Modules\MR\Models\Branch;
use App\Modules\MR\Services\BranchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="FIELD FORCE - MR Tracking",
 *     description="API endpoints for FIELD FORCE - MR Tracking"
 * )
 */
class BranchController extends ModularController
{
    public function __construct(private readonly BranchService $branches) {}

    /**
     * @OA\Get(
     *     path="/mr/branches",
     *     summary="Api Branches Index",
     *     tags={"FIELD FORCE - Branches"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->branches->table(
            TableQueryData::fromRequest($request, ['deleted', 'type', 'is_active']),
            $request->user(),
        ));
    }

    /**
     * @OA\Post(
     *     path="/mr/branches",
     *     summary="Api Branches Store",
     *     tags={"FIELD FORCE - Branches"},
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
    public function store(BranchRequest $request): JsonResponse
    {
        $branch = $this->branches->create($request->validated(), $request->user());

        return response()->json([
            'message' => "Branch '{$branch->name}' created.",
            'data' => $this->branches->payload($branch),
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/mr/branches/{branch}",
     *     summary="Api Branches Update",
     *     tags={"FIELD FORCE - Branches"},
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
    public function update(BranchRequest $request, Branch $branch): JsonResponse
    {
        $branch = $this->branches->update($branch, $request->validated(), $request->user());

        return response()->json([
            'message' => "Branch '{$branch->name}' updated.",
            'data' => $this->branches->payload($branch),
        ]);
    }

    /**
     * @OA\Patch(
     *     path="/mr/branches/{branch}/status",
     *     summary="Api Mr Branches Status",
     *     tags={"FIELD FORCE - Branches"},
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
    public function toggleStatus(Request $request, Branch $branch): JsonResponse
    {
        $validated = $request->validate(['is_active' => ['required', 'boolean']]);
        $branch = $this->branches->toggleStatus($branch, (bool) $validated['is_active'], $request->user());

        return response()->json([
            'message' => 'Branch status updated.',
            'data' => $this->branches->payload($branch),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/mr/branches/{branch}",
     *     summary="Api Branches Destroy",
     *     tags={"FIELD FORCE - Branches"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function destroy(Request $request, Branch $branch): JsonResponse
    {
        $this->branches->delete($branch, $request->user());

        return response()->json(['message' => 'Branch deleted.']);
    }

    /**
     * @OA\Post(
     *     path="/mr/branches/{id}/restore",
     *     summary="Api Mr Branches Restore",
     *     tags={"FIELD FORCE - Branches"},
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
        $branch = $this->branches->restore($id, $request->user());

        return response()->json([
            'message' => 'Branch restored.',
            'data' => $this->branches->payload($branch),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/mr/branches/options",
     *     summary="Api Mr Branches Options",
     *     tags={"FIELD FORCE - Branches"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function options(Request $request): JsonResponse
    {
        return response()->json($this->branches->options($request->user()));
    }
}
