<?php

namespace App\Modules\Setup\Http\Controllers;

use App\Core\DTOs\TableQueryData;
use App\Http\Controllers\Controller;
use App\Modules\Setup\Http\Requests\AreaRequest;
use App\Modules\Setup\Http\Resources\AreaResource;
use App\Modules\Setup\Models\Area;
use App\Modules\Setup\Services\OrganizationStructureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AreaController extends Controller
{
    public function index(Request $request, OrganizationStructureService $service): JsonResponse
    {
        $this->authorizeManage($request);

        $page = $service->areas(TableQueryData::fromRequest($request, ['branch_id', 'is_active', 'deleted']), $request->user());

        return response()->json(AreaResource::collection($page)->response()->getData(true));
    }

    public function store(AreaRequest $request, OrganizationStructureService $service): JsonResponse
    {
        $area = $service->saveArea(new Area, $request->validated(), $request->user());

        return (new AreaResource($area))
            ->additional(['message' => 'Area created.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(AreaRequest $request, Area $area, OrganizationStructureService $service): AreaResource
    {
        return new AreaResource($service->saveArea($area, $request->validated(), $request->user()));
    }

    public function destroy(Request $request, Area $area, OrganizationStructureService $service): JsonResponse
    {
        $this->authorizeManage($request);
        $service->deleteArea($area, $request->user());

        return response()->json(['message' => 'Area deleted.']);
    }

    public function restore(Request $request, int $id): JsonResponse
    {
        $this->authorizeManage($request);

        $area = Area::query()
            ->onlyTrashed()
            ->when($request->user()?->tenant_id, fn ($query, $tenantId) => $query->where('tenant_id', $tenantId))
            ->when($request->user()?->company_id, fn ($query, $companyId) => $query->where('company_id', $companyId))
            ->findOrFail($id);

        DB::transaction(function () use ($area, $request) {
            $area->restore();
            $area->forceFill(['is_active' => true, 'updated_by' => $request->user()->id])->save();
        });

        return (new AreaResource($area->fresh('branch:id,name,code,type')))
            ->additional(['message' => 'Area restored.'])
            ->response();
    }

    public function options(Request $request, OrganizationStructureService $service): JsonResponse
    {
        return response()->json(['data' => $service->options('areas', $request->user(), $request->query('search'))]);
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()?->is_owner || $request->user()?->can('settings.manage') || $request->user()?->can('mr.manage'), 403);
    }
}
