<?php

namespace App\Modules\Party\Http\Controllers;

use App\Core\DTOs\TableQueryData;
use App\Core\Support\WorkspaceScope;
use App\Http\Controllers\Controller;
use App\Modules\Party\Http\Requests\PartyIndexRequest;
use App\Modules\Party\Http\Requests\SupplierRequest;
use App\Modules\Party\Http\Resources\PartyResource;
use App\Modules\Party\Models\Supplier;
use App\Modules\Party\Services\PartyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(PartyIndexRequest $request, PartyService $service)
    {
        abort_unless($request->user()?->is_owner || $request->user()?->can('party.suppliers.view'), 403);

        return PartyResource::collection($service->suppliers(TableQueryData::fromRequest($request, ['is_active']), $request->user()));
    }

    public function store(SupplierRequest $request, PartyService $service): JsonResponse
    {
        $supplier = $service->createSupplier($request->validated(), $request->user());

        return (new PartyResource($supplier))
            ->additional(['message' => 'Supplier created.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(SupplierRequest $request, Supplier $supplier, PartyService $service): PartyResource
    {
        WorkspaceScope::ensure($supplier, $request->user(), ['tenant_id', 'company_id']);

        return new PartyResource($service->updateSupplier($supplier, $request->validated(), $request->user()));
    }

    public function destroy(Request $request, Supplier $supplier): JsonResponse
    {
        abort_unless($request->user()?->is_owner || $request->user()?->can('party.suppliers.manage'), 403);
        WorkspaceScope::ensure($supplier, $request->user(), ['tenant_id', 'company_id']);
        $supplier->forceFill(['is_active' => false, 'updated_by' => $request->user()?->id])->save();
        $supplier->delete();

        return response()->json(['message' => 'Supplier deleted.']);
    }

    public function options(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()?->is_owner
            || $request->user()?->can('party.suppliers.view')
            || $request->user()?->can('party.suppliers.manage')
            || $request->user()?->can('purchase.entries.create')
            || $request->user()?->can('purchase.orders.manage')
            || $request->user()?->can('accounting.vouchers.create')
            || $request->user()?->can('reports.view'),
            403
        );

        return response()->json([
            'data' => WorkspaceScope::apply(Supplier::query(), $request->user(), 'suppliers', ['tenant_id', 'company_id'])
                ->where('is_active', true)
                ->orderBy('name')
                ->limit(50)
                ->get(['id', 'name', 'phone', 'current_balance']),
        ]);
    }
}
