<?php

namespace App\Modules\Purchase\Http\Controllers;

use App\Core\Support\WorkspaceScope;
use App\Http\Controllers\Controller;
use App\Modules\Purchase\Http\Requests\PurchaseOrderStoreRequest;
use App\Modules\Purchase\Http\Resources\PurchaseResource;
use App\Modules\Purchase\Models\PurchaseOrder;
use App\Modules\Purchase\Services\PurchaseOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->is_owner || $request->user()?->can('purchase.orders.manage'), 403);

        $orders = WorkspaceScope::apply(PurchaseOrder::query(), $request->user(), 'purchase_orders', ['tenant_id', 'company_id', 'store_id'])
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
}
