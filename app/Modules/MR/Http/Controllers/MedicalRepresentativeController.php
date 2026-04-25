<?php

namespace App\Modules\MR\Http\Controllers;

use App\Core\DTOs\TableQueryData;
use App\Core\Support\WorkspaceScope;
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

        $page = $service->representatives(TableQueryData::fromRequest($request, ['is_active']), $request->user());

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
        WorkspaceScope::ensure($representative, $request->user(), ['tenant_id', 'company_id']);

        return response()->json([
            'data' => $service->updateRepresentative($representative, $request->validated(), $request->user()),
            'message' => 'Medical representative updated.',
        ]);
    }

    public function destroy(Request $request, MedicalRepresentative $representative, MrManagementService $service): JsonResponse
    {
        abort_unless($request->user()?->is_owner || $request->user()?->can('mr.manage'), 403);
        WorkspaceScope::ensure($representative, $request->user(), ['tenant_id', 'company_id']);

        $service->deleteRepresentative($representative, $request->user());

        return response()->json(['message' => 'Medical representative deleted.']);
    }

    public function options(Request $request): JsonResponse
    {
        abort_unless($request->user()?->is_owner || $request->user()?->can('mr.view') || $request->user()?->can('sales.invoices.create'), 403);

        return response()->json([
            'data' => WorkspaceScope::apply(MedicalRepresentative::query(), $request->user(), 'medical_representatives', ['tenant_id', 'company_id'])
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'territory', 'monthly_target']),
        ]);
    }
}
