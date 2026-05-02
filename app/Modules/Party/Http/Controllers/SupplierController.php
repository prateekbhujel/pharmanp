<?php

namespace App\Modules\Party\Http\Controllers;

use App\Core\DTOs\TableQueryData;
use App\Http\Controllers\Controller;
use App\Modules\Party\Http\Requests\PartyIndexRequest;
use App\Modules\Party\Http\Requests\SupplierRequest;
use App\Modules\Party\Http\Resources\PartyResource;
use App\Modules\Party\Models\Supplier;
use App\Modules\Party\Contracts\PartyServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(PartyIndexRequest $request, PartyServiceInterface $service)
    {
        return PartyResource::collection($service->suppliers(TableQueryData::fromRequest($request, ['is_active', 'deleted']), $request->user()));
    }

    public function store(SupplierRequest $request, PartyServiceInterface $service): JsonResponse
    {
        $supplier = $service->createSupplier($request->validated(), $request->user());

        return (new PartyResource($supplier))
            ->additional(['message' => 'Supplier created.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(SupplierRequest $request, Supplier $supplier, PartyServiceInterface $service): PartyResource
    {
        return new PartyResource($service->updateSupplier($supplier, $request->validated(), $request->user()));
    }

    public function destroy(Request $request, Supplier $supplier): JsonResponse
    {
        $supplier->forceFill(['is_active' => false, 'updated_by' => $request->user()?->id])->save();
        $supplier->delete();

        return response()->json(['message' => 'Supplier deleted.']);
    }

    public function toggleStatus(Request $request, Supplier $supplier): JsonResponse
    {
        $supplier->forceFill([
            'is_active' => $request->boolean('is_active'),
            'updated_by' => $request->user()?->id,
        ])->save();

        return response()->json(['message' => 'Supplier status updated.', 'data' => new PartyResource($supplier->refresh())]);
    }

    public function restore(Request $request, int $id): JsonResponse
    {
        $supplier = Supplier::query()->onlyTrashed()->findOrFail($id);
        $supplier->restore();
        $supplier->forceFill(['is_active' => true, 'updated_by' => $request->user()?->id])->save();

        return response()->json(['message' => 'Supplier restored.', 'data' => new PartyResource($supplier->refresh())]);
    }

    public function options(): JsonResponse
    {
        return response()->json([
            'data' => Supplier::query()
                ->where('is_active', true)
                ->when(request()->user()?->tenant_id, fn ($query, $tenantId) => $query->where('tenant_id', $tenantId))
                ->orderBy('name')
                ->limit(50)
                ->get(['id', 'name', 'phone', 'current_balance']),
        ]);
    }
}
