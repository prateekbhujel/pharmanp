<?php

namespace App\Modules\Setup\Http\Controllers;

use App\Core\DTOs\TableQueryData;
use App\Http\Controllers\Controller;
use App\Modules\Setup\Contracts\OrganizationStructureServiceInterface;
use App\Modules\Setup\Http\Requests\DivisionRequest;
use App\Modules\Setup\Http\Resources\DivisionResource;
use App\Modules\Setup\Models\Division;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DivisionController extends Controller
{
    public function index(Request $request, OrganizationStructureServiceInterface $service): JsonResponse
    {
        $this->authorizeManage($request);

        $page = $service->divisions(TableQueryData::fromRequest($request, ['is_active', 'deleted']), $request->user());

        return response()->json(DivisionResource::collection($page)->response()->getData(true));
    }

    public function store(DivisionRequest $request, OrganizationStructureServiceInterface $service): JsonResponse
    {
        $division = $service->saveDivision(new Division(), $request->validated(), $request->user());

        return (new DivisionResource($division))
            ->additional(['message' => 'Division created.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(DivisionRequest $request, Division $division, OrganizationStructureServiceInterface $service): DivisionResource
    {
        return new DivisionResource($service->saveDivision($division, $request->validated(), $request->user()));
    }

    public function destroy(Request $request, Division $division, OrganizationStructureServiceInterface $service): JsonResponse
    {
        $this->authorizeManage($request);
        $service->deleteDivision($division, $request->user());

        return response()->json(['message' => 'Division deleted.']);
    }

    public function restore(Request $request, int $id): JsonResponse
    {
        $this->authorizeManage($request);

        $division = Division::query()
            ->onlyTrashed()
            ->when($request->user()?->tenant_id, fn ($query, $tenantId) => $query->where('tenant_id', $tenantId))
            ->when($request->user()?->company_id, fn ($query, $companyId) => $query->where('company_id', $companyId))
            ->findOrFail($id);

        DB::transaction(function () use ($division, $request) {
            $division->restore();
            $division->forceFill(['is_active' => true, 'updated_by' => $request->user()->id])->save();
        });

        return (new DivisionResource($division->fresh()))
            ->additional(['message' => 'Division restored.'])
            ->response();
    }

    public function options(Request $request, OrganizationStructureServiceInterface $service): JsonResponse
    {
        return response()->json(['data' => $service->options('divisions', $request->user(), $request->query('search'))]);
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()?->is_owner || $request->user()?->can('settings.manage') || $request->user()?->can('mr.manage'), 403);
    }
}
