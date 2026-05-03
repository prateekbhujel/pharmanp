<?php

namespace App\Modules\MR\Http\Controllers;

use App\Core\DTOs\TableQueryData;
use App\Core\Support\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\MR\Http\Requests\MedicalRepresentativeRequest;
use App\Modules\MR\Models\MedicalRepresentative;
use App\Modules\MR\Services\MrManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MedicalRepresentativeController extends Controller
{
    public function index(Request $request, MrManagementService $service): JsonResponse
    {
        abort_unless($request->user()?->is_owner || $request->user()?->can('mr.view'), 403);

        $page = $service->representatives(TableQueryData::fromRequest($request, ['is_active', 'branch_id', 'area_id', 'division_id']), $request->user());

        return response()->json([
            'data' => $page->items(),
            'meta' => ApiResponse::paginationMeta($page),
        ]);
    }

    public function store(MedicalRepresentativeRequest $request, MrManagementService $service): JsonResponse
    {
        $representative = $service->createRepresentative($request->validated(), $request->user());

        return response()->json([
            'data' => $representative,
            'message' => 'Medical representative created.',
        ], 201);
    }

    public function update(MedicalRepresentativeRequest $request, MedicalRepresentative $representative, MrManagementService $service): JsonResponse
    {
        return response()->json([
            'data' => $service->updateRepresentative($representative, $request->validated(), $request->user()),
            'message' => 'Medical representative updated.',
        ]);
    }

    public function destroy(Request $request, MedicalRepresentative $representative, MrManagementService $service): JsonResponse
    {
        abort_unless($request->user()?->is_owner || $request->user()?->can('mr.manage'), 403);

        $service->deleteRepresentative($representative, $request->user());

        return response()->json(['message' => 'Medical representative deleted.']);
    }

    public function options(Request $request): JsonResponse
    {
        abort_unless($request->user()?->is_owner || $request->user()?->can('mr.view') || $request->user()?->can('sales.invoices.create'), 403);

        return response()->json([
            'data' => MedicalRepresentative::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'employee_code', 'area_id', 'division_id', 'territory', 'monthly_target']),
        ]);
    }
}
