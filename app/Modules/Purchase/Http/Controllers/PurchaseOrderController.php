<?php

namespace App\Modules\Purchase\Http\Controllers;

use App\Core\DTOs\TableQueryData;
use App\Http\Controllers\ModularController;
use App\Modules\Purchase\Http\Requests\PurchaseOrderReceiveRequest;
use App\Modules\Purchase\Http\Requests\PurchaseOrderStoreRequest;
use App\Modules\Purchase\Http\Resources\PurchaseResource;
use App\Modules\Purchase\Models\PurchaseOrder;
use App\Modules\Purchase\Services\PurchaseEntryService;
use App\Modules\Purchase\Services\PurchaseOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="PURCHASE - Purchase Workflow",
 *     description="API endpoints for PURCHASE - Purchase Workflow"
 * )
 */
class PurchaseOrderController extends ModularController
{
    /**
     * @OA\Get(
     *     path="/purchase/orders",
     *     summary="Api Orders Index",
     *     tags={"PURCHASE - Orders"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function index(Request $request, PurchaseOrderService $service): JsonResponse
    {
        $orders = $service->table(
            TableQueryData::fromRequest($request, ['supplier_id', 'status', 'from', 'to']),
            $request->user(),
        );

        return response()->json(PurchaseResource::collection($orders)->response()->getData(true));
    }

    /**
     * @OA\Post(
     *     path="/purchase/orders",
     *     summary="Api Orders Store",
     *     tags={"PURCHASE - Orders"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\RequestBody(required=false, @OA\JsonContent(type="object", additionalProperties=true)),
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(PurchaseOrderStoreRequest $request, PurchaseOrderService $service): JsonResponse
    {
        $order = $service->create($request->validated(), $request->user());

        return (new PurchaseResource($order))
            ->additional(['message' => 'Purchase order created.'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * @OA\Get(
     *     path="/purchase/orders/{order}",
     *     summary="Api Orders Show",
     *     tags={"PURCHASE - Orders"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function show(PurchaseOrder $order): JsonResponse
    {
        $order->load(['supplier:id,name', 'items.product:id,name,sku,mrp,purchase_price,selling_price,cc_rate', 'items', 'receivedPurchase']);

        return response()->json(['data' => $order]);
    }

    /**
     * @OA\Post(
     *     path="/purchase/orders/{order}/approve",
     *     summary="Api Purchase Orders Approve",
     *     tags={"PURCHASE - Orders"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\RequestBody(required=false, @OA\JsonContent(type="object", additionalProperties=true)),
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function approve(Request $request, PurchaseOrder $order, PurchaseOrderService $service): JsonResponse
    {
        $order = $service->approve($order, $request->user());

        return response()->json(['message' => 'Order approved', 'data' => $order]);
    }

    /**
     * @OA\Post(
     *     path="/purchase/orders/{order}/receive",
     *     summary="Api Purchase Orders Receive",
     *     tags={"PURCHASE - Orders"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\RequestBody(required=false, @OA\JsonContent(type="object", additionalProperties=true)),
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
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

    /**
     * @OA\Post(
     *     path="/purchase/orders/{order}/pay",
     *     summary="Api Purchase Orders Pay",
     *     tags={"PURCHASE - Orders"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\RequestBody(required=false, @OA\JsonContent(type="object", additionalProperties=true)),
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function pay(Request $request, PurchaseOrder $order, PurchaseOrderService $service): JsonResponse
    {
        $order = $service->markPaid($order, $request->user());

        return response()->json(['message' => 'Order marked as paid', 'data' => $order]);
    }
}
