<?php

namespace App\Modules\Party\Http\Controllers;

use App\Core\DTOs\TableQueryData;
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
        return PartyResource::collection($service->suppliers(TableQueryData::fromRequest($request, ['is_active'])));
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
        return new PartyResource($service->updateSupplier($supplier, $request->validated(), $request->user()));
    }

    public function destroy(Request $request, Supplier $supplier): JsonResponse
    {
        $supplier->forceFill(['is_active' => false, 'updated_by' => $request->user()?->id])->save();
        $supplier->delete();

        return response()->json(['message' => 'Supplier deleted.']);
    }

    public function options(): JsonResponse
    {
        return response()->json([
            'data' => Supplier::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->limit(50)
                ->get(['id', 'name', 'phone', 'current_balance']),
        ]);
    }
}
