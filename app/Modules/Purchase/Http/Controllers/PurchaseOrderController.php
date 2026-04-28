<?php

namespace App\Modules\Purchase\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Purchase\Http\Requests\PurchaseOrderReceiveRequest;
use App\Modules\Purchase\Http\Requests\PurchaseOrderStoreRequest;
use App\Modules\Purchase\Http\Resources\PurchaseResource;
use App\Modules\Purchase\Models\PurchaseOrder;
use App\Modules\Purchase\Services\PurchaseEntryService;
use App\Modules\Purchase\Services\PurchaseOrderService;
use Illuminate\Http\JsonResponse;

class PurchaseOrderController extends Controller
{
    public function index(): JsonResponse
    {
        $orders = PurchaseOrder::query()
            ->with('supplier:id,name')
            ->latest('order_date')
            ->latest('id')
            ->paginate(request()->integer('per_page', 15));

        return response()->json(PurchaseResource::collection($orders)->response()->getData(true));
    }

    public function store(PurchaseOrderStoreRequest $request, PurchaseOrderService $service): JsonResponse
    {
        $order = $service->create($request->validated(), $request->user());

        return (new PurchaseResource($order))
            ->additional(['message' => 'Purchase order created.'])
            ->response()
            ->setStatusCode(201);
    }

    public function show(PurchaseOrder $order): JsonResponse
    {
        $order->load(['supplier:id,name', 'items.product:id,name,sku,mrp,purchase_price,selling_price,cc_rate', 'items', 'receivedPurchase']);
        return response()->json(['data' => $order]);
    }

    public function approve(PurchaseOrder $order): JsonResponse
    {
        $order->update(['status' => 'approved']);
        return response()->json(['message' => 'Order approved', 'data' => $order]);
    }

    public function receive(PurchaseOrderReceiveRequest $request, PurchaseOrder $order, PurchaseOrderService $service, PurchaseEntryService $purchases): JsonResponse
    {
        $purchase = $service->receive($order, $request->validated(), $request->user(), $purchases);
        $order->refresh()->load(['supplier:id,name', 'items.product:id,name', 'receivedPurchase']);

        return response()->json([
            'message' => 'Order received and purchase bill posted.',
            'data' => $order,
            'purchase' => new PurchaseResource($purchase),
            'print_url' => route('purchases.print', $purchase),
        ]);
    }

    public function pay(PurchaseOrder $order): JsonResponse
    {
        $order->update(['status' => 'paid']);
        return response()->json(['message' => 'Order marked as paid', 'data' => $order]);
    }
}
