<?php

namespace App\Modules\MR\Http\Controllers;

use App\Core\DTOs\TableQueryData;
use App\Core\Support\WorkspaceScope;
use App\Http\Controllers\Controller;
use App\Modules\MR\Http\Requests\RepresentativeVisitRequest;
use App\Modules\MR\Models\RepresentativeVisit;
use App\Modules\MR\Services\MrManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RepresentativeVisitController extends Controller
{
    public function index(Request $request, MrManagementService $service): JsonResponse
    {
        abort_unless($request->user()?->is_owner || $request->user()?->can('mr.view') || $request->user()?->can('mr.visits.manage'), 403);

        $page = $service->visits(TableQueryData::fromRequest($request, ['medical_representative_id', 'status']), $request->user());

        return response()->json([
            'data' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    public function store(RepresentativeVisitRequest $request, MrManagementService $service): JsonResponse
    {
        return response()->json([
            'data' => $service->createVisit($request->validated(), $request->user()),
            'message' => 'Representative visit saved.',
        ], 201);
    }

    public function update(RepresentativeVisitRequest $request, RepresentativeVisit $visit, MrManagementService $service): JsonResponse
    {
        WorkspaceScope::ensure($visit->loadMissing('medicalRepresentative')->medicalRepresentative, $request->user(), ['tenant_id', 'company_id']);

        return response()->json([
            'data' => $service->updateVisit($visit, $request->validated(), $request->user()),
            'message' => 'Representative visit updated.',
        ]);
    }

    public function destroy(Request $request, RepresentativeVisit $visit, MrManagementService $service): JsonResponse
    {
        abort_unless($request->user()?->is_owner || $request->user()?->can('mr.visits.manage') || $request->user()?->can('mr.manage'), 403);
        WorkspaceScope::ensure($visit->loadMissing('medicalRepresentative')->medicalRepresentative, $request->user(), ['tenant_id', 'company_id']);

        $service->deleteVisit($visit);

        return response()->json(['message' => 'Representative visit deleted.']);
    }
}
