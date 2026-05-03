<?php

namespace App\Modules\Setup\Http\Controllers;

use App\Core\DTOs\TableQueryData;
use App\Http\Controllers\ModularController;
use App\Modules\Setup\Http\Requests\TargetRequest;
use App\Modules\Setup\Http\Resources\TargetResource;
use App\Modules\Setup\Models\Target;
use App\Modules\Setup\Services\TargetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="SETUP - Administration",
 *     description="API endpoints for SETUP - Administration"
 * )
 */
class TargetController extends ModularController
{
    /**
     * @OA\Get(
     *     path="/setup/targets",
     *     summary="Api Targets Index",
     *     tags={"SETUP - Targets"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function index(Request $request, TargetService $service): JsonResponse
    {
        $this->authorizeManage($request);

        $page = $service->targets(TableQueryData::fromRequest($request, [
            'target_type',
            'target_period',
            'target_level',
            'status',
            'deleted',
        ]), $request->user());

        return response()->json(TargetResource::collection($page)->response()->getData(true));
    }

    /**
     * @OA\Post(
     *     path="/setup/targets",
     *     summary="Api Targets Store",
     *     tags={"SETUP - Targets"},
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
    public function store(TargetRequest $request, TargetService $service): JsonResponse
    {
        $target = $service->save(new Target, $request->validated(), $request->user());

        return (new TargetResource($target))
            ->additional(['message' => 'Target created.'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * @OA\Put(
     *     path="/setup/targets/{target}",
     *     summary="Api Targets Update",
     *     tags={"SETUP - Targets"},
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
    public function update(TargetRequest $request, Target $target, TargetService $service): TargetResource
    {
        return new TargetResource($service->save($target, $request->validated(), $request->user()));
    }

    /**
     * @OA\Delete(
     *     path="/setup/targets/{target}",
     *     summary="Api Targets Destroy",
     *     tags={"SETUP - Targets"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function destroy(Request $request, Target $target, TargetService $service): JsonResponse
    {
        $this->authorizeManage($request);
        $service->delete($target, $request->user());

        return response()->json(['message' => 'Target deleted.']);
    }

    /**
     * @OA\Post(
     *     path="/setup/targets/{id}/restore",
     *     summary="Api Setup Targets Restore",
     *     tags={"SETUP - Targets"},
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
        $this->authorizeManage($request);

        $target = Target::query()
            ->onlyTrashed()
            ->when($request->user()?->tenant_id, fn ($query, $tenantId) => $query->where('tenant_id', $tenantId))
            ->when($request->user()?->company_id, fn ($query, $companyId) => $query->where('company_id', $companyId))
            ->findOrFail($id);

        DB::transaction(function () use ($target, $request) {
            $target->restore();
            $target->forceFill(['status' => 'active', 'updated_by' => $request->user()->id])->save();
        });

        return (new TargetResource($target->fresh()))
            ->additional(['message' => 'Target restored.'])
            ->response();
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()?->is_owner || $request->user()?->can('mr.manage') || $request->user()?->can('reports.view'), 403);
    }
}
