<?php

namespace App\Modules\Purchase\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Purchase\Http\Requests\PurchaseStoreRequest;
use App\Modules\Purchase\Http\Resources\PurchaseResource;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Services\PurchaseEntryService;
use Illuminate\Http\JsonResponse;

class PurchaseController extends Controller
{
    public function index(): JsonResponse
    {
        $purchases = Purchase::query()
            ->with('supplier:id,name')
            ->latest('purchase_date')
            ->latest('id')
            ->paginate(request()->integer('per_page', 15));

        return response()->json(PurchaseResource::collection($purchases)->response()->getData(true));
    }

    public function store(PurchaseStoreRequest $request, PurchaseEntryService $service): JsonResponse
    {
        $purchase = $service->create($request->validated(), $request->user());

        return (new PurchaseResource($purchase))
            ->additional(['message' => 'Purchase posted and stock received.'])
            ->response()
            ->setStatusCode(201);
    }
}
